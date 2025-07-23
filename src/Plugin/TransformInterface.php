<?php

namespace Drupal\localgov_publications_importer\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\localgov_publications_importer\ImportInterface;

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
