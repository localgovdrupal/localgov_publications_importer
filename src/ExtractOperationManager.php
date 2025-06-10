<?php

namespace Drupal\localgov_publications_importer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\localgov_publications_importer\Attribute\Extract;
use Drupal\localgov_publications_importer\Plugin\ExtractInterface;

/**
 * Manages discovery and instantiation of Extract operations.
 *
 * @method ExtractInterface createInstance(string $plugin_id, array $configuration = []);
 */
class ExtractOperationManager extends DefaultPluginManager {

  /**
   * Constructor.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/LocalGovImporter/Extract',
      $namespaces,
      $module_handler,
      ExtractInterface::class,
      Extract::class
    );

    $this->alterInfo('localgov_importer_extract_operation_info');
    $this->setCacheBackend($cache_backend, 'localgov_importer_extract_operations');
  }

}
