<?php

namespace Drupal\Tests\global_module\Unit\Service;

use Drupal\global_module\Service\GlobalVariablesService;
use Drupal\global_module\Service\ApimanTokenService;
use Drupal\global_module\Service\VaultConfigService;
use Drupal\global_module\Service\ApiHttpClientService;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Component\Uuid\UuidInterface;

/**
 * @coversDefaultClass \Drupal\global_module\Service\GlobalVariablesService
 * @group global_module
 */
class GlobalVariablesServiceTest extends UnitTestCase {

  protected $httpClient;
  protected $loggerFactory;
  protected $cache;
  protected $apimanTokenService;
  protected $vaultConfigService;
  protected $apiHttpClientService;
  protected $service;

  protected function setUp(): void {
    parent::setUp();

    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->cache = $this->createMock(CacheBackendInterface::class);
    $this->apimanTokenService = $this->createMock(ApimanTokenService::class);
    $this->vaultConfigService = $this->createMock(VaultConfigService::class);
    $this->apiHttpClientService = $this->createMock(ApiHttpClientService::class);

    $this->loggerFactory->method('get')->willReturn($this->createMock(\Psr\Log\LoggerInterface::class));

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('logger.factory', $this->loggerFactory);
    \Drupal::setContainer($container);

    $this->service = new GlobalVariablesService(
      $this->httpClient,
      $this->loggerFactory,
      $this->cache,
      $this->apimanTokenService,
      $this->vaultConfigService,
      $this->apiHttpClientService
    );
  }

  /**
   * @covers ::decrypt
   */
  public function testDecrypt() {
    $encrypted = base64_encode(openssl_encrypt("test", "AES-128-ECB", "Fl%JTt%d954n@PoU", OPENSSL_RAW_DATA));
    $result = $this->service->decrypt($encrypted);
    $this->assertNotNull($result);
  }

  /**
   * @covers ::fileUploadser
   */
  public function testFileUploadserSuccess() {
    $request = Request::create('/fileupload', 'POST');
    $request->request->set('userPic', 'notProfile');

    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'applicationConfig' => ['config' => ['fileuploadPath' => '/upload/']]
    ]);

    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('uuid123');
    
    $container = \Drupal::getContainer();
    $container->set('uuid', $uuid);

    // Mock $_FILES
    $tmpFile = tempnam(sys_get_temp_dir(), 'test.jpg');
    file_put_contents($tmpFile, "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46"); // Valid JPEG signature
    $_FILES['uploadedfile1'] = [
      'name' => 'test.jpg',
      'tmp_name' => $tmpFile,
      'error' => 0,
      'size' => 123
    ];

    $response = $this->createMock(ResponseInterface::class);
    $body = $this->createMock(StreamInterface::class);
    $body->method('getContents')->willReturn('ok');
    $response->method('getBody')->willReturn($body);

    $this->httpClient->method('request')->willReturn($response);

    $result = $this->service->fileUploadser($request);
    $this->assertInstanceOf(JsonResponse::class, $result);
    $data = json_decode($result->getContent(), TRUE);
    $this->assertEquals('/upload/uuid123.jpg', $data['fileName']);
    
    unlink($tmpFile);
  }

  /**
   * @covers ::updateUserProfilePic
   */
  public function testUpdateUserProfilePic() {
    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'apiManConfig' => ['config' => ['apiUrl' => 'http://api.com/', 'apiVersion' => '/v1/']]
    ]);

    $session = $this->createMock(SessionInterface::class);
    $session_data = [
      'mobileNumber' => '12345',
      'firstName' => 'John',
      'lastName' => 'Doe',
      'emailId' => 'test@test.com',
      'tenantCode' => 'tenant',
      'userId' => 123
    ];
    // In GlobalVariablesService::updateUserProfilePic, it calls $session->get('api_redirect_result') twice.
    $session->method('get')->with('api_redirect_result')->willReturn($session_data);

    $request = $this->createMock(Request::class);
    $request->method('getSession')->willReturn($session);
    
    $container = \Drupal::getContainer();
    $request_stack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
    $request_stack->method('getCurrentRequest')->willReturn($request);
    $container->set('request_stack', $request_stack);

    $response = $this->createMock(ResponseInterface::class);
    $body = $this->createMock(StreamInterface::class);
    $body->method('getContents')->willReturn(json_encode(['data' => ['profilePic' => 'http://new.jpg']]));
    $response->method('getBody')->willReturn($body);

    $this->httpClient->method('request')->willReturn($response);

    $result = $this->service->updateUserProfilePic('http://new.jpg');
    $data = json_decode($result->getContent(), TRUE);
    $this->assertTrue($data['status']);
  }

  /**
   * @covers ::detailsUpdate
   */
  public function testDetailsUpdate() {
    $session = $this->createMock(SessionInterface::class);
    $session->method('get')->with('api_redirect_result')->willReturn([
      'firstName' => 'John',
      'lastName' => 'Doe',
      'tenantCode' => 'tenant'
    ]);

    $request = $this->createMock(Request::class);
    $request->method('getSession')->willReturn($session);
    
    $container = \Drupal::getContainer();
    $request_stack = $this->createMock(\Symfony\Component\HttpFoundation\RequestStack::class);
    $request_stack->method('getCurrentRequest')->willReturn($request);
    $container->set('request_stack', $request_stack);

    $client = $this->getMockBuilder(\GuzzleHttp\Client::class)->disableOriginalConstructor()->onlyMethods(['post'])->getMock();
    $container->set('http_client', $client);

    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'apiManConfig' => ['config' => ['apiUrl' => 'http://api.com/', 'apiVersion' => '/v1/']]
    ]);

    $response = $this->createMock(ResponseInterface::class);
    $body = $this->createMock(StreamInterface::class);
    $body->method('__toString')->willReturn(json_encode(['status' => TRUE]));
    $response->method('getBody')->willReturn($body);

    $client->method('post')->willReturn($response);

    $result = $this->service->detailsUpdate();
    $this->assertInstanceOf(JsonResponse::class, $result);
  }
}
