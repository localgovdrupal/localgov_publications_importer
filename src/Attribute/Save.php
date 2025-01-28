<?php

namespace Drupal\localgov_publications_importer\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a Save attribute object.
 */
#[\Attribute(
  \Attribute::TARGET_CLASS,
)]
class Save extends Plugin {

  /**
   * Constructor.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly TranslatableMarkup $description,
  ) {
  }

}
