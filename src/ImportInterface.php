<?php

namespace Drupal\localgov_publications_importer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\file\Entity\File;

/**
 * Interface for content being imported.
 */
interface ImportInterface extends EntityInterface {

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

  /**
   * Remove a page from this import.
   */
  public function removePage(int $pageNumber): void;

  /**
   * Get the status of the import.
   */
  public function getStatus(): int;

  /**
   * Set the status of the import.
   */
  public function setStatus(int $status): void;

  /**
   * Get the created timestamp.
   */
  public function getCreated(): int;

  /**
   * Set the created timestamp.
   */
  public function setCreated(int $timestamp): void;

  /**
   * Get the import pipeline ID to use.
   */
  public function getPipeline(): string;

  /**
   * Get the file.
   */
  public function getFile(): ?File;

}
