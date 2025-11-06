<?php

namespace Drupal\localgov_publications_importer\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Import Pipeline configuration entity.
 *
 * @ConfigEntityType(
 *   id = "import_pipeline",
 *   label = @Translation("Import Pipeline"),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\localgov_publications_importer\Form\ImportPipelineForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "list_builder" = "Drupal\localgov_publications_importer\ImportPipelineListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\localgov_publications_importer\ImportPipelineAccessControlHandler",
 *   },
 *   config_prefix = "import_pipeline",
 *   admin_permission = "administer import pipelines",
 *   collection_permission = "view import pipelines",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "extract_plugin",
 *     "extract_plugin_configuration",
 *     "transform_plugins",
 *     "transform_plugin_configurations",
 *     "save_plugin",
 *     "save_plugin_configuration"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/system/import-pipeline/add",
 *     "collection" = "/admin/config/system/import-pipeline",
 *     "edit-form" = "/admin/config/system/import-pipeline/{import_pipeline}",
 *     "delete-form" = "/admin/config/system/import-pipeline/{import_pipeline}/delete"
 *   }
 * )
 */
class ImportPipeline extends ConfigEntityBase {

  /**
   * The pipeline ID.
   *
   * @var string
   */
  public $id;

  /**
   * The pipeline label.
   *
   * @var string
   */
  public $label;

  /**
   * The extract plugin ID.
   *
   * @var string
   */
  public $extract_plugin;

  /**
   * The extract plugin configuration.
   *
   * @var array
   */
  public $extract_plugin_configuration = [];

  /**
   * The transform plugin IDs.
   *
   * @var array
   */
  public $transform_plugins = [];

  /**
   * The transform plugin configurations.
   *
   * @var array
   */
  public $transform_plugin_configurations = [];

  /**
   * The save plugin ID.
   *
   * @var string
   */
  public $save_plugin;

  /**
   * The save plugin configuration.
   *
   * @var array
   */
  public $save_plugin_configuration = [];

}
