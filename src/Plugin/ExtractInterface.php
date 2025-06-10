<?php

namespace Drupal\localgov_publications_importer\Plugin;

use Drupal\localgov_publications_importer\ImportInterface;

/**
 * Interface for Extract Operation plugins.
 *
 * Accepts a file, and returns an Import object.
 */
interface ExtractInterface {

  /**
   * Accepts a file.
   *
   * @param string $pathToFile
   *   Path to the file.
   *
   * @return $this
   */
  public function setSource(string $pathToFile): self;

  /**
   * Creates a new Import object with the extracted content.
   *
   * @return ?\Drupal\localgov_publications_importer\ImportInterface
   *   The new import.
   */
  public function getImport(): ?ImportInterface;

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
