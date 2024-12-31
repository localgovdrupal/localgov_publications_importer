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

  /**
   * Array of pages.
   *
   * @var array
   */
  protected array $pages = [];

  /**
   * Constructor.
   */
  public function __construct(string $pathToFile) {
    $this->pathToFile = $pathToFile;
  }

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
   * Set the pages of the import.
   */
  public function setPages(array $pages): void {
    $this->pages = $pages;
  }

  /**
   * Get the pages of the import.
   */
  public function getPages(): array {
    return $this->pages;
  }

}
