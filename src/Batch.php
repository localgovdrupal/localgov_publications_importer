<?php

namespace Drupal\localgov_publications_importer;

use Drupal\localgov_publications_importer\Exception\RetryableTransformFailure;
use Drupal\localgov_publications_importer\Service\Importer;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
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

    if (isset($context['sandbox']['done'])) {
      $totalSteps = count($pluginIds) * count($import->getPages());
      $completedSteps = 0;
      foreach ($context['sandbox']['done'] as $steps) {
        $completedSteps += count($steps);
      }
      if ($totalSteps === 0) {
        // If there's no steps, because there's no transform plugins, or there's
        // no pages to run them on, we're done.
        $context['finished'] = 1;
      }
      else {
        $context['finished'] = $completedSteps / $totalSteps;
      }
    }

    // Do this one step at a time by limiting the loop using the sandbox.
    // @todo Ask the plugin at this point if it wants to work page by page.
    // Then if not we could just do one call.
    foreach ($pluginIds as $pluginId) {
      foreach ($import->getPages() as $pageNumber => $page) {
        if (isset($context['sandbox']['done'][$pluginId][$pageNumber])) {
          continue;
        }
        try {
          $importer->transform($import, $pluginId, $pageNumber);
          $context['sandbox']['done'][$pluginId][$pageNumber] = TRUE;
        }
        catch (RetryableTransformFailure $e) {
          // If we end up in here, 'done' won't get set in the sandbox for this
          // plugin/page combo. It'll therefore get retried.
          if (isset($context['sandbox']['retries'][$pluginId][$pageNumber])) {
            // If we already retried, and failed again, don't keep trying.
            $context['sandbox']['done'][$pluginId][$pageNumber] = TRUE;
            \Drupal::messenger()->addError('Transform ' . $pluginId . ' for page ' . $pluginId . ' failed.');
          }
          $context['sandbox']['retries'][$pluginId][$pageNumber] = 1;
        }
        return;
      }
    }

    // Set this to 1 if we make it out of the loop,
    // to ensure we finish this step.
    $context['finished'] = 1;
  }

  /**
   * Runs the save plugin.
   */
  public static function save(string $importPipelineId, array &$context): void {
    // We might not be importing to nodes, eventually... Generalise this.
    $node = self::importer($importPipelineId)->save($context['results']['import']);
    if ($node instanceof NodeInterface) {
      $context['results']['redirect'] = '/node/' . $node->id();
    }
  }

  /**
   * Batch is finished.
   */
  public static function finished(bool $success, array $results, array $operations, string $elapsed): ?RedirectResponse {

    if ($success) {
      if (isset($results['redirect'])) {
        \Drupal::messenger()->addMessage("Import complete. Here is your publication.");
        return new RedirectResponse($results['redirect']);
      }
      else {
        \Drupal::messenger()->addWarning("Nothing was imported. Please check the pipeline configuration.");
      }
    }
    else {
      // @todo Handle failure.
    }

    return NULL;
  }

}
