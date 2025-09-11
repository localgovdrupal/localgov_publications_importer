<?php

namespace Drupal\localgov_publications_importer\Commands;

use Drush\Commands\DrushCommands;
use Drush\Attributes as CLI;
use Smalot\PdfParser\Element\ElementMissing;
use Smalot\PdfParser\Element\ElementXRef;
use Smalot\PdfParser\Parser;

/**
 * Drush commands for PDF analysis.
 */
class AnalyseCommand extends DrushCommands {

  /**
   * Check if we can use the requested file.
   */
  protected function checkFile(string $pdf_path): bool {
    // Check if file exists.
    if (!file_exists($pdf_path)) {
      $this->logger()->error("File not found: {$pdf_path}");
      return FALSE;
    }

    // Check if file is readable.
    if (!is_readable($pdf_path)) {
      $this->logger()->error("File is not readable: {$pdf_path}");
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Analyzes a PDF file and prints the object names found in it.
   *
   * @param string $pdf_path
   *   The path to the PDF file to analyze.
   *
   * @command localgov_publications_importer:extract
   * @aliases lpie
   * @usage drush localgov_publications_importer:analyze-pdf /path/to/file.pdf
   *   Analyze a PDF file and print object names.
   */
  public function analyzePdf(string $pdf_path): void {

    if (!$this->checkFile($pdf_path)) {
      return;
    }

    try {

      $this->extractManager = $this->container->get('plugin.manager.localgov_importer.extract');

      // Initialize the PDF parser.
      $parser = new Parser();

      // Parse the PDF file.
      $pdf = $parser->parseFile($pdf_path);

      foreach ($pdf->getPages() as $pageNumber => $page) {

        $text = $page->getTextArray();

        // With "Nice annotations".

        $this->output()->writeln("");
        $this->output()->writeln("New");
        $this->output()->writeln("");


        foreach ($page->getAnnotations() as $annotation) {
          $action = $annotation->getAction();
          $rect = $annotation->getRect();
          $flags = $annotation->getFlags();
          $subType = $annotation->getSubType();

          // rect = lower left x, lower left y, upper right x, upper right y.
          [$llx, $lly, $urx, $ury] = $rect;

          // Look for text near the midpoint of the box.
          $textX = ($llx + $urx) / 2;
          $textY = ($lly + $ury) / 2;

          // Set the area to search to the dimensions of the box, plus a bit extra.
          $extra = 1.9;
          $xError = ($urx - $llx) / $extra;
          $yError = ($ury - $lly) / $extra;

          $uri = (string) $action->get('URI');

          // Can we find the text this annotation is around?
          $texts = $page->getTextXY($textX, $textY, $xError, $yError);

          $fulltext = [];
          foreach ($texts as $text) {
            $fulltext[] = $text[1];
          }

          $this->output()->writeln("URI: $uri");
          $this->output()->writeln("Text:" . implode(" ", $fulltext));
          $this->output()->writeln("");
        }

        // Without "Nice annotations".

        $this->output()->writeln("");
        $this->output()->writeln("Old");
        $this->output()->writeln("");

          $annnotationObjects =  [];
          $annotations = $page->get('Annots');
          if (!$annotations instanceof ElementMissing) {
            foreach ($annotations->getRawContent() as $element) {
              if ($element instanceof ElementXRef) {
                $annnotationObjects[] = $element->getObject();
              }
            }
          }

        foreach ($annnotationObjects as $annotation) {
          $action = $annotation->get('A');
          $rect = [];
          foreach ($annotation->get('Rect')->getRawContent() as $coordinate) {
            $rect[] = $coordinate->getContent();
          }
          $flags = $annotation->getFlags();
          $subType = $annotation->getSubType();

          // rect = lower left x, lower left y, upper right x, upper right y.
          [$llx, $lly, $urx, $ury] = $rect;

          // Look for text near the midpoint of the box.
          $textX = ($llx + $urx) / 2;
          $textY = ($lly + $ury) / 2;

          // Set the area to search to the dimensions of the box, plus a bit extra.
          $extra = 1.9;
          $xError = ($urx - $llx) / $extra;
          $yError = ($ury - $lly) / $extra;

          $uri = (string) $action->get('URI');

          // Can we find the text this annotation is around?
          $texts = $page->getTextXY($textX, $textY, $xError, $yError);

          $fulltext = [];
          foreach ($texts as $text) {
            $fulltext[] = $text[1];
          }

          $this->output()->writeln("URI: $uri");
          $this->output()->writeln("Text:" . implode(" ", $fulltext));
          $this->output()->writeln("");
        }


      }

      //return;

      // Get the PDF details.
      $details = $pdf->getDetails();

      $this->output()->writeln("<info>PDF Analysis Results for: {$pdf_path}</info>");
      $this->output()->writeln("<info>========================================</info>");

      // Display basic PDF information.
      if (isset($details['Title'])) {
        $this->output()->writeln("<comment>Title:</comment> " . $details['Title']);
      }
      if (isset($details['Author'])) {
        $this->output()->writeln("<comment>Author:</comment> " . $details['Author']);
      }
      if (isset($details['Subject'])) {
        $this->output()->writeln("<comment>Subject:</comment> " . $details['Subject']);
      }
      if (isset($details['Creator'])) {
        $this->output()->writeln("<comment>Creator:</comment> " . $details['Creator']);
      }
      if (isset($details['Producer'])) {
        $this->output()->writeln("<comment>Producer:</comment> " . $details['Producer']);
      }
      if (isset($details['CreationDate'])) {
        $this->output()->writeln("<comment>Creation Date:</comment> " . $details['CreationDate']);
      }
      if (isset($details['ModDate'])) {
        $this->output()->writeln("<comment>Modification Date:</comment> " . $details['ModDate']);
      }

      $this->output()->writeln("");
      $this->output()->writeln("<info>PDF Object Names:</info>");
      $this->output()->writeln("<info>==================</info>");

      // Get objects from the PDF.
      $objects = $pdf->getObjects();

      if (empty($objects)) {
        $this->output()->writeln("<comment>No objects found in the PDF.</comment>");
        return;
      }

      // Track object types and names.
      $object_names = [];
      $object_types = [];

      foreach ($objects as $object) {
        $object_type = get_class($object);
        $object_name = $object_type;

        // Get the short class name (without namespace).
        $short_name = substr($object_type, strrpos($object_type, '\\') + 1);

        // Try to get additional identifying information.
        if (method_exists($object, 'getName')) {
          $name = $object->getName();
          if (!empty($name)) {
            $object_name = $short_name . " ({$name})";
          }
        }
        elseif (method_exists($object, 'getType')) {
          $type = $object->getType();
          if (!empty($type)) {
            $object_name = $short_name . " (Type: {$type})";
          }
        }
        else {
          $object_name = $short_name;
        }

        $object_names[] = $object_name;

        // Count object types.
        if (!isset($object_types[$short_name])) {
          $object_types[$short_name] = 0;
        }
        $object_types[$short_name]++;
      }

      // Display object names.
      $unique_names = array_unique($object_names);
      sort($unique_names);

      foreach ($unique_names as $name) {
        $this->output()->writeln("- {$name}");
      }

      $this->output()->writeln("");
      $this->output()->writeln("<info>Object Type Summary:</info>");
      $this->output()->writeln("<info>====================</info>");

      // Display object type counts.
      arsort($object_types);
      foreach ($object_types as $type => $count) {
        $this->output()->writeln("- {$type}: {$count}");
      }

      // Display total count.
      $total_objects = count($objects);
      $this->output()->writeln("");
      $this->output()->writeln("<info>Total Objects: {$total_objects}</info>");


    }
    catch (\Exception $e) {
      $this->logger()->error("Error parsing PDF: " . $e->getMessage());
    }
  }

}
