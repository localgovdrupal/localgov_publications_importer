<?php

namespace Drupal\localgov_publications_importer\Plugin;

use Drupal\localgov_publications_importer\Import;
use Drupal\node\NodeInterface;

/**
 * Interface for Save Operation plugins.
 *
 * Accepts an import object and saves it to the database.
 */
interface SaveInterface {

  /**
   * Import the content.
   *
   * Returns a Node on success, null otherwise.
   */
  public function import(Import $import): ?NodeInterface;

}
