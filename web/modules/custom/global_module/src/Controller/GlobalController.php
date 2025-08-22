<?php

namespace Drupal\global_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\global_module\Service\GlobalService;

class GlobalController extends ControllerBase {

  /**
   * The custom global service.
   *
   * @var \Drupal\global_module\Service\GlobalService
   */
  protected $globalService;

  /**
   * Constructs the controller with GlobalService.
   */
  public function __construct(GlobalService $globalService) {
    $this->globalService = $globalService;
  }

  /**
   * Dependency injection via container.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('global_module.global_variables')
    );
  }

  /**
   * Handles file upload.
   */
  public function fileUpload(Request $request): JsonResponse {
    return $this->globalService->fileUploadser($request);
  }

  public function postData(Request $request): JsonResponse {
    return $this->globalService->postData($request);
  }

   public function detailsUpdate(): JsonResponse {
    return $this->globalService->detailsUpdate();
  }



}
