<?php

namespace Drupal\localgov_publications_importer\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\localgov_publications_importer\ExtractOperationManager;
use Drupal\localgov_publications_importer\SaveOperationManager;
use Drupal\localgov_publications_importer\TransformOperationManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for Import Pipeline entity.
 */
class ImportPipelineForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(ExtractOperationManager::class),
      $container->get(TransformOperationManager::class),
      $container->get(SaveOperationManager::class)
    );
  }

  public function __construct(
    protected ExtractOperationManager $extractOperationManager,
    protected TransformOperationManager $transformOperationManager,
    protected SaveOperationManager $saveOperationManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\localgov_publications_importer\Entity\ImportPipeline $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pipeline name'),
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\\localgov_publications_importer\\Entity\\ImportPipeline::load',
      ],
    ];

    $form['extract_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Extract Plugin'),
      '#options' => $this->getExtractPluginOptions(),
      '#default_value' => $entity->extract_plugin,
      '#ajax' => [
        'callback' => '::showExtractPluginConfiguration',
        'wrapper' => 'extract-plugin-configuration',
      ],
    ];

    $showExtractPluginConfiguration = FALSE;
    $currentExtractPluginId = $form_state->getValue('extract_plugin');
    if ($currentExtractPluginId) {
      $extractPlugin = $this->extractOperationManager->createInstance($currentExtractPluginId);
      $showExtractPluginConfiguration = $extractPlugin->isConfigurable();
    }

    $form['extract_plugin_configuration'] = [
      '#type' => $showExtractPluginConfiguration ? 'textarea' : 'hidden',
      '#title' => $this->t('Extract Plugin Configuration'),
      '#default_value' => json_encode($entity->extract_plugin_configuration),
      '#description' => $this->t('Provide configuration as a JSON array.'),
      '#prefix' => '<div id="extract-plugin-configuration">',
      '#suffix' => '</div>',
    ];

    $form['transform'] = [
      '#prefix' => '<div id="transform-wrapper">',
      '#suffix' => '</div>',
    ];

    $transformPluginOptions = $this->getTransformPluginOptions();
    $selectedTransformPluginsFromForm = $form_state->getValue('selected_transform_plugin', NULL);

    if ($selectedTransformPluginsFromForm === NULL) {
      // Nothing chosen in the form yet - use the entity values.
      $selectedTransformPlugins = array_filter($entity->transform_plugins);
    }
    else {
      // Existing values have been changed. Use the changed values.
      $selectedTransformPlugins = array_filter($selectedTransformPluginsFromForm);
    }

    $form['transform']['selected_transform_plugin'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Choose transform plugins'),
      '#options' => $transformPluginOptions,
      '#default_value' => $selectedTransformPlugins,
      '#ajax' => [
        'callback' => '::addTransform',
        'wrapper' => 'transform-wrapper',
      ],
    ];

    if (count($selectedTransformPlugins) > 0) {
      $form['transform']['transform_plugins'] = [
        '#type' => 'table',
        '#title' => $this->t('Transform Plugins'),
        '#header' => [
          $this->t('Plugin ID'),
          $this->t('Configuration'),
          $this->t('Operations'),
        ],
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'transform-weight',
          ],
        ],
      ];
    }

    foreach ($selectedTransformPlugins as $index => $pluginId) {

      $pluginConfiguration = $entity->transform_plugin_configurations[$index] ?? [];

      $transformPlugin = $this->transformOperationManager->createInstance($pluginId, $pluginConfiguration);

      $form['transform']['transform_plugins'][$index]['#attributes']['class'][] = 'draggable';
      $form['transform']['transform_plugins'][$index]['plugin'] = [
        '#prefix' => $transformPluginOptions[$pluginId],
        '#type' => 'hidden',
        '#default_value' => $pluginId,
      ];
      if ($transformPlugin->isConfigurable()) {
        $form['transform']['transform_plugins'][$index]['configuration'] = $transformPlugin->getConfigurationForm();
      }
      else {
        $form['transform']['transform_plugins'][$index]['configuration'] = [
          '#markup' => 'Not configurable',
        ];
      }
      $form['transform']['transform_plugins'][$index]['weight'] = [
        '#type' => 'weight',
        '#title_display' => 'invisible',
        '#default_value' => $index,
        '#attributes' => ['class' => ['transform-weight']],
      ];
    }

    $form['save_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Save Plugin'),
      '#options' => $this->getSavePluginOptions(),
      '#default_value' => $entity->save_plugin,
    ];

    $showSavePluginConfiguration = FALSE;
    $currentSavePluginId = $form_state->getValue('save_plugin');
    if ($currentSavePluginId) {
      $savePlugin = $this->saveOperationManager->createInstance($currentSavePluginId);
      $showSavePluginConfiguration = $savePlugin->isConfigurable();
    }

    $form['save_plugin_configuration'] = [
      '#type' => $showSavePluginConfiguration ? 'textarea' : 'hidden',
      '#title' => $this->t('Save Plugin Configuration'),
      '#default_value' => json_encode($entity->save_plugin_configuration),
      '#description' => $this->t('Provide configuration as a JSON array.'),
      '#prefix' => '<div id="save-plugin-configuration">',
      '#suffix' => '</div>',
    ];

    return parent::form($form, $form_state);
  }

  /**
   * AJAX callback for the extract plugin configuration.
   */
  public function showExtractPluginConfiguration(array &$form, FormStateInterface $form_state): array {
    return $form['extract_plugin_configuration'];
  }

  /**
   * AJAX callback for the transform plugins.
   */
  public function addTransform(array &$form, FormStateInterface $form_state): array {
    return $form['transform'];
  }

  /**
   * AJAX callback for the save plugin configuration.
   */
  public function showSavePluginConfiguration(array &$form, FormStateInterface $form_state): array {
    return $form['save_plugin_configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {

    /** @var \Drupal\localgov_publications_importer\Entity\ImportPipeline $entity */
    $entity = $this->entity;

    $entity->extract_plugin = $form_state->getValue('extract_plugin');
    $extractPlugin = $this->extractOperationManager->createInstance($entity->extract_plugin);
    if ($extractPlugin->isConfigurable()) {
      $entity->extract_plugin_configuration = $form_state->getValue('extract_plugin_configuration');
    }
    else {
      $entity->extract_plugin_configuration = [];
    }

    $entity->save_plugin = $form_state->getValue('save_plugin');
    $savePlugin = $this->saveOperationManager->createInstance($entity->save_plugin);
    if ($savePlugin->isConfigurable()) {
      $entity->save_plugin_configuration = $form_state->getValue('save_plugin_configuration');
    }
    else {
      $entity->save_plugin_configuration = [];
    }

    $plugin_rows = $form_state->getValue('transform_plugins');
    $entity->transform_plugins = [];
    $entity->transform_plugin_configurations = [];
    foreach ($plugin_rows as $row) {

      // $row['weight'] is a thing too. We should use that to sort the plugins.
      $entity->transform_plugins[] = $row['plugin'];
      $entity->transform_plugin_configurations[] = $row['configuration'] ?? [];
    }

    $return = $entity->isNew() ? SAVED_NEW : SAVED_UPDATED;

    $entity->save();
    $form_state->setRedirect('entity.import_pipeline.collection');

    return $return;
  }

  /**
   * Get extract plugin options.
   *
   * @return array
   *   Array of extract plugin options.
   */
  protected function getExtractPluginOptions(): array {
    return $this->getPluginOptions($this->extractOperationManager->getDefinitions());
  }

  /**
   * Get transform plugin options.
   *
   * @return array
   *   Array of transform plugin options.
   */
  protected function getTransformPluginOptions(): array {
    return $this->getPluginOptions($this->transformOperationManager->getDefinitions());
  }

  /**
   * Get save plugin options.
   *
   * @return array
   *   Array of save plugin options.
   */
  protected function getSavePluginOptions(): array {
    return $this->getPluginOptions($this->saveOperationManager->getDefinitions());
  }

  /**
   * Get plugin options from plugin definitions.
   *
   * @param array $pluginDefinitions
   *   Plugin definitions array.
   *
   * @return array
   *   Array of plugin options, id => label.
   */
  protected function getPluginOptions(array $pluginDefinitions): array {
    $options = [];
    foreach ($pluginDefinitions as $id => $pluginDefinition) {
      $options[$id] = $pluginDefinition['label'];
    }
    return $options;
  }

}
