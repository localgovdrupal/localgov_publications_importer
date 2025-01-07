<?php

namespace Drupal\localgov_publications_importer;

/**
 * Interface for content being imported.
 */
interface ImportInterface {

  /**
   * Set the title of the import.
   */
  public function setTitle(string $title): void;

  /**
   * Get the title of the import.
   */
  public function getTitle(): string;

  /**
   * Set the pages of the import.
   *
   * @param \Drupal\localgov_publications_importer\PageInterface[] $pages
   *   Array of pages.
   */
  public function setPages(array $pages): void;

  /**
   * Get the pages of the import.
   *
   * @return \Drupal\localgov_publications_importer\PageInterface[]
   *   Array of pages.
   */
  public function getPages(): array;

  /**
   * Add a new page to this import.
   */
  public function addPage(PageInterface $page): void;

}
