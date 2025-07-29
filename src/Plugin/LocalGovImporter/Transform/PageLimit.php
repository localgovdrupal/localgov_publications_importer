<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Transform;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Transform;
use Drupal\localgov_publications_importer\ImportInterface;

/**
 * Transform operation that limits the import to a specified number of pages.
 */
#[Transform(
  id: 'transform_page_limit',
  label: new TranslatableMarkup('Page limit'),
  description: new TranslatableMarkup('Limit the import to a specified number of pages.')
)]
class PageLimit extends TransformPluginBase {

  /**
   * The maximum number of pages to import.
   *
   * @var int
   */
  protected int $limit = 100;

  /**
   * The number of pages to skip before importing.
   *
   * @var int
   */
  protected int $offset = 0;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    if (isset($configuration['limit'])) {
      $this->limit = $configuration['limit'];
    }
    if (isset($configuration['offset'])) {
      $this->offset = $configuration['offset'];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function transform(ImportInterface $import, ?int $page = NULL): void {
    $pageCount = 0;
    $pageNumbers = array_keys($import->getPages());
    foreach ($pageNumbers as $pageNumber) {
      if ($pageNumber < $this->offset) {
        $import->removePage($pageNumber);
        continue;
      }

      if ($pageCount >= $this->limit) {
        $import->removePage($pageNumber);
      }

      $pageCount++;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function isConfigurable(): bool {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigurationForm(): array {
    return [
      'limit' => [
        '#type' => 'textfield',
        '#attributes' => [
          'type' => 'number',
        ],
        '#description' => new TranslatableMarkup("The number of pages the import will be limited to."),
        '#default_value' => $this->limit,
      ],
      'offset' => [
        '#type' => 'textfield',
        '#attributes' => [
          'type' => 'number',
        ],
        '#description' => new TranslatableMarkup("The number of pages to skip before importing."),
        '#default_value' => $this->offset,
      ],
    ];
  }

}
