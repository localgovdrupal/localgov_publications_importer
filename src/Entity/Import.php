<?php

namespace Drupal\localgov_publications_importer\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\file\Entity\File;
use Drupal\localgov_publications_importer\Image;
use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\localgov_publications_importer\Page;
use Drupal\localgov_publications_importer\PageInterface;
use Drupal\node\NodeInterface;

/**
 * Defines the Import entity.
 *
 * @ContentEntityType(
 *   id = "import",
 *   label = @Translation("Import"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "storage_schema" = "Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\localgov_publications_importer\ImportAccessControlHandler",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *   },
 *   base_table = "import",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "delete-form" = "/admin/content/imports/{import}/delete"
 *   }
 * )
 */
class Import extends ContentEntityBase implements ImportInterface {

  /**
   * Status constants.
   */
  const STATUS_PENDING = 0;
  const STATUS_PROCESSING = 1;
  const STATUS_COMPLETED = 2;
  const STATUS_FAILED = 3;

  /**
   * {@inheritdoc}
   */
  public function setTitle(string $title): void {
    $this->set('title', $title);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return $this->get('title')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setPages(array $pages): void {
    $this->set('pages', serialize($pages));
  }

  /**
   * {@inheritdoc}
   */
  public function getPages(): array {
    $serialized = $this->get('pages')->value;
    return $serialized ? unserialize($serialized, ['allowed_classes' => [Page::class, Image::class]]) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function addPage(PageInterface $page): void {
    $pages = $this->getPages();
    $pages[] = $page;
    $this->setPages($pages);
  }

  /**
   * {@inheritdoc}
   */
  public function removePage(int $pageNumber): void {
    $pages = $this->getPages();
    unset($pages[$pageNumber]);
    $this->setPages($pages);
  }

  /**
   * {@inheritdoc}
   */
  public function getPipeline(): string {
    return $this->get('pipeline')->value ?? '';
  }

  /**
   * Set the import pipeline ID to use.
   */
  public function setPipeline(string $pipeline): void {
    $this->set('pipeline', $pipeline);
  }

  /**
   * Get the status of the import.
   */
  public function getStatus(): int {
    return $this->get('status')->value ?? self::STATUS_PENDING;
  }

  /**
   * Set the status of the import.
   */
  public function setStatus(int $status): void {
    $this->set('status', $status);
  }

  /**
   * Set the result of the import.
   */
  public function setResult(NodeInterface $node) {
    $this->get('result')->entity = $node;
  }

  /**
   * Get the created timestamp.
   */
  public function getCreated(): int {
    return $this->get('created')->value;
  }

  /**
   * Set the created timestamp.
   */
  public function setCreated(int $timestamp): void {
    $this->set('created', $timestamp);
  }

  /**
   * Get the creator user.
   */
  public function getCreator(): ?UserInterface {
    return $this->get('creator')->entity;
  }

  /**
   * Set the creator user.
   */
  public function setCreator(UserInterface $user): void {
    $this->set('creator', $user);
  }

  /**
   * Get the file.
   */
  public function getFile(): ?File {
    $fileId = $this->get('file')->target_id;
    if ($fileId) {
      return File::load($fileId);
    }
    return NULL;
  }

  /**
   * Set the file.
   */
  public function setFile(File $file): void {
    $this->set('file', $file);
  }

  /**
   * Get the images.
   *
   * @return \Drupal\file\Entity\File[]
   *   Array of files, which are images.
   */
  public function getImages(): array {
    $images = [];
    foreach ($this->get('images') as $image) {
      $images[] = File::load($image->target_id);
    }
    return $images;
  }

  /**
   * Set the images.
   */
  public function setImages($values): void {
    $this->set('images', $values);
  }

  /**
   * Add an image.
   */
  public function addImage(File $file): void {
    $this->get('images')->appendItem($file);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['file'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('File'))
      ->setDescription(t('The file being imported.'))
      ->setSetting('target_type', 'file')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['result'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Result'))
      ->setDescription(t('The result of the import.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('Title of the document.'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['pipeline'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Pipeline'))
      ->setDescription(t('Import pipeline to use.'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['pages'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Pages'))
      ->setDescription(t('Serialized array of pages.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status'))
      ->setDescription(t('The status of the import.'))
      ->setDefaultValue(self::STATUS_PENDING)
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['creator'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Creator'))
      ->setDescription(t('The user who created this import.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['images'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Images'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDescription(t('Images extracted from the imported file.'))
      ->setSetting('target_type', 'file')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the import was created.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
