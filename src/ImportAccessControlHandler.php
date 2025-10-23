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
    if ($account->hasPermission('administer imports')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {
      case 'view':
        if ($account->hasPermission('access imports')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        return AccessResult::neutral()->setReason("The following permissions are required: 'access imports'.");

      case 'delete':

        if ($account->hasPermission('delete any imports')) {
          return AccessResult::allowed()->cachePerPermissions();
        }

        $owner = $entity->getCreator();

        // @todo Verify this.
        if ($owner instanceof AccountInterface && $owner->id() === $account->id()) {
          if ($account->hasPermission('delete own imports')) {
            return AccessResult::allowed()->cachePerPermissions();
          }
        }

        return AccessResult::neutral()->setReason("The following permissions are required: 'delete terms in {$entity->bundle()}' OR 'administer taxonomy'.");

      default:
        // No opinion.
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

}
