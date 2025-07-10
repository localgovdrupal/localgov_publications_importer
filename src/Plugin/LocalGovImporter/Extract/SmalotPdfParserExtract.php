<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Extract;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Extract;
use Drupal\localgov_publications_importer\Import;
use Drupal\localgov_publications_importer\ImportInterface;
use Drupal\localgov_publications_importer\Page;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Element\ElementName;
use Smalot\PdfParser\Parser as PdfParser;
use Smalot\PdfParser\XObject\Image;

/**
 * Extract operation that uses Smalot/pdfparser.
 */
#[Extract(
  id: 'smalot_pdfparser',
  label: new TranslatableMarkup('Smalot extract'),
  description: new TranslatableMarkup('Extract operation that uses Smalot/pdfparser')
)]
class SmalotPdfParserExtract extends ExtractPluginBase {

  /**
   * {@inheritDoc}
   */
  public function getImport(): ?ImportInterface {

    $config = new PdfParserConfig();
    // An empty string can prevent words from breaking up.
    $config->setHorizontalOffset('');

    // Parse PDF file and build necessary objects.
    $parser = new PdfParser([], $config);
    $pdf = $parser->parseFile($this->pathToFile);

    $images = $pdf->getObjectsByType('XObject', 'Image');
    $index = 0;
    foreach ($images as $image) {

      // @see https://blog.idrsolutions.com/how-to-extract-raw-jpeg-images-from-a-pdf-file/.


      // Values are FlateDecode / DCTDecode.
      // DCTDecode seems to be a jpeg that we can just write to a file.
      // FlateDecode needs running through?
      //   gzuncompress - no
      //   zlib_decode - no

      /**
       * The rest of the data we need to rebuild the image is here too:
       * Example values given.
       *
       * BitsPerComponent 8
       * ColorSpace DeviceGray
       * Filter DCTDecode
       * Height 643.0
       * Intent RelativeColorimetric
       * Length 3568.0
       * Metadata 1218_0
       * Name X
       * Subtype Image
       * Type XObject
       * Width 1173.0
       */

      $filter = $image->getHeader()->get('Filter')->getContent();

      if ($filter === 'DCTDecode') {
        file_put_contents(DRUPAL_ROOT . '/' . $index . '.jpg', $image->getContent());
      }
      else if ($filter === 'FlateDecode') {
        // The first 2 bytes of this binary file are the Adler 32 checksum.
        // We must remove this before trying to decompress it.
        //$content = $image->getContent();
        //$content = substr($content, 2);
        //file_put_contents(DRUPAL_ROOT . '/' . $index . '.something', zlib_decode($content));

        $width = (int) $image->getHeader()->get('Width')->getContent();
        $height = (int) $image->getHeader()->get('Height')->getContent();
        $bits_per_component = (int) $image->getHeader()->get('BitsPerComponent')->getContent();

        // $color_space = $image->getHeader()->get('ColorSpace')->getContent();
        // We need to get the image color space like this for some reason.
        $elements = $image->getHeader()->getElements();
        if (isset($elements['ColorSpace'])) {
          if ($elements['ColorSpace'] instanceof ElementName) {
            $color_space = $elements['ColorSpace']->getContent();
          }
          else {
            // This is when it's a pdfObject??
            $color_space = $elements['ColorSpace']->getHeader()
              ->get(0)
              ->getContent();
          }
        }
        else {
          $color_space = '';
        }

        /*
         *
/** @var \Drupal\file\FileRepositoryInterface $fileRepository * /
        $fileRepository = \Drupal::service('file.repository');
        $fileRepository->writeData($data, "public://my-dir/MY_FILE.txt", FileSystemInterface::EXISTS_REPLACE);
         *
         */



        $image = $this->createImageFromXObjectData($image->getContent(), $width, $height, $bits_per_component, $color_space);
        if ($image instanceof \GdImage) {
          imagepng($image, DRUPAL_ROOT . '/' . $index . '.png');
        }
        else {
          \Drupal::logger('localgov_pdf_importer')->error("Couldn't import image $width, $height, $bits_per_component, $color_space");
        }
      }
      $index++;
    }


    $import = new Import($this->pathToFile);

    $details = $pdf->getDetails();
    if (isset($details['Title']) && $details['Title'] !== '') {
      $import->setTitle($details['Title']);
    }
    else {
      // Fall back to the filename if we can't find a title in the PDF.
      // This isn't ideal, but we need to have a title to save a node.
      $import->setTitle(basename($this->pathToFile));
    }

    // Get the pages and sort them. They don't come back in order by default.
    $pdfPages = $pdf->getPages();
    usort($pdfPages, function ($a, $b) {
      return intval($a->getPageNumber()) <=> intval($b->getPageNumber());
    });

    foreach ($pdfPages as $pdfPage) {

      // Don't add empty pages.
      $content = trim($pdfPage->getText());
      if ($content === '') {
        continue;
      }

      $page = new Page();
      $page->setTitle('Page ' . $pdfPage->getPageNumber());
      $page->setContent($content);
      $page->setPageNumber($pdfPage->getPageNumber());
      $import->addPage($page);

      $xObjects = $pdfPage->getXObjects();
      foreach ($xObjects as $xObject) {
        if ($xObject instanceof Image) {

        }
      }
    }

    return $import;
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
   * @return resource|false
   *   A GD image resource on success, FALSE on failure.
   */
  public function createImageFromXObjectData(string $binary_data, int $width, int $height, int $bits_per_component, string $color_space, ?string $intent = NULL) {

    // Validate input parameters according to ISO 32000-1
    if ($width <= 0 || $height <= 0) {
      return FALSE;
    }

    if (!in_array($bits_per_component, [1, 2, 4, 8, 16])) {
      return FALSE;
    }

    // Create a new image resource
    $image = imagecreatetruecolor($width, $height);
    if (!$image) {
      return FALSE;
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
        // return $this->processGrayscaleImage($image, $binary_data, $width, $height, $bits_per_component);
    }
  }

  /**
   * Process RGB image data.
   *
   * @param resource $image
   *   The GD image resource.
   * @param string $binary_data
   *   The raw binary image data.
   * @param int $width
   *   The width of the image.
   * @param int $height
   *   The height of the image.
   * @param int $bits_per_component
   *   The number of bits per color component.
   *
   * @return resource|false
   *   The processed image resource or FALSE on failure.
   */
  private function processRgbImage($image, string $binary_data, int $width, int $height, int $bits_per_component) {
    $bytes_per_component = $bits_per_component / 8;
    $components_per_pixel = 3; // RGB has 3 components
    $bytes_per_pixel = $bytes_per_component * $components_per_pixel;

    $data_length = strlen($binary_data);
    $expected_length = $width * $height * $bytes_per_pixel;

    if ($data_length < $expected_length) {
      return FALSE;
    }

    $offset = 0;
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        if ($offset + $bytes_per_pixel > $data_length) {
          return $image;
        }

        // Extract RGB values based on bits per component
        if ($bits_per_component == 8) {
          $r = ord($binary_data[$offset]);
          $g = ord($binary_data[$offset + 1]);
          $b = ord($binary_data[$offset + 2]);
        } else {
          // Scale values to 8-bit range
          $max_value = (1 << $bits_per_component) - 1;
          $r = $this->extractComponentValue($binary_data, $offset, $bits_per_component) * 255 / $max_value;
          $g = $this->extractComponentValue($binary_data, $offset + $bytes_per_component, $bits_per_component) * 255 / $max_value;
          $b = $this->extractComponentValue($binary_data, $offset + 2 * $bytes_per_component, $bits_per_component) * 255 / $max_value;
        }

        $color = imagecolorallocate($image, (int)$r, (int)$g, (int)$b);
        imagesetpixel($image, $x, $y, $color);

        $offset += $bytes_per_pixel;
      }
    }

