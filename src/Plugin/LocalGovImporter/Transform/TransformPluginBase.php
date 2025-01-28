<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Transform;

use Drupal\Component\Plugin\PluginBase;
use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\localgov_publications_importer\PageInterface;
use Drupal\localgov_publications_importer\Plugin\TransformInterface;

/**
 * Base transform plugin.
 *
 * This lets transform plugin authors implement
 * transformPage() or transformContent() instead of transform() if they want
 * their code to act on a single page's content.
 */
abstract class TransformPluginBase extends PluginBase implements TransformInterface {

  /**
   * {@inheritDoc}
   */
  public function transform(ImportInterface $import): void {

    foreach ($import->getPages() as $page) {
      $this->transformPage($page);
    }
  }

  /**
   * Transforms a single page.
   *
   * If you just want to act on a single page, implement this in your plugin.
   */
  protected function transformPage(PageInterface $page): void {
    $page->setContent($this->transformContent($page->getContent()));
  }

  /**
   * Transforms the content of a single page.
   *
   * If you just want to alter content, implement this in your plugin.
   */
  protected function transformContent(string $content): string {
    return $content;
  }

}
