<?php

namespace Drupal\global_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\global_module\Service\GlobalVariablesService;
use Drupal\global_module\Service\ApiGatewayService;

class GlobalController extends ControllerBase
{

  /**
   * The custom global service.
   *
   * @var \Drupal\global_module\Service\GlobalService
   */
  protected $globalService;
  protected $apiGatewayService;

  /**
   * Constructs the controller with GlobalService.
   */
  public function __construct(GlobalVariablesService $globalService, ApiGatewayService $apiGatewayService)
  {
    $this->globalService = $globalService;
    $this->apiGatewayService = $apiGatewayService;
  }

  /**
   * Dependency injection via container.
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('global_module.global_variables'),
      $container->get('global_module.api_gateway')
    );
  }

  /**
   * Handles file upload.
   */
  public function fileUpload(Request $request): JsonResponse
  {
    return $this->globalService->fileUploadser($request);
  }

  public function postData(Request $request): JsonResponse
  {
    return $this->apiGatewayService->postData($request);
  }

  public function detailsUpdate(): JsonResponse
  {
    return $this->globalService->detailsUpdate();
  }

  /**
   * Access check for /fileupload.
   */
  public static function fileUploadAccess(Request $request)
  {
    $access = AccessResult::allowed();
    // Only allow POST
    if ($request->getMethod() !== 'POST') {
      $access = AccessResult::forbidden();
    }

    // Exact path match (case-insensitive)
    $current_path = strtolower(\Drupal::service('path.current')->getPath());
    if ($current_path !== '/fileupload') {
      $access = AccessResult::forbidden();
    }

    // Optional: check user permission
    $current_user = \Drupal::currentUser();
    if (!$current_user->hasPermission('access content')) {
      $access = AccessResult::forbidden();
    }

    return $access;
  }
}
