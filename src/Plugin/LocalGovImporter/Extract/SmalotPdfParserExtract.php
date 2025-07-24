<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Extract;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Extract;
use Drupal\localgov_publications_importer\Image;
use Drupal\localgov_publications_importer\Import;
use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\localgov_publications_importer\Page;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Element\ElementMissing;
use Smalot\PdfParser\Element\ElementName;
use Smalot\PdfParser\Element\ElementXRef;
use Smalot\PdfParser\Page as PdfPage;
use Smalot\PdfParser\Parser as PdfParser;
use Smalot\PdfParser\XObject\Image as XObjectImage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extract operation that uses Smalot/pdfparser.
 */
#[Extract(
  id: 'smalot_pdfparser',
  label: new TranslatableMarkup('Smalot extract'),
  description: new TranslatableMarkup('Extract operation that uses Smalot/pdfparser')
)]
class SmalotPdfParserExtract extends ExtractPluginBase implements ContainerFactoryPluginInterface {

  /**
   * An array of MD5 hashes that we'll use to not import duplicated images.
   *
   * @var string[]
   */
  protected array $importedImages = [];

  /**
   * The directory where we'll save temporary files.
   *
   * @var string
   */
  protected string $tempDir = 'temporary://localgov_publications_importer';

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('uuid'),
    );
  }

  /**
   * Constructor.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected FileSystemInterface $fileSystem,
    protected UuidInterface $uuid,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public function getImport(): ?ImportInterface {

    $pdf = $this->parseFile();
    $import = new Import($this->pathToFile);

    $details = $pdf->getDetails();
    if (isset($details['Title']) && $details['Title'] !== '') {
      $import->setTitle($details['Title']);
    }
    else {
      // Fall back to the filename if we can't find a title in the PDF.
      // This isn't ideal, but we need to have a title to save a node.
      $import->setTitle(basename($this->pathToFile));
    }

    // Get the pages and sort them. They don't come back in order by default.
    $pdfPages = $pdf->getPages();
    usort($pdfPages, function ($a, $b) {
      return intval($a->getPageNumber()) <=> intval($b->getPageNumber());
    });

    $this->fileSystem->prepareDirectory($this->tempDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    foreach ($pdfPages as $pdfPage) {

      // Don't add empty pages.
      $content = trim($pdfPage->getText());
      if ($content === '') {
        continue;
      }

      $page = new Page();
      $page->setTitle('Page ' . $pdfPage->getPageNumber());
      $page->setContent($content);
      $page->setPageNumber($pdfPage->getPageNumber());

      $this->addImages($pdfPage, $page);
      $this->addLinks($pdfPage, $page);

      $import->addPage($page);

    }
    return $import;
  }

  /**
   * Set up the parser and parse the file.
   */
  protected function parseFile(): Document {
    $config = new PdfParserConfig();
    // An empty string can prevent words from breaking up.
    $config->setHorizontalOffset('');
    $parser = new PdfParser([], $config);
    return $parser->parseFile($this->pathToFile);
  }

  /**
   * Extracts the images from this PDF page.
   *
   * The image content is written to a temp file, and the metadata is saved to
   * an object on the extract page, so we can use it later in the process.
   */
  protected function addImages(PdfPage $pdfPage, Page $exportPage): void {
    foreach ($pdfPage->getXObjects() as $xObject) {
      if (!$xObject instanceof XObjectImage) {
        continue;
      }

      $image = $xObject;

      // There are duplicate references on the page sometimes.
      // De-dupe the Image XObjects using the MD5 hash of the content.
      // @phpstan-ignore-next-line
      $imageHash = md5($image->getContent());
      if (in_array($imageHash, $this->importedImages, TRUE)) {
        continue;
      }
      $this->importedImages[] = $imageHash;

      // These could all be $image->get('Filter'); I think...
      $filter = $image->getHeader()->get('Filter')->getContent();
      $width = (int) $image->getHeader()->get('Width')->getContent();
      $height = (int) $image->getHeader()->get('Height')->getContent();
      $bitsPerComponent = (int) $image->getHeader()->get('BitsPerComponent')->getContent();

      // We need to get the image color space like this for some reason.
      $elements = $image->getHeader()->getElements();
      if (isset($elements['ColorSpace'])) {
        if ($elements['ColorSpace'] instanceof ElementName) {
          $colorSpace = $elements['ColorSpace']->getContent();
        }
        else {
          // This is when it's a pdfObject.
          $colorSpace = $elements['ColorSpace']->getHeader()
            ->get(0)
            ->getContent();
        }
      }
      else {
        $colorSpace = '';
      }

      $dataFile = $this->tempDir . '/' . $this->uuid->generate();
      $this->fileSystem->saveData($image->getContent(), $dataFile, FileExists::Replace);

      $image = new Image();
      $image->setWidth($width);
      $image->setHeight($height);
      $image->setBitsPerComponent($bitsPerComponent);
      $image->setColorSpace($colorSpace);
      $image->setFilter($filter);
      $image->setxObjectDataFile($dataFile);
      $exportPage->addImage($image);
    }
  }

  /**
   * Add links.
   *
   * This looks for link annotations in the PDF page content, and works them
   * into the content of the page.
   */
  protected function addLinks(PdfPage $pdfPage, Page $exportPage): void {
    foreach ($this->getAnnotations($pdfPage) as $annotation) {

      $subType = $annotation->get('Subtype')->getContent();
      if ($subType !== 'Link') {
        continue;
      }
      $action = $annotation->get('A');

      $rect = [];
      foreach ($annotation->get('Rect')->getRawContent() as $coordinate) {
        $rect[] = $coordinate->getContent();
      }

      $uri = (string) $action->get('URI');

      if (empty($uri)) {
        continue;
      }

      // Rect = lower left x, lower left y, upper right x, upper right y.
      [$llx, $lly, $urx, $ury] = $rect;

      // Look for text near the midpoint of the box.
      $textX = ($llx + $urx) / 2;
      $textY = ($lly + $ury) / 2;

      // Set the area to search to the dimensions of the box, plus a bit extra.
      $extra = 1.9;
      $xError = ($urx - $llx) / $extra;
      $yError = ($ury - $lly) / $extra;

      // Can we find the text this annotation is around?
      $texts = $pdfPage->getTextXY($textX, $textY, $xError, $yError);

      // There may be multiple text items.
      // We could do better than this and look for the text all combined as one
      // string, but this is easy for the moment.
      foreach ($texts as $text) {
        // Index 0 is position data. 1 is the text.
        $linktext = $text[1];
        if ($linktext) {
          $this->replaceContent($exportPage, $linktext, "<a href=\"{$uri}\">{$linktext}</a>");
        }
      }
    }
  }

  /**
   * Get the annotations from a page.
   */
  protected function getAnnotations(PdfPage $pdfPage) {
    $rtn = [];
    $annotations = $pdfPage->get('Annots');
    if (!$annotations instanceof ElementMissing) {
      foreach ($annotations->getRawContent() as $element) {
        if ($element instanceof ElementXRef) {
          $rtn[] = $element->getObject();
        }
      }
    }
    return $rtn;
  }

  /**
   * Replace content in the page.
   *
   * This could be a method on the page?
   */
  protected function replaceContent(Page $exportPage, $search, $replace) {
    $text = $exportPage->getContent();
    $text = str_replace($search, $replace, $text);
    $exportPage->setContent($text);
  }

}
