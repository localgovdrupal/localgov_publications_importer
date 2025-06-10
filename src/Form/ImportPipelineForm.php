<?php

namespace Drupal\localgov_publications_importer\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\localgov_publications_importer\ExtractOperationManager;
use Drupal\localgov_publications_importer\TransformOperationManager;
use Drupal\localgov_publications_importer\SaveOperationManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ImportPipelineForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
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

  public function form(array $form, FormStateInterface $form_state) {
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

    $addTransformPlugin = $form_state->getValue('add_transform_plugin');
    if ($addTransformPlugin !== NULL && $addTransformPlugin !== '0') {
      // This is an empty string sometimes for some reason...
      if (!is_array($entity->transform_plugins)) {
        $entity->transform_plugins = [];
      }
      $entity->transform_plugins[] = $addTransformPlugin;
    }

    $form['transform']['selected_transform_plugin'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Choose transform plugins'),
      '#options' => $transformPluginOptions,
      '#ajax' => [
        'callback' => '::addTransform',
        'wrapper' => 'transform-wrapper',
      ],
    ];

    $selectedTransformPlugins = array_filter($form_state->getValue('selected_transform_plugin', []));

    if (count($selectedTransformPlugins) > 0) {
      $form['transform']['transform_plugins'] = [
        '#type' => 'table',
        '#title' => $this->t('Transform Plugins'),
        '#header' => [
          $this->t('Plugin ID'),
          $this->t('Configuration'),
          $this->t('Operations')
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

    foreach ($selectedTransformPlugins as $index => $plugin_id) {

      $transformPlugin = $this->transformOperationManager->createInstance($plugin_id);

      $form['transform']['transform_plugins'][$index]['#attributes']['class'][] = 'draggable';
      $form['transform']['transform_plugins'][$index]['plugin'] = [
        '#prefix' => $transformPluginOptions[$plugin_id],
        '#type' => 'hidden',
        '#default_value' => $plugin_id,
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
  public function showExtractPluginConfiguration(array &$form, FormStateInterface $form_state) {
    return $form['extract_plugin_configuration'];
  }

  /**
   * AJAX callback for the transform plugins.
   */
  public function addTransform(array &$form, FormStateInterface $form_state) {
    return $form['transform'];
  }

  /**
   * AJAX callback for the save plugin configuration.
   */
  public function showSavePluginConfiguration(array &$form, FormStateInterface $form_state) {
    return $form['save_plugin_configuration'];
  }

  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $entity->extract_plugin_configuration = json_decode($form_state->getValue('extract_plugin_configuration'), TRUE);
    $entity->save_plugin_configuration = json_decode($form_state->getValue('save_plugin_configuration'), TRUE);

    $plugin_rows = $form_state->getValue('transform_plugins');
    $entity->transform_plugins = [];
    $entity->transform_plugin_configurations = [];
    foreach ($plugin_rows as $row) {
      $entity->transform_plugins[] = $row['plugin'];
      $entity->transform_plugin_configurations[] = json_decode($row['configuration'], TRUE);
    }

    $entity->save();
    $form_state->setRedirect('entity.import_pipeline.collection');
  }

  protected function getExtractPluginOptions() {
    return $this->getPluginOptions($this->extractOperationManager->getDefinitions());
  }

  protected function getTransformPluginOptions() {
    return $this->getPluginOptions($this->transformOperationManager->getDefinitions());
  }

  protected function getSavePluginOptions() {
    return $this->getPluginOptions($this->saveOperationManager->getDefinitions());
  }

  protected function getPluginOptions($pluginDefinitions) {
    $options = [];
    foreach ($pluginDefinitions as $id => $pluginDefinition) {
      $options[$id] = $pluginDefinition['label'];
    }
    return $options;
  }
}
