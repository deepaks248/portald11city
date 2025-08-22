<?php

namespace Drupal\profile\Controller;

use Drupal\Core\Controller\ControllerBase;

class FamilyFailureController extends ControllerBase {

  public function content() {
    return [
      '#theme' => 'failed-family-member',
    ];
  }
}
