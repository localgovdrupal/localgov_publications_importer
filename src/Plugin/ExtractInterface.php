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

}
