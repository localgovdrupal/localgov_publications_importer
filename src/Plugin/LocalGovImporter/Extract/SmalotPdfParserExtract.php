<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Extract;

use Drupal\localgov_publications_importer\Attribute\Extract;
use Drupal\localgov_publications_importer\Import;
use Drupal\localgov_publications_importer\Plugin\ExtractInterface;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Parser as PdfParser;
use Smalot\PdfParser\Document as PdfDocument;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Extract operation that uses Smalot/pdfparser.
 */
#[Extract(
  id: 'smalot_pdfparser',
  label: new TranslatableMarkup('Smalot extract'),
  description: new TranslatableMarkup('Extract operation that uses Smalot/pdfparser')
)]
class SmalotPdfParserExtract implements ExtractInterface {

  protected string $pathToFile;

  protected PdfDocument $pdf;

  public function setSource(string $pathToFile): self {
    $this->pathToFile = $pathToFile;
    return $this;
  }

  public function getImport(): ?Import {

    $config = new PdfParserConfig();
    // An empty string can prevent words from breaking up.
    $config->setHorizontalOffset('');

    // Parse PDF file and build necessary objects.
    $parser = new PdfParser([], $config);
    $this->pdf = $parser->parseFile($this->pathToFile);

    $import = new Import($this->pathToFile);

    $details = $this->pdf->getDetails();
    if (isset($details['Title'])) {
      $import->setTitle($details['Title']);
    }

    // Get the pages and sort them. They don't come back in order by default.
    $pages = $this->pdf->getPages();
    usort($pages, function ($a, $b) {
      return intval($a->getPageNumber()) <=> intval($b->getPageNumber());
    });

    // @todo: We need our own page representation so the whole project isn't
    // tied to what smalot/pdfparser does.
    $import->setPages($pages);

    return $import;
  }

}
