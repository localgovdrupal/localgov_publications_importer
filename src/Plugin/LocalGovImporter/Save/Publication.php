<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Save;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Save;
use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\localgov_publications_importer\Plugin\SaveInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Save operation to save content as an HTML publication.
 */
#[Save(
  id: 'save_publication',
  label: new TranslatableMarkup('Publication'),
  description: new TranslatableMarkup('Save operation that creates an HTML publication.')
)]
class Publication extends PluginBase implements SaveInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * Creates a Publication Save Operation.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public function import(ImportInterface $import): ?NodeInterface {

    $rootPage = NULL;
    $weight = 0;
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    foreach ($import->getPages() as $page) {

      if ($rootPage === NULL) {
        $book = [
          'bid' => 'new',
        ];
        $title = $import->getTitle();
      }
      else {
        $book = [
          'bid' => $rootPage->id(),
          'pid' => $rootPage->id(),
          'weight' => $weight++,
        ];
        $title = 'Page ' . $page->getPageNumber();
      }

      $publicationPage = $nodeStorage->create([
        'type' => 'localgov_publication_page',
        'title' => $title,
        'book' => $book,
      ]);

      // Create the paragraph that holds the text. NB that both the paragraph
      // and the field on it are called 'localgov_text'.
      $paragraph = Paragraph::create([
        'type' => 'localgov_text',
        'localgov_text' => [
          'value' => $page->getContent(),
          'format' => 'wysiwyg',
        ],
      ]);
      $paragraph->save();

      $paragraphList[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];

      $publicationPage->get('localgov_publication_content')->setValue($paragraphList);

      $publicationPage->save();

      if ($rootPage === NULL) {
        $rootPage = $publicationPage;
      }
    }

    return $rootPage;
  }

}
