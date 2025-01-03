<?php

namespace Drupal\localgov_publications_importer\Plugin;

use Drupal\localgov_publications_importer\Import;

/**
 * Interface for Transform Operation plugins.
 *
 * Accepts an import object and transforms it.
 */
interface TransformInterface {

  /**
   * Transform the content.
   */
  public function transform(Import $import): void;

}
