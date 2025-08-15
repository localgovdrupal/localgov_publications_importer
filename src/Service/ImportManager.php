<?php

namespace Drupal\localgov_publications_importer\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\localgov_publications_importer\Entity\Import;
use Drupal\localgov_publications_importer\ImportInterface;

/**
 * Service for managing import entities.
 */
class ImportManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ImportManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Gets the next import to process.
   *
   * Loads the oldest import entity with pending status, sets it to processing,
   * and returns it.
   *
   * @return \Drupal\localgov_publications_importer\ImportInterface|null
   *   The next import to process, or NULL if none available.
   */
  public function getNextToImport(): ?ImportInterface {
    $storage = $this->entityTypeManager->getStorage('import');
    
    $query = $storage->getQuery()
      ->condition('status', Import::STATUS_PENDING)
      ->sort('created', 'ASC')
      ->range(0, 1)
      ->accessCheck(FALSE);
    
    $ids = $query->execute();
    
    if (empty($ids)) {
      return NULL;
    }
    
    $import_id = reset($ids);
    $import = $storage->load($import_id);
    
    if ($import) {
      $import->setStatus(Import::STATUS_PROCESSING);
      $import->save();
    }
    
    return $import;
  }

}