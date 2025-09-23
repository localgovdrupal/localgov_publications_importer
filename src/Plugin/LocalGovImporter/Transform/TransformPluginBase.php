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
  public function transform(ImportInterface $import, ?int $page = NULL): void {

    $pages = $import->getPages();

    foreach ($pages as $currentPageNumber => $currentPage) {
      // $page is a limit. If it's not null, only process that page.
      if ($page !== NULL && $page !== $currentPageNumber) {
        continue;
      }

      $this->transformPage($currentPage);
    }

    $import->setPages($pages);
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

  /**
   * {@inheritDoc}
   */
  public function isConfigurable(): bool {
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigurationForm(): array {
    return [];
  }

}
