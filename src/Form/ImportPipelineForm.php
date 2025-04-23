<?php

# src/Form/ImportPipelineForm.php
namespace Drupal\localgov_publications_importer\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityForm;

class ImportPipelineForm extends EntityForm {

  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\localgov_publications_importer\Entity\ImportPipeline $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
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
      '#options' => $this->getPluginOptions('extract'),
      '#default_value' => $entity->extract_plugin,
    ];

    $form['extract_plugin_configuration'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Extract Plugin Configuration'),
      '#default_value' => json_encode($entity->extract_plugin_configuration),
      '#description' => $this->t('Provide configuration as a JSON array.'),
    ];

    $form['transform_plugins'] = [
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

    foreach ($entity->transform_plugins as $index => $plugin_id) {
      $form['transform_plugins'][$index]['#attributes']['class'][] = 'draggable';
      $form['transform_plugins'][$index]['plugin'] = [
        '#type' => 'textfield',
        '#default_value' => $plugin_id,
      ];
      $form['transform_plugins'][$index]['configuration'] = [
        '#type' => 'textarea',
        '#default_value' => json_encode($entity->transform_plugin_configurations[$index] ?? []),
      ];
      $form['transform_plugins'][$index]['weight'] = [
        '#type' => 'weight',
        '#title_display' => 'invisible',
        '#default_value' => $index,
        '#attributes' => ['class' => ['transform-weight']],
      ];
    }

    $form['save_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Save Plugin'),
      '#options' => $this->getPluginOptions('save'),
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

  protected function getPluginOptions($type) {
    // Placeholder for actual plugin discovery logic.
    return [
      'plugin_a' => $this->t('Plugin A'),
      'plugin_b' => $this->t('Plugin B'),
    ];
  }
}

# config/schema/localgov_publications_importer.schema.yml
