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
    ];

    $form['extract_plugin_configuration'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Extract Plugin Configuration'),
      '#default_value' => json_encode($entity->extract_plugin_configuration),
      '#description' => $this->t('Provide configuration as a JSON array.'),
    ];

    $form['transform'] = [
      '#prefix' => '<div id="tranform-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['transform']['transform_plugins'] = [
      '#type' => 'table',
      '#title' => $this->t('Transform Plugins'),
      '#header' => [$this->t('Plugin ID'), $this->t('Configuration'), $this->t('Operations')],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'transform-weight',
        ],
      ],
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

    foreach ($entity->transform_plugins as $index => $plugin_id) {
      $form['transform']['transform_plugins'][$index]['#attributes']['class'][] = 'draggable';
      $form['transform']['transform_plugins'][$index]['plugin'] = [
        '#prefix' => $transformPluginOptions[$plugin_id],
        '#type' => 'hidden',
        '#default_value' => $plugin_id,
      ];
      $form['transform']['transform_plugins'][$index]['configuration'] = [
        '#type' => 'textarea',
        '#default_value' => json_encode($entity->transform_plugin_configurations[$index] ?? []),
      ];
      $form['transform']['transform_plugins'][$index]['weight'] = [
        '#type' => 'weight',
        '#title_display' => 'invisible',
        '#default_value' => $index,
        '#attributes' => ['class' => ['transform-weight']],
      ];
    }

    // Remove any plugins that are already in use.
    foreach ($entity->transform_plugins as $plugin_id) {
      unset($transformPluginOptions[$plugin_id]);
    }

    if (count($transformPluginOptions) === 0) {
      $disableAddTransform = TRUE;
      $description = 'There are no more transform plugins available.';
    }
    else {
      $disableAddTransform = FALSE;
      $description = 'Please choose a transform plugin to add.';
    }

    $form['transform']['add_transform_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Add Transform Plugin'),
      '#options' => ['-- Choose --'] + $transformPluginOptions,
      '#ajax' => [
        'callback' => '::addTransform',
        'wrapper' => 'tranform-wrapper',
      ],
      '#disabled' => $disableAddTransform,
      '#description' => $description,
    ];

    $form['save_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Save Plugin'),
      '#options' => $this->getSavePluginOptions(),
      '#default_value' => $entity->save_plugin,
    ];

    $form['save_plugin_configuration'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Save Plugin Configuration'),
      '#default_value' => json_encode($entity->save_plugin_configuration),
      '#description' => $this->t('Provide configuration as a JSON array.'),
    ];

    return parent::form($form, $form_state);
  }

  public function addTransform(array &$form, FormStateInterface $form_state) {
    return $form['transform'];
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
