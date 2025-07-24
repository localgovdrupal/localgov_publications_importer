<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Save;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Save;
use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\media\Entity\Media;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;

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

      $paragraphs = [];

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
      $paragraphs[] = $paragraph;

      foreach ($page->getImages() as $image) {

        // Skip any images that didn't result in usable files.
        if (is_null($image->getFileId())) {
          continue;
        }

        $media = Media::create([
          'name' => '',
          'bundle' => 'image',
          'uid' => 1,
          'langcode' => 'en',
          'status' => 1,
          'field_media_image' => [
            'target_id' => $image->getFileId(),
            'alt' => 'Alt',
            'title' => 'Title',
          ],
        ]);
        $media->save();

        $paragraph = Paragraph::create([
          'type' => 'localgov_image',
          'localgov_image' => [
            'target_id' => $media->id(),
          ],
          'localgov_caption' => [
            // @todo Something meaningful.
            'value' => '',
          ],
        ]);
        $paragraph->save();
        $paragraphs[] = $paragraph;
      }
      $pageContent = $publicationPage->get('localgov_publication_content');
      foreach ($paragraphs as $paragraph) {
        $pageContent[] = [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ];
      }

      $publicationPage->save();

      if ($rootPage === NULL) {
        $rootPage = $publicationPage;
      }
    }

    return $rootPage;
  }

}
