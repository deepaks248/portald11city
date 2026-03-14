<?php

namespace Drupal\Tests\login_logout\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\global_module\Service\ApimanTokenService;
use Drupal\global_module\Service\VaultConfigService;
use Drupal\login_logout\Service\UserRegistrationAccountManager;
use Drupal\login_logout\Service\UserRegistrationSubmitHandler;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @coversDefaultClass \Drupal\login_logout\Service\UserRegistrationSubmitHandler
 * @group login_logout
 */
class UserRegistrationSubmitHandlerTest extends UnitTestCase
{
  protected $httpClient;
  protected $vaultConfigService;
  protected $apimanTokenService;
  protected $messenger;
  protected $entityTypeManager;
  protected $entityStorage;
  protected $currentUser;
  protected $requestStack;
  protected $session;
  protected $flood;
  protected $lock;
  protected $database;
  protected $loggerFactory;
  protected $secauditLogger;
  protected $registerApiLogger;
  protected $scimLogger;
  protected $accountManager;
  protected $time;
  protected $handler;

  protected function setUp(): void
  {
    parent::setUp();

    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->vaultConfigService = $this->createMock(VaultConfigService::class);
    $this->apimanTokenService = $this->createMock(ApimanTokenService::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityStorage = $this->createMock(EntityStorageInterface::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->session = $this->createMock(SessionInterface::class);
    $this->flood = $this->createMock(FloodInterface::class);
    $this->lock = $this->createMock(LockBackendInterface::class);
    $this->database = $this->createMock(Connection::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->secauditLogger = $this->createMock(LoggerChannelInterface::class);
    $this->registerApiLogger = $this->createMock(LoggerChannelInterface::class);
    $this->scimLogger = $this->createMock(LoggerChannelInterface::class);
    $this->accountManager = $this->createMock(UserRegistrationAccountManager::class);
    $this->time = $this->createMock(\Drupal\Component\Datetime\TimeInterface::class);

    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($this->entityStorage);
    $this->loggerFactory->method('get')->willReturnCallback(function (string $channel) {
      return match ($channel) {
        'register_api' => $this->registerApiLogger,
        'scim_user' => $this->scimLogger,
        default => $this->secauditLogger,
      };
    });

    $request = new Request();
    $request->headers->set('x-real-ip', '127.0.0.1');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);
    $this->currentUser->method('id')->willReturn(7);

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('datetime.time', $this->time);
    \Drupal::setContainer($container);

    $this->handler = new class(
      $this->httpClient,
      $this->vaultConfigService,
      $this->apimanTokenService,
      $this->messenger,
      $this->entityTypeManager,
      $this->currentUser,
      $this->requestStack,
      $this->session,
      $this->flood,
      $this->lock,
      $this->database,
      $this->loggerFactory,
      $this->accountManager
    ) extends UserRegistrationSubmitHandler {
      public function callHandleOtpRequest(FormStateInterface $formState): ?JsonResponse {
        return $this->handleOtpRequest($formState);
      }
      public function callHandleOtpVerification(FormStateInterface $formState): ?JsonResponse {
        return $this->handleOtpVerification($formState);
      }
      public function callHandleFinalRegistration(FormStateInterface $formState): ?JsonResponse {
        return $this->handleFinalRegistration($formState);
      }
      public function callCollectRegistrationData(FormStateInterface $formState): array {
        return $this->collectRegistrationData($formState);
      }
      public function callLogUsernameAnomalies(string $username): void {
        $this->logUsernameAnomalies($username);
      }
      public function callLogPasswordAnomalies(string $password): void {
        $this->logPasswordAnomalies($password);
      }
      public function callBuildAuditContext(): array {
        return $this->buildAuditContext();
      }
      public function callEmailAlreadyRegistered(string $email): bool {
        return $this->emailAlreadyRegistered($email);
      }
      public function callEnforceOtpRateLimit(string $email): ?JsonResponse {
        return $this->enforceOtpRateLimit($email);
      }
      public function callBuildOtpIdentifier(string $email): string {
        return $this->buildOtpIdentifier($email);
      }
      public function callGetOtpWaitTime(string $identifier): int {
        return $this->getOtpWaitTime($identifier);
      }
      public function callSendOtpWithLock(array $data, string $otp, string $identifier): ?JsonResponse {
        return $this->sendOtpWithLock($data, $otp, $identifier);
      }
      public function callSendOtpWebhookRequest(array $data, string $otp): void {
        $this->sendOtpWebhookRequest($data, $otp);
      }
      public function callRegisterApiUser(array $data): bool {
        return $this->registerApiUser($data);
      }
      public function callRegisterScimUser(array $data, string $password): void {
        $this->registerScimUser($data, $password);
      }
      public function callPasswordsMatch(string $password, string $confirmPassword, FormStateInterface $formState): bool {
        return $this->passwordsMatch($password, $confirmPassword, $formState);
      }
      public function callGenerateOtp(): string {
        return $this->generateOtp();
      }
      public function callGetClientIp(): string {
        return $this->getClientIp();
      }
    };
  }

  /**
   * @covers ::handleFormSubmission
   * @covers ::handleOtpVerification
   */
  public function testHandleFormSubmissionCoversPhaseTwoPaths(): void
  {
    $invalid = $this->createMock(FormStateInterface::class);
    $invalid->method('get')->willReturnMap([
      ['phase', 2],
      ['otp_code', '111111'],
    ]);
    $invalid->method('getValue')->with('otp')->willReturn('222222');
    $invalid->expects($this->once())->method('setRebuild');
    $this->messenger->expects($this->once())->method('addError');

    $form = [];
    $this->assertNull($this->handler->handleFormSubmission($form, $invalid));

    $valid = $this->createMock(FormStateInterface::class);
    $valid->method('get')->willReturnMap([
      ['phase', 2],
      ['otp_code', '123456'],
    ]);
    $valid->method('getValue')->with('otp')->willReturn('123456');
    $valid->expects($this->once())->method('set')->with('phase', 3);
    $valid->expects($this->once())->method('setRebuild');

    $form = [];
    $this->assertNull($this->handler->handleFormSubmission($form, $valid));
  }

  /**
   * @covers ::handleOtpRequest
   * @covers ::collectRegistrationData
   * @covers ::emailAlreadyRegistered
   */
  public function testHandleOtpRequestStopsWhenEmailExists(): void
  {
    $formState = $this->createRegistrationFormState();
    $this->entityStorage->method('loadByProperties')->willReturn(['existing-user']);
    $this->messenger->expects($this->once())->method('addError');

    $this->assertNull($this->handler->callHandleOtpRequest($formState));
  }

  /**
   * @covers ::handleOtpRequest
   * @covers ::enforceOtpRateLimit
   * @covers ::buildOtpIdentifier
   * @covers ::getOtpWaitTime
   */
  public function testHandleOtpRequestReturnsRateLimitResponse(): void
  {
    $formState = $this->createRegistrationFormState();
    $this->entityStorage->method('loadByProperties')->willReturn([]);
    $this->session->method('getId')->willReturn('session-1');
    $this->flood->method('isAllowed')->willReturn(FALSE);
    $this->mockFloodTimestampQuery(time() - 30);
    $this->time->method('getCurrentTime')->willReturn(time());
    $this->messenger->expects($this->once())->method('addError');

    $response = $this->handler->callHandleOtpRequest($formState);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $this->assertSame(429, $response->getStatusCode());
  }

  /**
   * @covers ::handleOtpRequest
   * @covers ::sendOtpWithLock
   * @covers ::sendOtpWebhookRequest
   */
  public function testHandleOtpRequestSuccess(): void
  {
    $formState = $this->createRegistrationFormState();
    $this->entityStorage->method('loadByProperties')->willReturn([]);
    $this->session->method('getId')->willReturn('session-2');
    $this->flood->method('isAllowed')->willReturn(TRUE);
    $this->lock->method('acquire')->willReturn(TRUE);
    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'applicationConfig' => ['config' => ['otpWebhookUrl' => 'http://otp.local']],
    ]);

    $this->httpClient->expects($this->once())->method('request')->with(
      'POST',
      'http://otp.local',
      $this->arrayHasKey('json')
    );
    $this->flood->expects($this->once())->method('register');
    $this->lock->expects($this->once())->method('release')->with('otp_lock:john@example.com');
    $this->messenger->expects($this->once())->method('addStatus');
    $formState->expects($this->exactly(3))->method('set')->willReturnCallback(function ($key, $value) {
      static $calls = [];
      $calls[] = [$key, $value];
      if (count($calls) === 1) {
        $this->assertSame('user_data', $key);
        $this->assertIsArray($value);
        return NULL;
      }
      if (count($calls) === 2) {
        $this->assertSame('otp_code', $key);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $value);
        return NULL;
      }
      $this->assertSame(['phase', 2], [$key, $value]);
      return NULL;
    });
    $formState->expects($this->once())->method('setRebuild');

