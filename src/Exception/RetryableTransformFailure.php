<?php

namespace Drupal\localgov_publications_importer\Exception;

/**
 * Retryable transform failure.
 *
 * An exception thrown during a transform operation that indicates that the
 * operation could be retried if desired.
 */
class RetryableTransformFailure extends \Exception {

}
