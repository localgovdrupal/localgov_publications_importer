<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Transform;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\localgov_publications_importer\Attribute\Transform;
use Drupal\localgov_publications_importer\PageInterface;
use Drupal\localgov_publications_importer\Plugin\TransformInterface;

/**
 * Transform operation to change xObject raw data into image files.
 */
#[Transform(
  id: 'transform_images',
  label: new TranslatableMarkup('Images'),
  description: new TranslatableMarkup('Create image files from data in the uploaded PDF.')
)]
class Images extends TransformPluginBase implements TransformInterface {

  /**
   * {@inheritdoc}
   */
  protected function transformPage(PageInterface $page): void {

    /** @var \Drupal\file\FileRepositoryInterface $fileRepository */
    $fileRepository = \Drupal::service('file.repository');

    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
    $fileSystem = \Drupal::service('file_system');
    $publicDir = 'public://localgov_publications_importer';
    $fileSystem->prepareDirectory($publicDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    foreach ($page->getImages() as $image) {

      $filter = $image->getFilter();

      $dataFileName = $image->getxObjectDataFile();

      $fileEntity = NULL;

      if ($filter === 'DCTDecode') {
        // DCTDecode objects are just JPEGs. We can write them to a file and use them.
        $imageFileName = str_replace('temporary://', 'public://', $dataFileName) . '.jpg';
        $fileEntity = $fileRepository->writeData(file_get_contents($dataFileName), $imageFileName);
        $fileSystem->delete($dataFileName);
      }
      else {
        if ($filter === 'FlateDecode') {

          $imageFileName = str_replace('temporary://', 'public://', $dataFileName) . '.png';

          $width = $image->getWidth();
          $height = $image->getHeight();
          $bitsPerComponent = $image->getBitsPerComponent();
          $colorSpace = $image->getColorSpace();

          // FlateDecode objects have already been decompressed for us, but
          // aren't images yet. We need to rebuild the pixel data in the source
          // file into an image and save it.
          $imageFile = $this->createImageFromxObjectData(file_get_contents($image->getxObjectDataFile()), $width, $height, $bitsPerComponent, $colorSpace);
          if ($imageFile instanceof \GdImage) {
            // Write a png into /tmp. We'll read it and save it properly.
            imagepng($imageFile, $dataFileName . '.png');
            $fileEntity = $fileRepository->writeData(file_get_contents($dataFileName . '.png'), $imageFileName);
            $fileSystem->delete($dataFileName);
            $fileSystem->delete($dataFileName . '.png');
          }
          else {
            \Drupal::logger('localgov_pdf_importer')
              ->error("Couldn't import image $width, $height, $bitsPerComponent, $colorSpace");
          }
        }
      }

      if ($fileEntity instanceof FileInterface) {
        $image->setFileId($fileEntity->id());
      }
    }
  }

  /**
   * Create an image from PDF XObject binary data.
   *
   * Based on ISO 32000-1 standard for PDF image objects.
   *
   * @param string $binary_data
   *   The raw binary image data extracted from PDF XObject.
   * @param int $width
   *   The width of the image in pixels.
   * @param int $height
   *   The height of the image in pixels.
   * @param int $bits_per_component
   *   The number of bits per color component (typically 1, 2, 4, 8, or 16).
   * @param string $color_space
   *   The color space (e.g., 'DeviceRGB', 'DeviceGray', 'DeviceCMYK').
   * @param string|null $intent
   *   The rendering intent (optional).
   *
   * @return ?\GdImage
   *   A GD image on success, NULL on failure.
   */
  public function createImageFromxObjectData(string $binary_data, int $width, int $height, int $bits_per_component, string $color_space, ?string $intent = NULL): ?\GdImage {

    // Validate input parameters according to ISO 32000-1.
    if ($width <= 0 || $height <= 0) {
      return NULL;
    }

    if (!in_array($bits_per_component, [1, 2, 4, 8, 16])) {
      return NULL;
    }

    // Create a new image.
    $image = imagecreatetruecolor($width, $height);
    if (!$image) {
      return NULL;
    }

    // Handle different color spaces according to ISO 32000-1
    // Do we need to handle Indexed? See Spec part 8.6.6.3 Indexed Color Spaces.
    switch ($color_space) {
      case 'DeviceRGB':
      case 'RGB':
        return $this->processRgbImage($image, $binary_data, $width, $height, $bits_per_component);

      case 'DeviceGray':
      case 'G':
        return $this->processGrayscaleImage($image, $binary_data, $width, $height, $bits_per_component);

      case 'DeviceCMYK':
      case 'CMYK':
        return $this->processCmykImage($image, $binary_data, $width, $height, $bits_per_component);

      default:
        // For unsupported color spaces, see if RGB works.
        // ICCBased shows up a lot in the Southwark PDFs...
        // See https://blog.idrsolutions.com/what-are-iccbased-colorspaces-in-pdf-files/.
        return $this->processRgbImage($image, $binary_data, $width, $height, $bits_per_component);
    }
  }

  /**
   * Process RGB image data.
   *
   * @param \GdImage $image
   *   The image to work on.
   * @param string $binary_data
   *   The raw binary image data.
   * @param int $width
   *   The width of the image.
   * @param int $height
   *   The height of the image.
   * @param int $bits_per_component
   *   The number of bits per color component.
   *
   * @return ?\GdImage
   *   The processed image or NULL on failure.
   */
  private function processRgbImage(\GdImage $image, string $binary_data, int $width, int $height, int $bits_per_component): ?\GdImage {
    $bytes_per_component = $bits_per_component / 8;
    // RGB has 3 components.
    $components_per_pixel = 3;
    $bytes_per_pixel = $bytes_per_component * $components_per_pixel;

    $data_length = strlen($binary_data);
    $expected_length = $width * $height * $bytes_per_pixel;

    if ($data_length < $expected_length) {
      return NULL;
    }

    $offset = 0;
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        if ($offset + $bytes_per_pixel > $data_length) {
          return $image;
        }

        // Extract RGB values based on bits per component.
        if ($bits_per_component == 8) {
          $r = ord($binary_data[$offset]);
          $g = ord($binary_data[$offset + 1]);
          $b = ord($binary_data[$offset + 2]);
        }
        else {
          // Scale values to 8-bit range.
          $max_value = (1 << $bits_per_component) - 1;
          $r = $this->extractComponentValue($binary_data, $offset, $bits_per_component) * 255 / $max_value;
          $g = $this->extractComponentValue($binary_data, $offset + $bytes_per_component, $bits_per_component) * 255 / $max_value;
          $b = $this->extractComponentValue($binary_data, $offset + 2 * $bytes_per_component, $bits_per_component) * 255 / $max_value;
        }

        $color = imagecolorallocate($image, (int) $r, (int) $g, (int) $b);
        imagesetpixel($image, $x, $y, $color);

        $offset += $bytes_per_pixel;
      }
    }

