<?php

namespace Drupal\profile\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DeleteAddressController {

  /**
   * Custom access check for deleting an address.
   */
  public function access($nid, AccountInterface $account) {
    $node = Node::load($nid);

    if (!$node || $node->bundle() !== 'add_address') {
      return AccessResult::forbidden();
    }

    // Allow if user has "delete any".
    if ($account->hasPermission('delete any add_address content')) {
      return AccessResult::allowed();
    }

    // Allow if user has "delete own" and owns this node.
    if (
      $account->hasPermission('delete own add_address content') &&
      $account->id() === $node->getOwnerId()
    ) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Deletes an address node.
   */
  public function delete($nid, Request $request) {
    $node = Node::load($nid);

    if (!$node || $node->bundle() !== 'add_address') {
      return new JsonResponse(['status' => 'not found'], 404);
    }

    $node->delete();
    return new JsonResponse(['status' => 'success'], 200);
  }
}