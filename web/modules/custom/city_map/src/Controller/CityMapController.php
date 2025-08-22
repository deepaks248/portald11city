<?php

namespace Drupal\city_map\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CityMapController extends ControllerBase
{

  public function cityMap()
  {
    return [
      '#theme' => 'city_map',
      '#attached' => [
        'library' => [
          'city_map/city-map-library',
        ],
      ]
    ];
  }

  public function getContentByTerm($tid)
  {
    $file_url_generator = \Drupal::service('file_url_generator');
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['field_pois_category' => $tid]);

    $data = [];

    foreach ($nodes as $node) {

      // Get Image URL (safe check).
      $image_url = NULL;
      if (!$node->get('field_image_1')->isEmpty()) {
        $file_uri = $node->get('field_image_1')->entity->getFileUri();
        $image_url = $file_url_generator->generateAbsoluteString($file_uri);
      }

      $data[] = [
        'id' => $node->id(),
        'title' => $node->label(),
        'address' => $node->get('field_address')->value ?? '',
        'contact_number' => $node->get('field_contact_number')->value ?? '',
        'description' => $node->get('field_desc')->value ?? '',
        'latitude' => $node->get('field_latitude')->value ?? '',
        'longitude' => $node->get('field_longitude')->value ?? '',
        'price' => $node->get('field_price')->value ?? '',
        'timings' => $node->get('field_timings')->value ?? '',
        'website_url' => $node->get('field_website_url')->value ?? '',
        'image_url' => $image_url,
        'created' => $node->getCreatedTime(),
        'node_url' => $node->toUrl()->toString(),
      ];
    }

    return new JsonResponse([
      'count' => count($data),
      'items' => $data,
    ]);
  }
}
