<?php

namespace Drupal\global_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

class StatusController extends ControllerBase {

  public function statusPage(Request $request) {
    $status = $request->query->get('status', 0);
    $message = $request->query->get('message', 'No message provided.');
    $formData = $request->query->get('formData', 'unknown');

    return [
      '#theme' => 'status',
      '#status' => $status,
      '#message' => $message,
      '#form_data' => $formData,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }
}