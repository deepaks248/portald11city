<?php

namespace Drupal\Tests\profile\Unit\Controller;

use Drupal\profile\Controller\RequestDetailsController;
use Drupal\profile\Service\ServiceRequestApiService;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @coversDefaultClass \Drupal\profile\Controller\RequestDetailsController
 * @group profile
 */
class RequestDetailsControllerTest extends UnitTestCase {

  protected $apiService;
  protected $session;
  protected $controller;

  protected function setUp(): void {
    parent::setUp();

    $this->apiService = $this->createMock(ServiceRequestApiService::class);
    $this->session = $this->createMock(SessionInterface::class);

    $container = new ContainerBuilder();
    $container->set('profile.service_request_api', $this->apiService);
    $container->set('session', $this->session);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->controller = new RequestDetailsController($this->apiService);
  }

  /**
   * @covers ::create
   */
  public function testCreate() {
    $container = \Drupal::getContainer();
    $controller = RequestDetailsController::create($container);
    $this->assertInstanceOf(RequestDetailsController::class, $controller);
  }

  /**
   * @covers ::view
   */
  public function testViewUnauthorized() {
    $request = new Request();
    $this->apiService->method('getServiceRequestDetails')->willReturn([
      'data' => [
        'serviceRequestDetails' => ['userId' => 'different'],
      ],
    ]);
    $this->session->method('get')->with('api_redirect_result')->willReturn(['userId' => 'me']);

    $response = $this->controller->view('GV-123', $request);
    $this->assertInstanceOf(RedirectResponse::class, $response);
    $this->assertEquals('/service-request', $response->getTargetUrl());
  }

  /**
   * @covers ::view
   */
  public function testViewSuccess() {
    $request = new Request();
    $api_data = [
      'data' => [
        'serviceRequestDetails' => ['userId' => 'me'],
      ],
    ];
    $this->apiService->method('getServiceRequestDetails')->willReturn($api_data);
    $this->session->method('get')->with('api_redirect_result')->willReturn(['userId' => 'me']);

    $result = $this->controller->view('GV-123', $request);
    $this->assertEquals('request_details_page', $result['#theme']);
    $this->assertEquals($api_data['data'], $result['#data']);
  }

  /**
   * @covers ::view
   */
  public function testViewEmptyData() {
    $request = new Request();
    $this->apiService->method('getServiceRequestDetails')->willReturn([
      'data' => [],
    ]);
    $this->session->method('get')->with('api_redirect_result')->willReturn(['userId' => 'me']);

    $result = $this->controller->view('GV-123', $request);
    // Since responseUserId is null, it should redirect if it doesn't match sessionUserId.
    // In our case both are null/empty, let's see.
    // The code says: if (!$sessionUserId || !$responseUserId || $sessionUserId !== $responseUserId)
    $this->assertInstanceOf(RedirectResponse::class, $result);
  }
}
