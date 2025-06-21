<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Save;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Save;
use Drupal\localgov_publications_importer\ImportInterface;
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
class Publication extends SavePluginBase {

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
      }
      else {
        $book = [
          'bid' => $rootPage->id(),
          'pid' => $rootPage->id(),
          'weight' => $weight++,
        ];
      }

      /** @var \Drupal\node\NodeInterface $publicationPage */
      $publicationPage = $nodeStorage->create([
        'type' => 'localgov_publication_page',
        'title' => $page->getTitle(),
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

      $publicationPage->get('localgov_publication_content')->setValue([
        [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ],
      ]);

      $publicationPage->save();

      if ($rootPage === NULL) {
        $rootPage = $publicationPage;
      }
    }

    return $rootPage;
  }

}
