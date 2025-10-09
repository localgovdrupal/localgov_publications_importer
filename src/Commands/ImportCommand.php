<?php

namespace Drupal\localgov_publications_importer\Commands;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\localgov_publications_importer\Batch;
use Drupal\localgov_publications_importer\Entity\Import;
use Drupal\localgov_publications_importer\ImportInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for PDF import.
 */
class ImportCommand extends DrushCommands {

  /**
   * Analyzes a PDF file and prints the object names found in it.
   *
   * @command localgov_publications_importer:import
   * @aliases lpii
   */
  public function import(): void {

    // @todo Move all this into a service.

    // Get the Import entity with the lowest creation time that's still pending.
    $entityTypeManager = \Drupal::entityTypeManager();
    $importStorage = $entityTypeManager->getStorage('import');
    $query = $importStorage->getQuery();

    $result = $query
      ->condition('status', Import::STATUS_PENDING)
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();

    // If there's no imports found to import, do nothing.
    if ($result === []) {
      return;
    }

    /** @var Import $import */
    $import = $importStorage->load(reset($result));

    try {
      // Build a batch to do the import.
      // We could pass the import entity to the importer service here? We don't
      // really need to do a batch, I don't think, but it keeps things consistent
      // with running it in the UI.

      $file = $import->getFile();

      $batch = new BatchBuilder();
      $batch->setTitle('Importing ' . $file->getFilename())
        ->setFinishCallback([Batch::class, 'finished'])
        ->setInitMessage('Commencing')
        ->setProgressMessage('Importing. Elapsed time: @elapsed.')
        ->setErrorMessage('An error occurred during import.');

      $batch->addOperation([Batch::class, 'extract'], [$import]);
      $batch->addOperation([Batch::class, 'transform'], [$import]);
      $batch->addOperation([Batch::class, 'save'], [$import]);

      batch_set($batch->toArray());

      // Mark the import as processing before we begin.
      $import->setStatus(Import::STATUS_PROCESSING);
      $import->save();

      drush_backend_batch_process();
    }
    catch (\Throwable $e) {

      \Drupal::logger('localgov_publications_importer')->error($e->getMessage(), ['exception' => $e]);

      // @todo Log the error.
      $import = $importStorage->load($import->id());
      $import->setStatus(Import::STATUS_FAILED);
      $import->save();
      return;
    }

    // If the process didn't fail, mark it as completed.
    $import = $importStorage->load($import->id());
    $import->setStatus(Import::STATUS_COMPLETED);
    $import->save();
  }

  /**
   * Resets the status of a given import so it can be reimported.
   *
   * @command localgov_publications_importer:reset
   * @param int $import_id
   *   The id of the import to reset
   * @aliases lpir
   */
  public function reset(int $import_id): void {

    // @todo Move all this into a service.

    // Get the Import entity with the lowest creation time that's still pending.
    $entityTypeManager = \Drupal::entityTypeManager();
    $importStorage = $entityTypeManager->getStorage('import');

    /** @var ImportInterface $import */
    $import = $importStorage->load($import_id);

    if ($import instanceof ImportInterface) {
      $import->setStatus(Import::STATUS_PENDING);
      $import->setPages([]);
      $import->setImages([]);
      $import->save();
    }
  }

}
