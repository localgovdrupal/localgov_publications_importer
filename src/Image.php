<?php

namespace Drupal\localgov_publications_importer;

/**
 * Represents images being imported.
 *
 * @todo Add an interface.
 */
class Image {

  /**
   * Path to the original xObject data that made up this image.
   *
   * @var string
   */
  protected string $xObjectDataFile;

  /**
   * Image width in pixels.
   *
   * @var int
   */
  protected int $width;

  /**
   * Image height in pixels.
   *
   * @var int
   */
  protected int $height;

  /**
   * Number of bits per color component.
   *
   * @var int
   */
  protected int $bitsPerComponent;

  /**
   * Color space of the image.
   *
   * @var string
   */
  protected string $colorSpace;

  /**
   * Filter used to encode the image.
   *
   * @var string
   */
  protected string $filter;

  /**
   * Stores the ID of a file, once this image has been imported as one.
   *
   * @var int
   */
  protected ?int $fileId = NULL;

  /**
   * Stores the ID of a media entity, once this image has been imported as one.
   *
   * @var int
   */
  protected ?int $mediaId = NULL;

  /**
   * Get the file ID, if this image has been imported.
   *
   * @return int|null
   *   The file ID or NULL if not imported yet.
   */
  public function getFileId(): ?int {
    return $this->fileId;
  }

  /**
   * Set the file ID of this image.
   *
   * @param int $fileId
   *   The file ID to set.
   */
  public function setFileId(int $fileId): void {
    $this->fileId = $fileId;
  }

  /**
   * Get the filter used to encode the image.
   *
   * @return string
   *   The filter name.
   */
  public function getFilter(): string {
    return $this->filter;
  }

  /**
   * Set the filter used to encode the image.
   *
   * @param string $filter
   *   The filter name to set.
   */
  public function setFilter(string $filter): void {
    $this->filter = $filter;
  }

  /**
   * Get the image width in pixels.
   *
   * @return int
   *   The width in pixels.
   */
  public function getWidth(): int {
    return $this->width;
  }

  /**
   * Set the image width in pixels.
   *
   * @param int $width
   *   The width in pixels.
   */
  public function setWidth(int $width): void {
    $this->width = $width;
  }

  /**
   * Get the image height in pixels.
   *
   * @return int
   *   The height in pixels.
   */
  public function getHeight(): int {
    return $this->height;
  }

  /**
   * Set the image height in pixels.
   *
   * @param int $height
   *   The height in pixels.
   */
  public function setHeight(int $height): void {
    $this->height = $height;
  }

  /**
   * Get the number of bits per color component.
   *
   * @return int
   *   The bits per component.
   */
  public function getBitsPerComponent(): int {
    return $this->bitsPerComponent;
  }

  /**
   * Set the number of bits per color component.
   *
   * @param int $bitsPerComponent
   *   The bits per component.
   */
  public function setBitsPerComponent(int $bitsPerComponent): void {
    $this->bitsPerComponent = $bitsPerComponent;
  }

  /**
   * Get the color space of the image.
   *
   * @return string
   *   The color space name.
   */
  public function getColorSpace(): string {
    return $this->colorSpace;
  }

  /**
   * Set the color space of the image.
   *
   * @param string $colorSpace
   *   The color space name.
   */
  public function setColorSpace(string $colorSpace): void {
    $this->colorSpace = $colorSpace;
  }

  /**
   * Get the path to the file containing the xObject data.
   *
   * @return string
   *   The file path.
   */
  public function getxObjectDataFile(): string {
    return $this->xObjectDataFile;
  }

  /**
   * Set the path to the file containing the xObject data.
   */
  public function setxObjectDataFile(string $xObjectDataFile): void {
    $this->xObjectDataFile = $xObjectDataFile;
  }

  /**
   * Set the ID of media using this image.
   */
  public function setMediaId(int $mediaId): void {
    $this->mediaId = $mediaId;
  }

  /**
   * Get the ID of media using this image.
   */
  public function getMediaId(): ?int {
    return $this->mediaId;
  }

  /**
   * Gets a placeholder for the image that we can insert into content.
   *
   * The placeholder will later be replaced with the real image.
   */
  public function toPlaceHolder(): string {
    $bits = explode('/', $this->xObjectDataFile);
    $uuid = array_pop($bits);
    return "<img src=\"{$uuid}.{$this->fileExtension()}\">";
  }

  /**
   * Figure out the file extension this image should have from its filter.
   */
  public function fileExtension(): string {
    if ($this->filter === 'DCTDecode') {
      return 'jpg';
    }
    return 'png';
  }

}