    $this->assertNull($this->handler->callHandleOtpRequest($formState));
  }

  /**
   * @covers ::sendOtpWithLock
   */
  public function testSendOtpWithLockHandlesFailurePaths(): void
  {
    $data = [
      'first_name' => 'John',
      'last_name' => 'Doe',
      'mail' => 'john@example.com',
      'mobile' => '1234567890',
    ];

    $this->lock->method('acquire')->willReturnOnConsecutiveCalls(FALSE, TRUE);
    $this->messenger->expects($this->exactly(2))->method('addError');
    $this->registerApiLogger->expects($this->once())->method('error');
    $this->lock->expects($this->once())->method('release')->with('otp_lock:john@example.com');
    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'applicationConfig' => ['config' => ['otpWebhookUrl' => 'http://otp.local']],
    ]);
    $this->httpClient->method('request')->willThrowException(new \RuntimeException('OTP send failed'));

    $this->assertSame(503, $this->handler->callSendOtpWithLock($data, '123456', 'otp:id')->getStatusCode());
    $this->assertSame(500, $this->handler->callSendOtpWithLock($data, '123456', 'otp:id')->getStatusCode());
  }

  /**
   * @covers ::handleFinalRegistration
   * @covers ::passwordsMatch
   * @covers ::registerApiUser
   * @covers ::registerScimUser
   */
  public function testHandleFinalRegistrationPasswordMismatch(): void
  {
    $mismatch = $this->createMock(FormStateInterface::class);
    $mismatch->method('getValue')->willReturnMap([
      ['password', 'secret123'],
      ['confirm_password', 'different'],
    ]);
    $mismatch->expects($this->once())->method('setRebuild');
    $this->messenger->expects($this->once())->method('addError');
    $this->assertNull($this->handler->callHandleFinalRegistration($mismatch));
  }

  /**
   * @covers ::handleFinalRegistration
   * @covers ::registerApiUser
   */
  public function testHandleFinalRegistrationStopsOnApiFailure(): void
  {
    $apiFailure = $this->createMock(FormStateInterface::class);
    $apiFailure->method('getValue')->willReturnMap([
      ['password', 'secret123'],
      ['confirm_password', 'secret123'],
    ]);
    $apiFailure->method('get')->with('user_data')->willReturn($this->userData());
    $this->apimanTokenService->method('getApimanAccessToken')->willReturn('token-1');
    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'apiManConfig' => ['config' => ['apiUrl' => 'http://api.local/', 'apiVersion' => 'v1/']],
      'applicationConfig' => ['config' => ['ceptenantCode' => 'T1', 'idamconfig' => 'idam.local']],
    ]);
    $this->httpClient->method('request')->willThrowException($this->buildRequestException(['developerMessage' => 'API failed']));
    $this->assertNull($this->handler->callHandleFinalRegistration($apiFailure));
  }

  /**
   * @covers ::handleFinalRegistration
   * @covers ::registerApiUser
   * @covers ::registerScimUser
   */
  public function testHandleFinalRegistrationSuccessDelegatesToAccountManager(): void
  {
    $success = $this->createMock(FormStateInterface::class);
    $success->method('getValue')->willReturnMap([
      ['password', 'secret123'],
      ['confirm_password', 'secret123'],
    ]);
    $success->method('get')->with('user_data')->willReturn($this->userData());

    $this->apimanTokenService->method('getApimanAccessToken')->willReturn('token-3');
    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'apiManConfig' => ['config' => ['apiUrl' => 'http://api.local/', 'apiVersion' => 'v1/']],
      'applicationConfig' => ['config' => ['ceptenantCode' => 'T1', 'idamconfig' => 'idam.local']],
    ]);
    $this->httpClient->expects($this->exactly(2))->method('request')->willReturn($this->createMock(ResponseInterface::class));
    $this->accountManager->expects($this->once())->method('finalizeRegistration')->with($this->userData(), 'secret123', $success);

    $this->assertNull($this->handler->callHandleFinalRegistration($success));
  }

  /**
   * @covers ::logUsernameAnomalies
   * @covers ::logPasswordAnomalies
   * @covers ::buildAuditContext
   * @covers ::generateOtp
   * @covers ::getClientIp
   */
  public function testAuditAndUtilityHelpers(): void
  {
    $this->secauditLogger->expects($this->exactly(4))->method('warning');

    $this->handler->callLogUsernameAnomalies('x');
    $this->handler->callLogPasswordAnomalies("\x07bad");

    $this->assertSame([
      '@uid' => 7,
      '@ip' => '127.0.0.1',
    ], $this->handler->callBuildAuditContext());

    $otp = $this->handler->callGenerateOtp();
    $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
    $this->assertSame('127.0.0.1', $this->handler->callGetClientIp());
  }

  /**
   * @covers ::buildOtpIdentifier
   * @covers ::getOtpWaitTime
   * @covers ::registerApiUser
   * @covers ::registerScimUser
   */
  public function testRemainingHelpers(): void
  {
    $this->session->method('getId')->willReturn('abc');
    $this->assertSame('otp:test@example.com:abc', $this->handler->callBuildOtpIdentifier('test@example.com'));

    $this->mockFloodTimestampQuery(FALSE);
    $this->assertSame(120, $this->handler->callGetOtpWaitTime('otp:test@example.com:abc'));

    $this->apimanTokenService->method('getApimanAccessToken')->willReturn('token-2');
    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'apiManConfig' => ['config' => ['apiUrl' => 'http://api.local/', 'apiVersion' => 'v1/']],
      'applicationConfig' => ['config' => ['ceptenantCode' => 'T1', 'idamconfig' => 'idam.local']],
    ]);
    $this->httpClient->expects($this->exactly(2))->method('request')->willReturnCallback(function (string $method, string $uri) {
      if (str_contains($uri, 'user/register')) {
        return $this->createMock(ResponseInterface::class);
      }
      throw $this->buildRequestException(['detail' => 'Error - SCIM failed']);
    });
    $this->assertTrue($this->handler->callRegisterApiUser($this->userData()));
    $this->scimLogger->expects($this->once())->method('error');
    $this->messenger->expects($this->once())->method('addError');
    $this->handler->callRegisterScimUser($this->userData(), 'secret123');
  }

  protected function createRegistrationFormState(): FormStateInterface
  {
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getValue')->willReturnMap([
      ['first_name', 'John'],
      ['last_name', 'Doe'],
      ['mail', 'john@example.com'],
      ['country_code', '+91'],
      ['mobile', '1234567890'],
    ]);

    return $formState;
  }

  protected function mockFloodTimestampQuery($timestamp): void
  {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchField')->willReturn($timestamp);

    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($statement);

    $this->database->method('select')->willReturn($query);
  }

  protected function buildRequestException(array $payload): RequestException
  {
    $response = $this->createMock(ResponseInterface::class);
    $stream = $this->createMock(StreamInterface::class);
    $stream->method('getContents')->willReturn(json_encode($payload));
    $response->method('getBody')->willReturn($stream);

    return new RequestException('request failed', $this->createMock(RequestInterface::class), $response);
  }

  protected function userData(): array
  {
    return [
      'first_name' => 'John',
      'last_name' => 'Doe',
      'mail' => 'john@example.com',
      'country_code' => '+91',
      'mobile' => '1234567890',
    ];
  }
}
