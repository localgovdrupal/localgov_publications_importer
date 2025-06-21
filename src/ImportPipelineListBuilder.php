<?php

namespace Drupal\localgov_publications_importer;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Import Pipeline entities.
 */
class ImportPipelineListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['extract_plugin'] = $this->t('Extract Plugin');
    $header['save_plugin'] = $this->t('Save Plugin');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['extract_plugin'] = $entity->extract_plugin ?: $this->t('Not configured');
    $row['save_plugin'] = $entity->save_plugin ?: $this->t('Not configured');
    return $row + parent::buildRow($entity);
  }

  public function getTitle() {
    return 'Import Pipelines';
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['intro'] = [
      '#prefix' => '<p>',
      '#markup' => 'Import pipelines are used by the content importer. You can add multiple pipelines to import content in different ways. For example: To different content types, Using different AI prompts, or using your own custom plugins.',
      '#suffix' => '</p>',
      '#weight' => -1,
    ];
    return $build;
  }
}
