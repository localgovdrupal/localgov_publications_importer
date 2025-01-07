<?php

namespace Drupal\localgov_publications_importer;

/**
 * Represents a single page of content being imported.
 */
class Page implements PageInterface {

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
   * {@inheritdoc}
   */
  public function setTitle(string $title): void {
    $this->title = $title;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function setContent(string $content): void {
    $this->content = $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getContent(): string {
    return $this->content;
  }

}
