<?php

namespace Drupal\localgov_publications_importer;

/**
 * Represents content being imported.
 */
class Import {

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

  protected array $pages = [];

  public function __construct(string $pathToFile) {
    $this->pathToFile = $pathToFile;
  }

  public function setTitle(string $title) {
    $this->title = $title;
  }

  public function getTitle(): string {
    return $this->title;
  }

  public function setPages(array $pages) {
    $this->pages = $pages;
  }

  public function getPages(): array {
    return $this->pages;
  }

}
