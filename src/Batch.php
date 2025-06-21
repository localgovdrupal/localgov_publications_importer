<?php

namespace Drupal\localgov_publications_importer;

use Drupal\localgov_publications_importer\Service\Importer;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Batch operations for importing content.
 */
class Batch {

  /**
   * Get the importer service.
   */
  protected static function importer(string $importPipelineId): Importer {
    $importer = \Drupal::service('localgov_publications_importer.importer');
    $importer->setPipeline($importPipelineId);
    return $importer;
  }

  /**
   * Runs the extract plugin.
   */
  public static function extract(string $importPipelineId, string $filename, array &$context): void {
    $context['results']['import'] = self::importer($importPipelineId)->extract($filename);
  }

  /**
   * Runs the transform plugins, one page at a time.
   */
  public static function transform(string $importPipelineId, array &$context): void {

    $importer = self::importer($importPipelineId);

    $pluginIds = $importer->getTransformPluginIds();

    // Set this method to keep being called until we decide we're done.
    $context['finished'] = 0;

    /** @var \Drupal\localgov_publications_importer\Import $import */
    $import = $context['results']['import'];

    // Do this one step at a time by limiting the loop using the sandbox.
    // @todo Ask the plugin at this point if it wants to work page by page.
    // Then if not we could just do one call.
    foreach ($pluginIds as $pluginId) {
      foreach ($import->getPages() as $pageNumber => $page) {
        if (isset($context['sandbox']['done'][$pluginId][$pageNumber])) {
          continue;
        }
        $importer->transform($import, $pluginId, $pageNumber);
        $context['sandbox']['done'][$pluginId][$pageNumber] = TRUE;
        return;
      }
    }

    $context['finished'] = 1;
  }

  /**
   * Runs the save plugin.
   */
  public static function save(string $importPipelineId, array &$context): void {
    // We might not be importing to nodes, eventually... Generalise this.
    $node = self::importer($importPipelineId)->save($context['results']['import']);
    $context['results']['redirect'] = '/node/' . $node->id();
  }

  /**
   * Batch is finished.
   */
  public static function finished(bool $success, array $results, array $operations, string $elapsed): ?RedirectResponse {

    if ($success) {
      \Drupal::messenger()->addMessage("Import complete. Here is your publication.");
      return new RedirectResponse($results['redirect']);
    }
    else {
      // @todo Handle failure.
    }

    return NULL;
  }

}
