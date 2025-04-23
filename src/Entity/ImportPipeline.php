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
 *       "add" = "Drupal\\localgov_publications_importer\\Form\\ImportPipelineForm",
 *       "edit" = "Drupal\\localgov_publications_importer\\Form\\ImportPipelineForm",
 *       "delete" = "Drupal\\Core\\Entity\\Form\\ConfigEntityDeleteForm"
 *     },
 *     "list_builder" = "Drupal\\Core\\Config\\Entity\\ConfigEntityListBuilder"
 *   },
 *   config_prefix = "import_pipeline",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
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
