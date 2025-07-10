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

  protected int $width;
  protected int $height;
  protected int $bitsPerComponent;
  protected string $colorSpace;
  protected string $filter;

  /**
   * @var int
   *   Stores the ID of a file, once this image has been imported as one.
   */
  protected ?int $fileId = NULL;

  /**
   * Get the file ID, if this image has been imported.
   */
  public function getFileId(): ?int {
    return $this->fileId;
  }

  /**
   * Set the file ID of this image.
   */
  public function setFileId(int $fileId): void {
    $this->fileId = $fileId;
  }

  /**
   * @return string
   */
  public function getFilter(): string {
    return $this->filter;
  }

  /**
   * @param string $filter
   */
  public function setFilter(string $filter): void {
    $this->filter = $filter;
  }

  /**
   * @return int
   */
  public function getWidth(): int {
    return $this->width;
  }

  /**
   * @param int $width
   */
  public function setWidth(int $width): void {
    $this->width = $width;
  }

  /**
   * @return int
   */
  public function getHeight(): int {
    return $this->height;
  }

  /**
   * @param int $height
   */
  public function setHeight(int $height): void {
    $this->height = $height;
  }

  /**
   * @return int
   */
  public function getBitsPerComponent(): int {
    return $this->bitsPerComponent;
  }

  /**
   * @param int $bitsPerComponent
   */
  public function setBitsPerComponent(int $bitsPerComponent): void {
    $this->bitsPerComponent = $bitsPerComponent;
  }

  /**
   * @return string
   */
  public function getColorSpace(): string {
    return $this->colorSpace;
  }

  /**
   * @param string $colorSpace
   */
  public function setColorSpace(string $colorSpace): void {
    $this->colorSpace = $colorSpace;
  }

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
