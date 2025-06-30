<?php

namespace Drupal\localgov_publications_importer;

/**
 * Represents content being imported.
 */
class Import implements ImportInterface {

  /**
   * Path to the original file.
   *
   * @var string
   */
  protected string $pathToFile;

  /**
   * Title of the document.
   *
   * @var string
   */
  protected string $title = '';

  /**
   * Array of pages.
   *
   * @var \Drupal\localgov_publications_importer\PageInterface[]
   */
  protected array $pages = [];

  /**
   * Constructor.
   */
  public function __construct(string $pathToFile) {
    $this->pathToFile = $pathToFile;
  }

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
  public function setPages(array $pages): void {
    $this->pages = $pages;
  }

  /**
   * {@inheritdoc}
   */
  public function getPages(): array {
    return $this->pages;
  }

  /**
   * {@inheritdoc}
   */
  public function addPage(PageInterface $page): void {
    $this->pages[] = $page;
  }

  /**
   * {@inheritdoc}
   */
  public function removePage(int $pageNumber): void {
    unset($this->pages[$pageNumber]);
  }
}
