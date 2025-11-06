<?php

namespace Drupal\localgov_publications_importer;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;


/**
 * Defines the access control handler for the import entity type.
 */
class ImportPipelineAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);
    if (!$result instanceof AccessResultNeutral) {
      return $result;
    }
    if ($account->hasPermission('create import pipelines')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    return AccessResult::neutral()->setReason("The following permissions are required: 'create import pipelines'.");
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Parent checks admin perm and is new.
    $result = parent::checkAccess($entity, $operation, $account);
    if (!$result instanceof AccessResultNeutral) {
      return $result;
    }

    // Make 'view label' work like 'view'.
    if ($operation === 'view label') {
      $operation = 'view';
    }

    switch ($operation) {
      case 'view':
      case 'update':
      case 'delete':
        if ($account->hasPermission($operation . ' import pipelines')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        return AccessResult::neutral()->setReason("The following permissions are required: '" . $operation . " import pipelines'.");

      default:
        // No opinion.
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

}
