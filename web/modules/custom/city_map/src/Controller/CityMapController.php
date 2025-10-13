<?php

namespace Drupal\city_map\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class CityMapController extends ControllerBase {

  protected $entityTypeManager;
  protected $fileUrlGenerator;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, FileUrlGeneratorInterface $fileUrlGenerator) {
    $this->entityTypeManager = $entityTypeManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_url_generator')
    );
  }

  public function cityMap() {
    return [
      '#theme' => 'city_map',
      '#attached' => [
        'library' => [
          'city_map/city-map-library',
        ],
      ],
    ];
  }

  public function getContentByTerm($tid) {
    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['field_pois_category' => $tid]);

    $data = [];

    foreach ($nodes as $node) {
      $image_url = NULL;

      if (!$node->get('field_image_1')->isEmpty()) {
        $file_uri = $node->get('field_image_1')->entity->getFileUri();
        $image_url = $this->fileUrlGenerator->generateAbsoluteString($file_uri);
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
