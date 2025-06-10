<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Extract;

use Drupal\Component\Plugin\PluginBase;
use Drupal\localgov_publications_importer\Plugin\ExtractInterface;

/**
 * Base class for extract operations.
 */
abstract class ExtractPluginBase extends PluginBase implements ExtractInterface {

  /**
   * Path to the file to import.
   *
   * @var string
   */
  protected string $pathToFile;

  /**
   * {@inheritDoc}
   */
  public function setSource(string $pathToFile): self {
    $this->pathToFile = $pathToFile;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function isConfigurable(): bool {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigurationForm(): array {
    return [];
  }

}
