<?php


namespace Drupal\localgov_publications_importer;

/**
 * Represents images being imported.
 *
 * @todo: Add an interface.
 */
class Image {

  /**
   * Path to the original xObject data that made up this image.
   *
   * @var string
   */
  protected string $xObjectDataFile;

  /**
   * Get the path to the file containing the xObject data.
   */
  public function getXObjectDataFile(): string {
    return $this->xObjectDataFile;
  }

  /**
   * Set the path to the file containing the xObject data.
   */
  public function setXObjectDataFile(string $xObjectDataFile): void {
    $this->xObjectDataFile = $xObjectDataFile;
  }
}
