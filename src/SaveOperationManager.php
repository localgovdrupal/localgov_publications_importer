<?php

namespace Drupal\localgov_publications_importer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\localgov_publications_importer\Attribute\Save;
use Drupal\localgov_publications_importer\Plugin\SaveInterface;

/**
 * Manages discovery and instantiation of Save operations.
 *
 * @method SaveInterface createInstance(string $plugin_id, array $configuration = []);
 */
class SaveOperationManager extends DefaultPluginManager {

  /**
   * Constructor.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/LocalGovImporter/Save',
      $namespaces,
      $module_handler,
      SaveInterface::class,
      Save::class
    );

    $this->alterInfo('localgov_importer_save_operation_info');
    $this->setCacheBackend($cache_backend, 'localgov_importer_save_operations');
  }

}
