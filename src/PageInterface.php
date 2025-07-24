<?php

namespace Drupal\localgov_publications_importer;

/**
 * Interface for a single page of content being imported.
 */
interface PageInterface {

  /**
   * Set the title of the page.
   */
  public function setTitle(string $title): void;

  /**
   * Get the title of the page.
   */
  public function getTitle(): string;

  /**
   * Set the content of the page.
   */
  public function setContent(string $content): void;

  /**
   * Get the content of the page.
   */
  public function getContent(): string;

  /**
   * Set the poge number.
   */
  public function setPageNumber(int $pageNumber): void;

  /**
   * Get the poge number.
   */
  public function getPageNumber(): ?int;

  /**
   * Add an image to the page.
   */
  public function addImage(Image $image): void;

  /**
   * Get the images that have been added to the page.
   *
   * @return Image[]
   *   The images that have been added to this page.
   */
  public function getImages(): array;

}
