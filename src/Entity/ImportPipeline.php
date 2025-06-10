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
 *       "delete" = "Drupal\Core\Entity\Form\ConfigEntityDeleteForm"
 *     },
 *     "list_builder" = "Drupal\Core\Config\Entity\ConfigEntityListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "import_pipeline",
 *   admin_permission = "administer site configuration",
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

  /** @var string */
  public $id;

  /** @var string */
  public $label;

  /** @var string */
  public $extract_plugin;

  /** @var array */
  public $extract_plugin_configuration = [];

  /** @var array */
  public $transform_plugins = [];

  /** @var array */
  public $transform_plugin_configurations = [];

  /** @var string */
  public $save_plugin;

  /** @var array */
  public $save_plugin_configuration = [];
}
