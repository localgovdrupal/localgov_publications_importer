<?php

namespace Drupal\localgov_publications_importer\Plugin;

use Drupal\localgov_publications_importer\ImportInterface;
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
  public function import(ImportInterface $import): ?NodeInterface;

  /**
   * Is this plugin configurable?
   *
   * If it's not, we won't show fields to configure it in the pipeline form.
   *
   * @return bool
   *   True if configurable. False otherwise.
   */
  public function isConfigurable(): bool;

  /**
   * Get the configuration form fields for this plugin.
   *
   * @return array
   *   Form API fields.
   */
  public function getConfigurationForm(): array;

}
