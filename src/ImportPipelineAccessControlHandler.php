<?php

namespace Drupal\localgov_publications_importer;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the import entity type.
 */
class ImportAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    if ($account->hasPermission('administer import pipelines')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {
      case 'view':
        if ($account->hasPermission('access import pipelines')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        return AccessResult::neutral()->setReason("The following permissions are required: 'access import pipelines'.");

      case 'delete':

        if ($account->hasPermission('delete any import pipelines')) {
          return AccessResult::allowed()->cachePerPermissions();
        }

        $owner = $entity->getCreator();

        if ($owner instanceof AccountInterface && $owner->id() === $account->id()) {
          if ($account->hasPermission('delete own import pipelines')) {
            return AccessResult::allowed()->cachePerPermissions();
          }
        }

        return AccessResult::neutral()->setReason("The following permissions are required: 'delete any import pipelines' OR 'delete own import pipelines'.");

      default:
        // No opinion.
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

}
