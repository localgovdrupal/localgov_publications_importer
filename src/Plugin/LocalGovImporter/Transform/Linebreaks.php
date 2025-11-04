<?php

namespace Drupal\localgov_publications_importer\Plugin\LocalGovImporter\Transform;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Attribute\Transform;

/**
 * Transform operation to fix line breaks.
 */
#[Transform(
  id: 'transform_line_breaks',
  label: new TranslatableMarkup('Line breaks'),
  description: new TranslatableMarkup('Remove rogue line breaks and other whitespace from content.')
)]
class Linebreaks extends TransformPluginBase {

  /**
   * Replace unwanted whitespace in the content with a space.
   */
  public function transformContent(string $content): string {
    return trim(str_replace("\t\n", ' ', $content));
  }

}