    return $image;
  }

  /**
   * Process grayscale image data.
   *
   * @param resource $image
   *   The GD image resource.
   * @param string $binary_data
   *   The raw binary image data.
   * @param int $width
   *   The width of the image.
   * @param int $height
   *   The height of the image.
   * @param int $bits_per_component
   *   The number of bits per color component.
   *
   * @return resource|false
   *   The processed image resource or FALSE on failure.
   */
  private function processGrayscaleImage($image, string $binary_data, int $width, int $height, int $bits_per_component) {
    $bytes_per_component = $bits_per_component / 8;
    $data_length = strlen($binary_data);
    $expected_length = $width * $height * $bytes_per_component;

    if ($data_length < $expected_length) {
      return FALSE;
    }

    $offset = 0;
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        if ($offset + $bytes_per_component > $data_length) {
          return $image;
        }

        // Extract grayscale value
        if ($bits_per_component == 8) {
          $gray = ord($binary_data[$offset]);
        } else {
          // Scale value to 8-bit range
          $max_value = (1 << $bits_per_component) - 1;
          $gray = $this->extractComponentValue($binary_data, $offset, $bits_per_component) * 255 / $max_value;
        }

        $color = imagecolorallocate($image, (int)$gray, (int)$gray, (int)$gray);
        imagesetpixel($image, $x, $y, $color);

        $offset += $bytes_per_component;
      }
    }

    return $image;
  }

  /**
   * Process CMYK image data.
   *
   * @param resource $image
   *   The GD image resource.
   * @param string $binary_data
   *   The raw binary image data.
   * @param int $width
   *   The width of the image.
   * @param int $height
   *   The height of the image.
   * @param int $bits_per_component
   *   The number of bits per color component.
   *
   * @return resource|false
   *   The processed image resource or FALSE on failure.
   */
  private function processCmykImage($image, string $binary_data, int $width, int $height, int $bits_per_component) {
    $bytes_per_component = $bits_per_component / 8;
    $components_per_pixel = 4; // CMYK has 4 components
    $bytes_per_pixel = $bytes_per_component * $components_per_pixel;

    $data_length = strlen($binary_data);
    $expected_length = $width * $height * $bytes_per_pixel;

    if ($data_length < $expected_length) {
      return FALSE;
    }

    $offset = 0;
    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        if ($offset + $bytes_per_pixel > $data_length) {
          return $image;
        }

        // Extract CMYK values and convert to RGB
        if ($bits_per_component == 8) {
          $c = ord($binary_data[$offset]) / 255;
          $m = ord($binary_data[$offset + 1]) / 255;
          $y_cmyk = ord($binary_data[$offset + 2]) / 255;
          $k = ord($binary_data[$offset + 3]) / 255;
        } else {
          // Scale values to 0-1 range
          $max_value = (1 << $bits_per_component) - 1;
          $c = $this->extractComponentValue($binary_data, $offset, $bits_per_component) / $max_value;
          $m = $this->extractComponentValue($binary_data, $offset + $bytes_per_component, $bits_per_component) / $max_value;
          $y_cmyk = $this->extractComponentValue($binary_data, $offset + 2 * $bytes_per_component, $bits_per_component) / $max_value;
          $k = $this->extractComponentValue($binary_data, $offset + 3 * $bytes_per_component, $bits_per_component) / $max_value;
        }

        // Convert CMYK to RGB
        $r = (1 - $c) * (1 - $k) * 255;
        $g = (1 - $m) * (1 - $k) * 255;
        $b = (1 - $y_cmyk) * (1 - $k) * 255;

        $color = imagecolorallocate($image, (int)$r, (int)$g, (int)$b);
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
    } elseif ($bits_per_component == 16) {
      $byte1 = ord($data[$offset] ?? 0);
      $byte2 = ord($data[$offset + 1] ?? 0);
      return ($byte1 << 8) | $byte2;
    } else {
      // For 1, 2, 4 bits per component, we need bit-level extraction
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