    return $image;
  }

  /**
   * Process grayscale image data.
   *
   * @param \GdImage $image
   *   The GD image.
   * @param string $binary_data
   *   The raw binary image data.
   * @param int $width
   *   The width of the image.
   * @param int $height
   *   The height of the image.
   * @param int $bits_per_component
   *   The number of bits per color component.
   *
   * @return ?\GdImage
   *   The processed image or NULL on failure.
   */
  private function processGrayscaleImage(\GdImage $image, string $binary_data, int $width, int $height, int $bits_per_component): ?\GdImage {
    $bytes_per_component = $bits_per_component / 8;
    $data_length = strlen($binary_data);
    $expected_length = $width * $height * $bytes_per_component;

    if ($data_length < $expected_length) {
      return NULL;
    }

    $offset = 0;
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        if ($offset + $bytes_per_component > $data_length) {
          return $image;
        }

        // Extract grayscale value.
        if ($bits_per_component == 8) {
          $gray = ord($binary_data[$offset]);
        }
        else {
          // Scale value to 8-bit range.
          $max_value = (1 << $bits_per_component) - 1;
          $gray = $this->extractComponentValue($binary_data, $offset, $bits_per_component) * 255 / $max_value;
        }

        $color = imagecolorallocate($image, (int) $gray, (int) $gray, (int) $gray);
        imagesetpixel($image, $x, $y, $color);

        $offset += $bytes_per_component;
      }
    }

    return $image;
  }

  /**
   * Process CMYK image data.
   *
   * @param \GdImage $image
   *   The GD image.
   * @param string $binary_data
   *   The raw binary image data.
   * @param int $width
   *   The width of the image.
   * @param int $height
   *   The height of the image.
   * @param int $bits_per_component
   *   The number of bits per color component.
   *
   * @return ?\GdImage
   *   The processed image or NULL on failure.
   */
  private function processCmykImage(\GdImage $image, string $binary_data, int $width, int $height, int $bits_per_component): ?\GdImage {
    $bytes_per_component = $bits_per_component / 8;
    // CMYK has 4 components.
    $components_per_pixel = 4;
    $bytes_per_pixel = $bytes_per_component * $components_per_pixel;

    $data_length = strlen($binary_data);
    $expected_length = $width * $height * $bytes_per_pixel;

    if ($data_length < $expected_length) {
      return NULL;
    }

    $offset = 0;
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        if ($offset + $bytes_per_pixel > $data_length) {
          return $image;
        }

        // Extract CMYK values and convert to RGB.
        if ($bits_per_component == 8) {
          $c = ord($binary_data[$offset]) / 255;
          $m = ord($binary_data[$offset + 1]) / 255;
          $y_cmyk = ord($binary_data[$offset + 2]) / 255;
          $k = ord($binary_data[$offset + 3]) / 255;
        }
        else {
          // Scale values to 0-1 range.
          $max_value = (1 << $bits_per_component) - 1;
          $c = $this->extractComponentValue($binary_data, $offset, $bits_per_component) / $max_value;
          $m = $this->extractComponentValue($binary_data, $offset + $bytes_per_component, $bits_per_component) / $max_value;
          $y_cmyk = $this->extractComponentValue($binary_data, $offset + 2 * $bytes_per_component, $bits_per_component) / $max_value;
          $k = $this->extractComponentValue($binary_data, $offset + 3 * $bytes_per_component, $bits_per_component) / $max_value;
        }

        // Convert CMYK to RGB.
        $r = (1 - $c) * (1 - $k) * 255;
        $g = (1 - $m) * (1 - $k) * 255;
        $b = (1 - $y_cmyk) * (1 - $k) * 255;

        $color = imagecolorallocate($image, (int) $r, (int) $g, (int) $b);
        imagesetpixel($image, $x, $y, $color);

        $offset += $bytes_per_pixel;
      }
    }

    return $image;
  }

  /**
   * Extract a component value from binary data.
   *
   * @param string $data
   *   The binary data.
   * @param int $offset
   *   The byte offset.
   * @param int $bits_per_component
   *   The number of bits per component.
   *
   * @return int
   *   The extracted component value.
   */
  private function extractComponentValue(string $data, int $offset, int $bits_per_component): int {
    if ($bits_per_component == 8) {
      return ord($data[$offset] ?? 0);
    }
    elseif ($bits_per_component == 16) {
      $byte1 = ord($data[$offset] ?? 0);
      $byte2 = ord($data[$offset + 1] ?? 0);
      return ($byte1 << 8) | $byte2;
    }
    else {
      // For 1, 2, 4 bits per component, we need bit-level extraction.
      $byte_offset = intval($offset / 8);
      $bit_offset = $offset % 8;

      if (!isset($data[$byte_offset])) {
        return 0;
      }

      $byte = ord($data[$byte_offset]);
      $mask = (1 << $bits_per_component) - 1;
      return ($byte >> (8 - $bit_offset - $bits_per_component)) & $mask;
    }
  }

}
