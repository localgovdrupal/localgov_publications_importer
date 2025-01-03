<?php

namespace Drupal\localgov_publications_importer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\localgov_publications_importer\Attribute\Transform;
use Drupal\localgov_publications_importer\Plugin\TransformInterface;

/**
 * Manages discovery and instantiation of Transform operations.
 */
class TransformOperationManager extends DefaultPluginManager {

  /**
   * Constructor.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/LocalGovImporter/Transform',
      $namespaces,
      $module_handler,
      TransformInterface::class,
      Transform::class
    );

    $this->alterInfo('localgov_importer_transform_operation_info');
    $this->setCacheBackend($cache_backend, 'localgov_importer_transform_operations');
  }

}
