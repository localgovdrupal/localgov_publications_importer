<?php

namespace Drupal\localgov_publications_importer\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\localgov_publications_importer\ExtractOperationManager;
use Drupal\localgov_publications_importer\Import;
use Drupal\localgov_publications_importer\Plugin\ExtractInterface;
use Drupal\localgov_publications_importer\Plugin\SaveInterface;
use Drupal\localgov_publications_importer\Plugin\TransformInterface;
use Drupal\localgov_publications_importer\SaveOperationManager;
use Drupal\localgov_publications_importer\TransformOperationManager;
use Drupal\migrate\Plugin\migrate\destination\Entity;
use Drupal\node\NodeInterface;

/**
 * Imports content from uploaded files.
 */
class Importer {

  /**
   * Constructor.
   */
  public function __construct(
    protected ExtractOperationManager $extractOperationManager,
    protected TransformOperationManager $transformOperationManager,
    protected SaveOperationManager $saveOperationManager,
  ) {
  }

  /**
   * Imports the given file as a new LocalGov Publication page.
   * @deprecated As we don't really want to run batches all in one go.
   */
  public function importPdf($pathToFile): ?NodeInterface {
    $import = $this->extract($pathToFile);
    foreach ($this->transformOperations() as $transformOperation) {
      $transformOperation->transform($import);
    }
    return $this->save($import);
  }

  public function extract($pathToFile): Import {
    return $this->extractOperation()
      ->setSource($pathToFile)
      ->getImport();
  }

  public function transform($import, $pluginID, $page): void {

    foreach ($this->transformOperations() as $transformOperation) {
      if ($transformOperation->getPluginId() === $pluginID) {
        $transformOperation->transform($import, $page);
      }
    }
  }

  public function save($import): EntityInterface {
    return $this->saveOperation()->import($import);
  }

  /**
   * Gets the extract operation to use.
   */
  protected function extractOperation(): ExtractInterface {
    $operationDefinitions = $this->extractOperationManager->getDefinitions();

    // @todo There should only be one extract operation in this pipeline.
    // Provide a way to choose it, and the other operations!
    $operationDefinition = reset($operationDefinitions);

    /** @var \Drupal\localgov_publications_importer\Plugin\ExtractInterface $operation */
    $operation = $this->extractOperationManager->createInstance($operationDefinition['id']);

    return $operation;
  }

  public function getTransformPluginIds() {
    $ids = [];
    $operations = $this->transformOperations();
    foreach ($operations as $operation) {
      $ids[] = $operation->getPluginId();
    }
    return $ids;
  }

  /**
   * Gets the transform operations to use.
   *
   * @return \Drupal\localgov_publications_importer\Plugin\TransformInterface[]
   *   Array of transform operations.
   */
  protected function transformOperations(): array {
    $operations = [];
    foreach ($this->transformOperationManager->getDefinitions() as $operationDefinition) {
      $operations[] = $this->transformOperationManager->createInstance($operationDefinition['id']);
    }

    usort($operations, function (TransformInterface $a, TransformInterface $b) {
      return $a->order() <=> $b->order();
    });

    return $operations;
  }

  /**
   * Gets the save operation to use.
   */
  protected function saveOperation(): SaveInterface {
    $operationDefinitions = $this->saveOperationManager->getDefinitions();

    // @todo There should only be one save operation in this pipeline.
    // Provide a way to choose it, and the other operations!
    $operationDefinition = reset($operationDefinitions);

    /** @var \Drupal\localgov_publications_importer\Plugin\SaveInterface $operation */
    $operation = $this->saveOperationManager->createInstance($operationDefinition['id']);

    return $operation;
  }

}
