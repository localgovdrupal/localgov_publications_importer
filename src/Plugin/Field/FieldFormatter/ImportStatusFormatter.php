<?php

namespace Drupal\localgov_publications_importer\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\NumericFormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\localgov_publications_importer\Entity\Import;

/**
 * Plugin implementation of the 'import_status' formatter.
 */
#[FieldFormatter(
  id: 'import_status',
  label: new TranslatableMarkup('Import status'),
  field_types: [
    'integer',
  ],
)]
class ImportStatusFormatter extends NumericFormatterBase {

  /**
   * {@inheritdoc}
   */
  protected function numberFormat($number) {

    $statuses = [
      Import::STATUS_PENDING => 'Pending',
      Import::STATUS_PROCESSING => 'Processing',
      Import::STATUS_COMPLETED => 'Completed',
      Import::STATUS_FAILED => 'Failed',
    ];

    if (isset($statuses[$number])) {
      return $statuses[$number];
    }

    return $number;
  }

}
