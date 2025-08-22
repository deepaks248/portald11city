<?php

namespace Drupal\global_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

class StatusController extends ControllerBase {

 public function statusPage() {
  $request = \Drupal::request();
  $status = $request->query->get('status');
  $message = $request->query->get('message');
  $formData = $request->query->get('formData');
  // Example rendering
   return [
      '#theme' => 'status',
      '#status' => $status,
      '#message' => $message,
      '#form_data' => $formData,
      '#attached' => [
        'library' => [
          // Optional: add CSS/JS if needed
          // 'global_module/status-page',
        ],
      ],
    ];
}


}
