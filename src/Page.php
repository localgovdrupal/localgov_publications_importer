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
   * Page number.
   *
   * @var int|null
   */
  protected ?int $pageNumber = NULL;

  /**
   * An array of image objects that were found on the page.
   *
   * @var \Drupal\localgov_publications_importer\Image[]
   */
  protected array $images = [];

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

  /**
   * {@inheritdoc}
   */
  public function setPageNumber(int $pageNumber): void {
    $this->pageNumber = $pageNumber;
  }

  /**
   * {@inheritdoc}
   */
  public function getPageNumber(): ?int {
    return $this->pageNumber;
  }

  /**
   * {@inheritdoc}
   */
  public function addImage(Image $image): void {
    $this->images[] = $image;
  }

  /**
   * {@inheritdoc}
   */
  public function getImages(): array {
    return $this->images;
  }

}
