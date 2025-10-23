<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Save;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Save;
use Drupal\localgov_publications_importer\ImportInterface;
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

      // Create a layout paragraph to hold our text.
      $layoutParagraph = Paragraph::create([
        'type' => 'localgov_page_section',
      ]);
      $layoutParagraph->setBehaviorSettings('layout_paragraphs', [
        'region' => '',
        'parent_uuid' => '',
        'layout' => 'layout_onecol',
        'config' => ['label' => ''],
      ]);
      $layoutParagraph->save();

      $paragraphs = [];
      $paragraphs[] = $layoutParagraph;

      /** @var \Drupal\node\NodeInterface $publicationPage */
      $publicationPage = $nodeStorage->create([
        'type' => 'localgov_publication_page',
        'title' => $page->getTitle(),
        'book' => $book,
      ]);

      $pageContent = $page->getContent();
      $replacements = [];

      if (preg_match_all("#<img src=\"([^\"]+\\.[a-z]+)\">#", $pageContent, $matches)) {

        $replacements = array_merge($replacements, $matches[0]);

        // Loop over all the matches and images to find the right image for
        // each matched pattern.
        foreach ($matches[1] as $imageFileName) {
          foreach ($import->getImages() as $image) {
            if ($image->getFilename() === $imageFileName) {

              $mediaEntities = $this->entityTypeManager->getStorage('media')->loadByProperties(['field_media_image' => $image->id()]);

              if ($mediaEntities === []) {
                continue;
              }

              $media = reset($mediaEntities);

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
              $paragraph->setBehaviorSettings('layout_paragraphs', [
                'region' => 'content',
                'parent_uuid' => $layoutParagraph->uuid(),
                'layout' => '',
                'config' => [],
              ]);
              $paragraph->save();
              $paragraphs[] = $paragraph;
            }
          }
        }
      }

      // Strip out the image tags we put in.
      $pageContent = str_replace($replacements, '', $pageContent);

      // Create the paragraph that holds the text. NB that both the paragraph
      // and the field on it are called 'localgov_text'.
      $paragraph = Paragraph::create([
        'type' => 'localgov_text',
        'localgov_text' => [
          'value' => $pageContent,
          'format' => 'wysiwyg',
        ],
      ]);
      $paragraph->setBehaviorSettings('layout_paragraphs', [
        'region' => 'content',
        'parent_uuid' => $layoutParagraph->uuid(),
        'layout' => '',
        'config' => [],
      ]);
      $paragraph->save();
      $paragraphs[] = $paragraph;

      $publicationContentField = $publicationPage->get('localgov_publication_content');

      foreach ($paragraphs as $paragraph) {
        $publicationContentField[] = [
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
