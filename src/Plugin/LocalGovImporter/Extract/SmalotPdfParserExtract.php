<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Extract;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Extract;
use Drupal\localgov_publications_importer\Image;
use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\localgov_publications_importer\Page;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Element\ElementArray;
use Smalot\PdfParser\Element\ElementName;
use Smalot\PdfParser\Element\ElementXRef;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\PDFObject;
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

  use LoggerChannelTrait;

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
  public function extract(ImportInterface $import): void {

    $pdf = $this->parseFile($import->getFile()->getFileUri());

    $this->setTitle($import, $pdf);

    // Get the pages and sort them. They don't come back in order by default.
    $pdfPages = $pdf->getPages();
    usort($pdfPages, function ($a, $b) {
      return intval($a->getPageNumber()) <=> intval($b->getPageNumber());
    });

    $this->fileSystem->prepareDirectory($this->tempDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    foreach ($pdfPages as $pdfPage) {

      $content = $this->cleanText($pdfPage->getText());

      // Don't add empty pages.
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
  }

  /**
   * Cleans the text in preparation for using it.
   */
  protected function cleanText(string $text): string {

    // This char is present in at least one PDF in the test suite.
    // It stops the text it's in being saved to the DB.
    $text = str_replace("\xD7", ' ', $text);

    // This is in the "Unicode private range". We may need to remove all of it.
    $text = str_replace("\uF0B7", ' ', $text);

    // Remove leading/trailing whitespace.
    return trim($text);
  }

  /**
   * Set the title of an import from the parsed PDF.
   */
  protected function setTitle(ImportInterface $import, Document $pdf): void {
    $details = $pdf->getDetails();

    $title = NULL;

    if (isset($details['Title'])) {
      $title = $details['Title'];
    }
    else {
      $this->getLogger('localgov_publications_importer')->debug("Title not found in details.");
    }

    if (is_string($title)) {
      if ($title === '') {
        $this->getLogger('localgov_publications_importer')->debug("Title empty in details.");
      }
    }
    else {
      // Ignore null here, so we don't log duplicate messages.
      if ($title !== NULL) {
        $this->getLogger('localgov_publications_importer')->debug("Title not a string in details. " . gettype($title) . " found.");
      }
      // Se this to null so we don't try to use it.
      $title = NULL;
    }

    if ($title !== NULL) {
      $import->setTitle($details['Title']);
      return;
    }

    // Fall back to the filename if we can't find a title in the PDF.
    // This isn't ideal, but we need to have a title to save a node.
    $import->setTitle(basename($import->getFile()->getFileUri()));
  }

  /**
   * Set up the parser and parse the file.
   */
  protected function parseFile(string $pathToFile): Document {
    $config = new PdfParserConfig();
    // An empty string can prevent words from breaking up.
    $config->setHorizontalOffset('');
    $parser = new PdfParser([], $config);
    return $parser->parseFile($pathToFile);
  }

  /**
   * Extracts the images from this PDF page.
   *
   * The image content is written to a temp file, and the metadata is saved to
   * an object on the extract page, so we can use it later in the process.
   */
  protected function addImages(PdfPage $pdfPage, Page $importPage): void {
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
      $colorSpace = '';
      $elements = $image->getHeader()->getElements();
      if (isset($elements['ColorSpace'])) {
        if ($elements['ColorSpace'] instanceof ElementName) {
          $colorSpace = $elements['ColorSpace']->getContent();
        }
        elseif ($elements['ColorSpace'] instanceof PDFObject) {
          $colorSpace = $elements['ColorSpace']->getHeader()
            ->get(0)
            ->getContent();
        }
        elseif ($elements['ColorSpace'] instanceof ElementArray) {
          // Handle when $elements['ColorSpace'] is an ElementArray,
          // like in Where-your-money-goes-2025-26.pdf.
          $details = $elements['ColorSpace']->getDetails();
          // There's other data in here too. EG:
          // 0 => 'Indexed'
          // 1 => ['ICCBased']
          // 2 => 255
          // 3 => ['Filter' => 'FlateDecode', 'Length' => 708].
          $colorSpace = $details[0];
        }
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
      $importPage->addImage($image);
    }
  }

  /**
   * Add links.
   *
   * This looks for link annotations in the PDF page content, and works them
   * into the content of the page.
   */
  protected function addLinks(PdfPage $pdfPage, Page $importPage): void {
    $search = [];
    $replace = [];

    $annotations = [];

    foreach ($this->getAnnotations($pdfPage) as $annotation) {

      $subType = $annotation->get('Subtype')->getContent();
      if ($subType == 'Link') {
        $annotations[] = $annotation;
      }
    }

    if ($annotations === []) {
      return;
    }

    $links = [];

    foreach ($annotations as $annotation) {
      $rect = [];
      foreach ($annotation->get('Rect')->getRawContent() as $coordinate) {
        $rect[] = $coordinate->getContent();
      }

      $uri = '';

      $action = $annotation->get('A');
      if ($action instanceof PDFObject) {
        $uri = (string) $action->get('URI');
      }
      if ($action instanceof Header) {
        $uri = (string) $action->get('URI');
      }

      if (!empty($uri)) {
        $links[] = [
          'uri' => $uri,
          'rect' => $rect,
        ];
      }
    }

    foreach ($links as $i => $link) {

      // Rect = lower left x, lower left y, upper right x, upper right y.
      [$llx, $lly, $urx, $ury] = $link['rect'];

      // Look for text near the midpoint of the box.
      $textX = ($llx + $urx) / 2;
      $textY = ($lly + $ury) / 2;

      // Set the area to search to the dimensions of the box, plus a bit extra.
      $extra = 0.5;
      $xError = ($urx - $llx) * $extra;
      $yError = ($ury - $lly) * $extra;

      // Can we find the text this annotation is around?
      $texts = $pdfPage->getTextXY($textX, $textY, $xError, $yError);

      // There may be multiple text items found. Combine them into one.
      $textSearch = array_map(function ($text) {
        // Index 0 is position data. 1 is the text.
        return $text[1];
      }, $texts);

      $linkText = $this->buildLinkText($textSearch);
      if ($linkText === '') {
        // Log this...
        unset($links[$i]);
      }
      else {
        $links[$i]['text'] = $linkText;
      }
    }

    // Combine links where we can. This makes for more accurate search/replace.
    $prevIndex = NULL;
    foreach ($links as $index => $link) {

      // Loop over all the links, comparing each with the one before it.
      // They seem to be in page order.
      if ($prevIndex === NULL) {
        $prevIndex = $index;
        continue;
      }

      // If two URLs in a row are the same, we can try to combine them.
      // This happens when links go over a line break.
      if ($links[$prevIndex]['uri'] === $links[$index]['uri']) {

        // Build a regex to look for the combined text,
        // with whitespace in between.
        $chars = '/.^$*+-?()[]{}\|';
        $combinedTextRegex = '/' . addcslashes($links[$prevIndex]['text'], $chars) . '\s+' . addcslashes($links[$index]['text'], $chars) . '/';

        // If we find the combined text, use it as a replacement and remove the
        // other identical link.
        if ($combinedTextResult = $this->pageContains($importPage, $combinedTextRegex)) {
          $links[$prevIndex]['text'] = $combinedTextResult;
          unset($links[$index]);

          // Set this to the previous, so that when it's moved on at the end of
          // the loop, we end of looking at the previous index again.
          $index = $prevIndex;
        }
      }
      $prevIndex = $index;
    }

    foreach ($links as $link) {
      $search[] = $link['text'];
      $replace[] = "<a href=\"{$link['uri']}\">{$link['text']}</a>";
    }

    if (count($search) > 0) {
      if (!$this->replaceContent($importPage, $search, $replace)) {
        // If zero replacements were made, log the failure:
        $this->getLogger('localgov_publications_importer')->debug("Couldn't find text '{$search}' on page {$importPage->getPageNumber()} of {$importPage->getTitle()}");
      }
    }
  }

  /**
   * Get the annotations from a page.
   */
  protected function getAnnotations(PdfPage $pdfPage): array {

    $rtn = [];
    $annotations = [];

    $annotationCollection = $pdfPage->get('Annots');
    if ($annotationCollection instanceof ElementArray) {
      foreach ($annotationCollection->getRawContent() as $element) {
        $annotations[] = $element;
      }
    }
    if ($annotationCollection instanceof PDFObject) {
      $elements = $annotationCollection->getHeader()->getElements();
      foreach ($elements as $element) {
        $annotations[] = $element;
      }
    }

    foreach ($annotations as $annotation) {
      if ($annotation instanceof PDFObject) {
        $rtn[] = $annotation;
      }
      elseif ($annotation instanceof ElementXRef) {
        $rtn[] = $annotation->getObject();
      }
    }

    return $rtn;
  }

  /**
   * Replace content in the page.
   *
   * This could be a method on the page?
   *
   * @return bool
   *   If at least one replacement was made.
   */
  protected function replaceContent(Page $importPage, array $search, array $replace): bool {
    $text = $importPage->getContent();
    $text = str_replace($search, $replace, $text, $count);
    $importPage->setContent($text);

    return $count > 0;
  }

  /**
   * Does the page contain this text?
   *
   * This could be a method on the page?
   *
   * @return ?string
   *   The matched string, if a string matched.
   */
  protected function pageContains(Page $importPage, string $pattern): ?string {
    if (preg_match($pattern, $importPage->getContent(), $matches)) {
      return $matches[0];
    }
    return NULL;
  }

  /**
   * Combines text search results into a string to search for.
   */
  protected function buildLinkText(array $textSearch): string {

    if (empty($textSearch)) {
      return '';
    }

    // Join all the text together.
    $linkText = implode('', $textSearch);

    // Trim any spaces off the start and end.
    $linkText = trim($linkText);

    $linkText = $this->cleanText($linkText);

    return $linkText;
  }

}
