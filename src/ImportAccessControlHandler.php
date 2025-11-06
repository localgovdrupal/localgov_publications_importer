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
class ImportAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);
    if (!$result instanceof AccessResultNeutral) {
      return $result;
    }
    if ($account->hasPermission('create imports')) {
      return AccessResult::allowed()->cachePerPermissions();
    }
    return AccessResult::neutral()->setReason("The following permissions are required: 'create imports'.");
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    /** @var \Drupal\localgov_publications_importer\ImportInterface $entity */

    // Parent checks admin perm and is new.
    $result = parent::checkAccess($entity, $operation, $account);
    if (!$result instanceof AccessResultNeutral) {
      return $result;
    }

    switch ($operation) {
      case 'view':
      case 'view label':
        if ($account->hasPermission('view imports')) {
          return AccessResult::allowed()->cachePerPermissions();
        }
        return AccessResult::neutral()->setReason("The following permissions are required: 'view imports'.");

      case 'update':
        $owner = $entity->getCreator();
        if ($owner instanceof AccountInterface && $owner->id() === $account->id()) {
          if ($account->hasPermission('update own imports')) {
            return AccessResult::allowed()->cachePerPermissions();
          }
        }
        return AccessResult::neutral()->setReason("The following permissions are required: 'update own imports'.");

      case 'delete':
        $owner = $entity->getCreator();
        if ($owner instanceof AccountInterface && $owner->id() === $account->id()) {
          if ($account->hasPermission('delete own imports')) {
            return AccessResult::allowed()->cachePerPermissions();
          }
        }
        return AccessResult::neutral()->setReason("The following permissions are required: 'delete own imports'.");

      default:
        // No opinion.
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

}
