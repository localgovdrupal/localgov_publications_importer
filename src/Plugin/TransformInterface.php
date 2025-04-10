<?php

namespace Drupal\localgov_publications_importer\Plugin;

use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for Transform Operation plugins.
 *
 * Accepts an import object and transforms it.
 */
interface TransformInterface extends PluginInspectionInterface {

  /**
   * Transform the content.
   */
  public function transform(ImportInterface $import, ?int $page = NULL): void;

  /**
   * The order this plugin should run in.
   *
   * Low numbers will be run before higher numbers.
   */
  public function order(): int;

}
