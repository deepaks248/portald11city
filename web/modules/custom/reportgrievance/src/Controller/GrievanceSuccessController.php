<?php

namespace Drupal\reportgrievance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

class GrievanceSuccessController extends ControllerBase {

  protected $tempStore;

  public function __construct(PrivateTempStoreFactory $tempStoreFactory) {
    $this->tempStore = $tempStoreFactory->get('reportgrievance');
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private')
    );
  }

  public function content() {
    $response = $this->tempStore->get('grievance_response');

    return [
      '#theme' => 'grievance_success',
      '#response_data' => $response,
    ];
  }
}
