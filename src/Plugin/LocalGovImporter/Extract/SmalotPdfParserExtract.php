<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Extract;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Extract;
use Drupal\localgov_publications_importer\Import;
use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\localgov_publications_importer\Page;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Extract operation that uses Smalot/pdfparser.
 */
#[Extract(
  id: 'smalot_pdfparser',
  label: new TranslatableMarkup('Smalot extract'),
  description: new TranslatableMarkup('Extract operation that uses Smalot/pdfparser')
)]
class SmalotPdfParserExtract extends ExtractPluginBase {

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
    }

    return $import;
  }

}
