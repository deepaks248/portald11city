<?php

namespace Drupal\profile\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DeleteAddressController {

  public function delete($nid, Request $request) {
    $node = Node::load($nid);
    if ($node && $node->bundle() === 'add_address') {
      if (\Drupal::currentUser()->hasPermission('delete address') || \Drupal::currentUser()->id() == $node->getOwnerId()) {
        $node->delete();
        return new JsonResponse(['status' => 'success'], 200);
      }
      throw new AccessDeniedHttpException('Access denied.');
    }
    return new JsonResponse(['status' => 'not found'], 404);
  }

}
