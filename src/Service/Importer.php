<?php

namespace Drupal\localgov_publications_importer\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\localgov_publications_importer\Entity\ImportPipeline;
use Drupal\localgov_publications_importer\ExtractOperationManager;
use Drupal\localgov_publications_importer\Entity\Import;
use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\localgov_publications_importer\Plugin\ExtractInterface;
use Drupal\localgov_publications_importer\Plugin\SaveInterface;
use Drupal\localgov_publications_importer\SaveOperationManager;
use Drupal\localgov_publications_importer\TransformOperationManager;
use Drupal\node\NodeInterface;

/**
 * Imports content from uploaded files.
 */
class Importer {

  /**
   * The import pipeline that we're using.
   *
   * This controls which plugins we use, their order and configuration.
   */
  protected ImportPipeline $pipeline;

  /**
   * Constructor.
   */
  public function __construct(
    protected ExtractOperationManager $extractOperationManager,
    protected TransformOperationManager $transformOperationManager,
    protected SaveOperationManager $saveOperationManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Set the pipeline we'll be using to import content.
   */
  public function setPipeline(string $importPipelineId): void {
    $this->pipeline = $this->entityTypeManager
      ->getStorage('import_pipeline')
      ->load($importPipelineId);
  }

  /**
   * Imports the given file.
   *
   * Call this to run the whole import process in one go.
   */
  public function importPdf($pathToFile): ?NodeInterface {
    $import = $this->extract($pathToFile);
    foreach ($this->transformOperations() as $transformOperation) {
      $transformOperation->transform($import);
    }
    return $this->save($import);
  }

  /**
   * Run the extract part of the process.
   */
  public function extract(ImportInterface $import): Import {
    $this->extractOperation()->extract($import);
    $import->save();
    return $import;
  }

  /**
   * Run a single step of the transform part of the process.
   */
  public function transform(ImportInterface $import, string $pluginID, int $page): void {

    foreach ($this->transformOperations() as $transformOperation) {
      if ($transformOperation->getPluginId() === $pluginID) {
        $transformOperation->transform($import, $page);
        break;
      }
    }
  }

  /**
   * Run the save part of the process.
   */
  public function save($import): ?EntityInterface {
    return $this->saveOperation()->import($import);
  }

  /**
   * Gets the extract operation to use.
   */
  protected function extractOperation(): ExtractInterface {
    return $this->extractOperationManager->createInstance($this->pipeline->extract_plugin, $this->pipeline->extract_plugin_configuration);
  }

  /**
   * Gets the IDs of the transform operations to use.
   */
  public function getTransformPluginIds(): array {
    return $this->pipeline->transform_plugins;
  }

  /**
   * Gets the transform operations to use.
   *
   * @return \Drupal\localgov_publications_importer\Plugin\TransformInterface[]
   *   Array of transform operations.
   */
  protected function transformOperations(): array {
    $operations = [];
    foreach ($this->pipeline->transform_plugins as $index => $pluginId) {
      $pluginConfig = $this->pipeline->transform_plugin_configurations[$index] ?? [];
      $operations[] = $this->transformOperationManager->createInstance($pluginId, $pluginConfig);
    }
    return $operations;
  }

  /**
   * Gets the save operation to use.
   */
  protected function saveOperation(): SaveInterface {
    return $this->saveOperationManager->createInstance($this->pipeline->save_plugin, $this->pipeline->save_plugin_configuration);
  }

}
