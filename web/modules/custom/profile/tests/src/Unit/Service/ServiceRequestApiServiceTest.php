<?php

namespace Drupal\Tests\profile\Unit\Service;

use Drupal\profile\Service\ServiceRequestApiService;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\global_module\Service\VaultConfigService;
use Drupal\global_module\Service\ApimanTokenService;

/**
 * @coversDefaultClass \Drupal\profile\Service\ServiceRequestApiService
 * @group profile
 */
class ServiceRequestApiServiceTest extends UnitTestCase {

  protected $httpClient;
  protected $loggerFactory;
  protected $logger;
  protected $vaultConfigService;
  protected $apimanTokenService;
  protected $service;

  protected function setUp(): void {
    parent::setUp();

    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($this->logger);
    
    $this->vaultConfigService = $this->createMock(VaultConfigService::class);
    $this->apimanTokenService = $this->createMock(ApimanTokenService::class);

    $this->service = new ServiceRequestApiService(
      $this->httpClient,
      $this->loggerFactory,
      $this->vaultConfigService,
      $this->apimanTokenService
    );

    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'apiManConfig' => [
        'config' => [
          'apiUrl' => 'https://api.example.com/',
          'apiVersion' => 'v1/',
        ],
      ],
    ]);

    $this->apimanTokenService->method('getApimanAccessToken')->willReturn('token');
  }

  /**
   * @covers ::getServiceRequestDetails
   */
  public function testGetServiceRequestDetailsSuccess() {
    $response = $this->createMock(ResponseInterface::class);
    $body = $this->createMock(StreamInterface::class);
    $body->method('__toString')->willReturn(json_encode(['id' => 'GV-123']));
    $response->method('getBody')->willReturn($body);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with('GET', $this->stringContains('service-request-by-grievance'))
      ->willReturn($response);

    $result = $this->service->getServiceRequestDetails('GV-123', 1);
    $this->assertEquals(['id' => 'GV-123'], $result);
  }

  /**
   * @covers ::getServiceRequestDetails
   */
  public function testGetServiceRequestDetailsFailure() {
    $this->httpClient->method('request')->willThrowException(new \Exception('Network Error'));
    $this->logger->expects($this->once())->method('error');

    $result = $this->service->getServiceRequestDetails('GV-123', 1);
    $this->assertEmpty($result);
  }

  /**
   * @covers ::getServiceRequests
   */
  public function testGetServiceRequestsSuccess() {
    $response = $this->createMock(ResponseInterface::class);
    $body = $this->createMock(StreamInterface::class);
    $body->method('__toString')->willReturn(json_encode(['items' => []]));
    $response->method('getBody')->willReturn($body);

    $this->httpClient->expects($this->once())
      ->method('request')
      ->with('POST', $this->stringContains('common/service-request'))
      ->willReturn($response);

    $result = $this->service->getServiceRequests(123);
    $this->assertEquals(['items' => []], $result);
  }

  /**
   * @covers ::getServiceRequests
   */
  public function testGetServiceRequestsFailure() {
    $this->httpClient->method('request')->willThrowException(new \Exception('Network Error'));
    $this->logger->expects($this->once())->method('error');

    $result = $this->service->getServiceRequests(123);
    $this->assertEmpty($result);
  }
}
