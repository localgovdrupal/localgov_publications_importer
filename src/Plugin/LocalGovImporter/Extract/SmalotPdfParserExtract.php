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
use Smalot\PdfParser\Element\ElementName;
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
   */
  protected $importedImages = [];

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

    $config = new PdfParserConfig();
    // An empty string can prevent words from breaking up.
    $config->setHorizontalOffset('');

    // Parse PDF file and build necessary objects.
    $parser = new PdfParser([], $config);
    $pdf = $parser->parseFile($this->pathToFile);

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
      $import->addPage($page);

      $tempDir = 'temporary://localgov_publications_importer';
      $this->fileSystem->prepareDirectory($tempDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

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
            // This is when it's a pdfObject??
            $colorSpace = $elements['ColorSpace']->getHeader()
              ->get(0)
              ->getContent();
          }
        }
        else {
          $colorSpace = '';
        }

        $dataFile = $tempDir . '/' . $this->uuid->generate();
        $this->fileSystem->saveData($image->getContent(), $dataFile, FileExists::Replace);

        $image = new Image();
        $image->setWidth($width);
        $image->setHeight($height);
        $image->setBitsPerComponent($bitsPerComponent);
        $image->setColorSpace($colorSpace);
        $image->setFilter($filter);
        $image->setXObjectDataFile($dataFile);
        $page->addImage($image);
      }
    }
    return $import;
  }

}
