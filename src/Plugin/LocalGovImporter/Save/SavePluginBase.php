<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Save;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\localgov_publications_importer\Plugin\SaveInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for save plugins.
 */
abstract class SavePluginBase extends PluginBase implements SaveInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * Creates a Publication Save Operation.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
