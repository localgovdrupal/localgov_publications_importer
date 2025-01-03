<?php

namespace Drupal\localgov_publications_importer;

/**
 * Represents a single page of content being imported.
 */
class Page {

  /**
   * Title of the page.
   *
   * @var string
   */
  protected string $title = '';

  /**
   * Content of the page.
   *
   * @var string
   */
  protected string $content = '';

  /**
   * Set the title of the import.
   */
  public function setTitle(string $title): void {
    $this->title = $title;
  }

  /**
   * Get the title of the import.
   */
  public function getTitle(): string {
    return $this->title;
  }

  /**
   * Set the content of the page.
   */
  public function setContent(string $content): void {
    $this->content = $content;
  }

  /**
   * Get the content of the page.
   */
  public function getContent(): string {
    return $this->content;
  }

}
