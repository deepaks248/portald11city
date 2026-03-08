<?php

namespace Drupal\Tests\login_logout\Unit\Service;

use Drupal\login_logout\Service\OAuthHelperService;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @coversDefaultClass \Drupal\login_logout\Service\OAuthHelperService
 * @group login_logout
 */
class OAuthHelperServiceTest extends UnitTestCase {

  protected $httpClient;
  protected $logger;
  protected $service;

  protected function setUp(): void {
    parent::setUp();

    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    new Settings([
      'idam_local_authenticator_id' => 'local_auth_id',
    ]);

    $this->service = new OAuthHelperService($this->httpClient, $this->logger);
  }

  /**
   * @covers ::__construct
   */
  public function testConstructor() {
    $this->assertInstanceOf(OAuthHelperService::class, $this->service);
  }

  /**
   * @covers ::detectFromRules
   * @covers ::matchesConditions
   */
  public function testDetectFromRules() {
    $rules = [
      'Mobile' => ['iphone'],
      'Desktop' => ['!iphone', 'mozilla'],
    ];

    $this->assertEquals('Mobile', $this->service->detectFromRules('iphone user agent', $rules, 'Default'));
    $this->assertEquals('Desktop', $this->service->detectFromRules('mozilla user agent', $rules, 'Default'));
    $this->assertEquals('Default', $this->service->detectFromRules('something else', $rules, 'Default'));
  }

  /**
   * @covers ::matchesConditions
   */
  public function testMatchesConditions() {
    $this->assertTrue($this->service->matchesConditions('iphone agent', ['iphone']));
    $this->assertFalse($this->service->matchesConditions('iphone agent', ['!iphone']));
    $this->assertFalse($this->service->matchesConditions('android agent', ['iphone']));
    $this->assertTrue($this->service->matchesConditions('android agent', ['!iphone']));
  }

  /**
   * @covers ::prepareAuthPayload
   */
  public function testPrepareAuthPayload() {
    $payload = $this->service->prepareAuthPayload('flow123', 'user@test.com', 'pass123');
    $expected = [
      "flowId" => 'flow123',
      "selectedAuthenticator" => [
        "authenticatorId" => 'local_auth_id',
        "params" => ["username" => 'user@test.com', "password" => 'pass123'],
      ],
    ];
    $this->assertEquals($expected, $payload);
  }

  /**
   * @covers ::sendAuthenticationRequest
   */
  public function testSendAuthenticationRequest() {
    $this->httpClient->expects($this->once())
      ->method('request')
      ->with('POST', $this->stringContains('/oauth2/authn'), $this->arrayHasKey('json'))
      ->willReturn($this->createMock(ResponseInterface::class));

    $this->service->sendAuthenticationRequest('api.test.com', [], 'agent');
  }

  /**
   * @covers ::parseResponse
   */
  public function testParseResponse() {
    $response = $this->createMock(ResponseInterface::class);
    $body = $this->createMock(StreamInterface::class);
    $body->method('getContents')->willReturn(json_encode(['foo' => 'bar']));
    $response->method('getBody')->willReturn($body);

    $result = $this->service->parseResponse($response);
    $this->assertEquals(['foo' => 'bar'], $result);
  }

  /**
   * @covers ::isAuthSuccess
   */
  public function testIsAuthSuccess() {
    $this->assertTrue($this->service->isAuthSuccess(['authData' => ['code' => '123']]));
    $this->assertFalse($this->service->isAuthSuccess(['authData' => []]));
    $this->assertFalse($this->service->isAuthSuccess([]));
  }

  /**
   * @covers ::handleAuthSuccess
   */
  public function testHandleAuthSuccess() {
    $result = $this->service->handleAuthSuccess(['authData' => ['code' => '123']]);
    $this->assertTrue($result['success']);
    $this->assertEquals('123', $result['code']);
  }

  /**
   * @covers ::isActiveSessionLimitReached
   */
  public function testIsActiveSessionLimitReached() {
    $result = [
      'nextStep' => [
        'authenticators' => [
          ['authenticator' => 'Active Sessions Limit']
        ]
      ]
    ];
    $this->assertTrue($this->service->isActiveSessionLimitReached($result));
    
    $result['nextStep']['authenticators'][0]['authenticator'] = 'Other';
    $this->assertFalse($this->service->isActiveSessionLimitReached($result));
  }

  /**
   * @covers ::handleSessionLimit
   * @covers ::formatSessions
   */
  public function testHandleSessionLimit() {
    $sessions = [
      ['browser' => 'Chrome', 'device' => 'PC', 'lastAccessTime' => 1600000000000],
    ];
    $result = [
      'nextStep' => [
        'authenticators' => [
          [
            'metadata' => [
              'additionalData' => [
                'MaxSessionCount' => '2',
                'sessions' => json_encode($sessions),
              ]
            ]
          ]
        ]
      ]
    ];

    $this->logger->expects($this->once())->method('notice');
    $response = $this->service->handleSessionLimit($result, 'test@test.com');
    
    $this->assertFalse($response['success']);
    $this->assertStringContainsString('reached the maximum active sessions (2)', $response['message']);
  }

  /**
   * @covers ::handleErrorResponse
   */
  public function testHandleErrorResponse() {
    $result = [
      'nextStep' => [
        'messages' => [
          ['message' => 'Custom Error']
        ]
      ]
    ];
    $response = $this->service->handleErrorResponse($result);
    $this->assertEquals('Custom Error', $response['message']);

    $response = $this->service->handleErrorResponse([]);
    $this->assertEquals('Authentication failed. Please try again.', $response['message']);
  }

  /**
   * @covers ::logError
   */
  public function testLogError() {
    $this->logger->expects($this->once())->method('error');
    $this->service->logError('msg');
  }

  /**
   * @covers ::generateErrorResponse
   */
  public function testGenerateErrorResponse() {
    $response = $this->service->generateErrorResponse();
    $this->assertFalse($response['success']);
    $this->assertStringContainsString('error occurred', $response['message']);
  }

  /**
   * @covers ::isValidJwtFormat
   */
  public function testIsValidJwtFormat() {
    $this->assertTrue($this->service->isValidJwtFormat('a.b.c'));
    $this->assertFalse($this->service->isValidJwtFormat('a.b'));
  }

  /**
   * @covers ::extractPayloadFromJwt
   */
  public function testExtractPayloadFromJwt() {
    $this->assertEquals('payload', $this->service->extractPayloadFromJwt('header.payload.signature'));
  }

  /**
   * @covers ::decodeBase64Url
   */
  public function testDecodeBase64Url() {
    $data = ['user' => 'john'];
    $encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($data)));
    
    $result = $this->service->decodeBase64Url($encoded);
    $this->assertEquals($data, $result);
    
    // Test padding
    $result = $this->service->decodeBase64Url(base64_encode(json_encode(['a' => 'b'])));
    $this->assertEquals(['a' => 'b'], $result);
  }
}
