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
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    if (isset($configuration['limit'])) {
      $this->limit = $configuration['limit'];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function transform(ImportInterface $import, ?int $page = NULL): void {
    $pageNumbers = array_keys($import->getPages());
    foreach ($pageNumbers as $pageNumber) {
      if ($pageNumber > $this->limit) {
        $import->removePage($pageNumber);
      }
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
        '#description' => "The number of pages the import will be limited to.",
        '#default_value' => $this->limit,
      ],
    ];
  }

}
