<?php

namespace Drupal\login_logout\Form {
  if (!function_exists('Drupal\login_logout\Form\user_login_finalize')) {
    function user_login_finalize($account) {
      // Mock global function.
    }
  }
}

namespace Drupal\Tests\login_logout\Unit\Form {

use Drupal\login_logout\Form\UserRegisterForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\ClientInterface;
use Drupal\login_logout\Service\OAuthLoginService;
use Drupal\global_module\Service\GlobalVariablesService;
use Drupal\active_sessions\Service\ActiveSessionService;
use Drupal\global_module\Service\VaultConfigService;
use Drupal\global_module\Service\ApimanTokenService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @coversDefaultClass \Drupal\login_logout\Form\UserRegisterForm
 * @group login_logout
 */
class UserRegisterFormTest extends UnitTestCase {

  protected $requestStack;
  protected $httpClient;
  protected $oauthLoginService;
  protected $globalVariablesService;
  protected $activeSessionService;
  protected $vaultConfigService;
  protected $apimanTokenService;
  protected $tempStoreFactory;
  protected $tempStore;
  protected $currentUser;
  protected $loggerFactory;
  protected $logger;
  protected $messenger;
  protected $flood;
  protected $session;
  protected $lock;
  protected $database;
  protected $time;
  protected $entityTypeManager;
  protected $entityTypeRepository;
  protected $entityFieldManager;
  protected $form;

  protected function setUp(): void {
    parent::setUp();

    $this->requestStack = $this->createMock(RequestStack::class);
    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->oauthLoginService = $this->createMock(OAuthLoginService::class);
    $this->globalVariablesService = $this->createMock(GlobalVariablesService::class);
    $this->activeSessionService = $this->createMock(ActiveSessionService::class);
    $this->vaultConfigService = $this->createMock(VaultConfigService::class);
    $this->apimanTokenService = $this->createMock(ApimanTokenService::class);

    $this->tempStoreFactory = $this->createMock(PrivateTempStoreFactory::class);
    $this->tempStore = $this->createMock(PrivateTempStore::class);
    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->flood = $this->createMock(FloodInterface::class);
    $this->session = $this->createMock(SessionInterface::class);
    $this->lock = $this->createMock(LockBackendInterface::class);
    $this->database = $this->createMock(Connection::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->entityTypeRepository = $this->createMock(EntityTypeRepositoryInterface::class);
    $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);

    $this->tempStoreFactory->method('get')->with('login_logout')->willReturn($this->tempStore);
    $this->loggerFactory->method('get')->willReturn($this->logger);

    $container = new ContainerBuilder();
    $container->set('tempstore.private', $this->tempStoreFactory);
    $container->set('current_user', $this->currentUser);
    $container->set('logger.factory', $this->loggerFactory);
    $container->set('messenger', $this->messenger);
    $container->set('flood', $this->flood);
    $container->set('session', $this->session);
    $container->set('lock', $this->lock);
    $container->set('database', $this->database);
    $container->set('datetime.time', $this->time);
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('entity_type.repository', $this->entityTypeRepository);
    $container->set('entity_field.manager', $this->entityFieldManager);
    $container->set('request_stack', $this->requestStack);
    $container->set('http_client', $this->httpClient);
    $container->set('login_logout.oauth_login_service', $this->oauthLoginService);
    $container->set('global_module.global_variables', $this->globalVariablesService);
    $container->set('active_sessions.session_service', $this->activeSessionService);
    $container->set('global_module.vault_config_service', $this->vaultConfigService);
    $container->set('global_module.apiman_token_service', $this->apimanTokenService);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->form = new UserRegisterForm(
      $this->requestStack,
      $this->httpClient,
      $this->oauthLoginService,
      $this->globalVariablesService,
      $this->activeSessionService,
      $this->vaultConfigService,
      $this->apimanTokenService
    );
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormId() {
    $this->assertEquals('user_register_form', $this->form->getFormId());
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    
    // Test Phase 1
    $form_state->method('get')->with('phase')->willReturn(1);
    $this->tempStore->method('get')->with('registration_email')->willReturn('test@example.com');
    $built_form = $this->form->buildForm($form, $form_state);
    $this->assertArrayHasKey('first_name', $built_form);

    // Test Phase 2
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->with('phase')->willReturn(2);
    $built_form = $this->form->buildForm($form, $form_state);
    $this->assertArrayHasKey('otp', $built_form);

    // Test Phase 3
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->with('phase')->willReturn(3);
    $built_form = $this->form->buildForm($form, $form_state);
    $this->assertArrayHasKey('password', $built_form);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormPhase1Success() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->willReturnMap([['phase', 1]]);
    $form_state->method('getValue')->willReturnMap([
      ['first_name', 'John'],
      ['last_name', 'Doe'],
      ['mail', 'john@example.com'],
      ['country_code', '+91'],
      ['mobile', '1234567890'],
    ]);

    $request = new Request();
    $request->headers->set('x-real-ip', '127.0.0.1');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($storage);
    $storage->method('loadByProperties')->willReturn([]);

    $this->session->method('getId')->willReturn('session123');
    $this->flood->method('isAllowed')->willReturn(TRUE);
    $this->lock->method('acquire')->willReturn(TRUE);

    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'applicationConfig' => ['config' => ['otpWebhookUrl' => 'http://otp.com']]
    ]);

    $this->httpClient->expects($this->once())->method('request');
    $this->messenger->expects($this->once())->method('addStatus');

    $form_state->expects($this->once())->method('setRebuild');

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormPhase1EmailExists() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->willReturnMap([['phase', 1]]);
    $form_state->method('getValue')->willReturnMap([
        ['mail', 'john@example.com'],
        ['first_name', 'John'],
        ['last_name', 'Doe'],
        ['country_code', '+91'],
        ['mobile', '1234567890'],
    ]);

    $request = new Request();
    $request->headers->set('x-real-ip', '127.0.0.1');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($storage);
    $storage->method('loadByProperties')->willReturn([$this->createMock(UserInterface::class)]);

    $this->messenger->expects($this->once())->method('addError')->with($this->callback(function($markup) {
        return $markup instanceof TranslatableMarkup && strpos($markup->getUntranslatedString(), 'Email already registered') !== false;
    }));

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormPhase1RateLimited() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->willReturnMap([['phase', 1]]);
    $form_state->method('getValue')->willReturnMap([
      ['mail', 'john@example.com'],
      ['first_name', 'John'],
      ['last_name', 'Doe'],
      ['country_code', '+91'],
      ['mobile', '1234567890'],
    ]);

    $request = new Request();
    $request->headers->set('x-real-ip', '127.0.0.1');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($storage);
    $storage->method('loadByProperties')->willReturn([]);

    $this->session->method('getId')->willReturn('session123');
    $this->flood->method('isAllowed')->willReturn(FALSE);

    $stmt = $this->createMock(\Drupal\Core\Database\StatementInterface::class);
    $stmt->method('fetchField')->willReturn(time() - 60);
    $query = $this->createMock(\Drupal\Core\Database\Query\SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('execute')->willReturn($stmt);
    $this->database->method('select')->willReturn($query);

    $result = $this->form->submitForm($form, $form_state);
    $this->assertInstanceOf(JsonResponse::class, $result);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormPhase2Success() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->willReturnMap([
      ['phase', 2],
      ['otp_code', '123456'],
    ]);
    $form_state->method('getValue')->with('otp')->willReturn('123456');

    $form_state->expects($this->once())->method('set')->with('phase', 3);
    $form_state->expects($this->once())->method('setRebuild');

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormPhase3Success() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->willReturnMap([
      ['phase', 3],
      ['user_data', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'mail' => 'john@example.com',
        'country_code' => '+91',
        'mobile' => '1234567890',
      ]],
    ]);
    $form_state->method('getValue')->willReturnMap([
      ['password', 'password123'],
      ['confirm_password', 'password123'],
    ]);

    $request = new Request();
    $request->headers->set('x-real-ip', '127.0.0.1');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $this->apimanTokenService->method('getApimanAccessToken')->willReturn('token');
    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'apiManConfig' => ['config' => ['apiUrl' => 'http://api.com/', 'apiVersion' => 'v1']],
      'applicationConfig' => ['config' => ['ceptenantCode' => 'T1', 'idamconfig' => 'idam.com']]
    ]);

    $this->oauthLoginService->method('getFlowId')->willReturn('flow123');
    $this->oauthLoginService->method('authenticateUser')->willReturn(['code' => 'auth_code']);
    $this->oauthLoginService->method('exchangeCodeForToken')->willReturn([
      'access_token' => 'at',
      'id_token' => 'header.' . base64_encode(json_encode(['sub' => 'john@example.com'])) . '.sig'
    ]);

    $this->activeSessionService->method('fetchActiveSessions')->willReturn(['sessions' => []]);

    $user = $this->createMock(UserInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($storage);
    $storage->method('create')->willReturn($user);
    $this->entityTypeRepository->method('getEntityTypeFromClass')->willReturn('user');

    $form_state->expects($this->once())->method('setRedirect')->with('<front>');

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormPhase3ScimFailure() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->willReturnMap([
      ['phase', 3],
      ['user_data', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'mail' => 'john@example.com',
        'country_code' => '+91',
        'mobile' => '1234567890',
      ]],
    ]);
    $form_state->method('getValue')->willReturnMap([
      ['password', 'password123'],
      ['confirm_password', 'password123'],
    ]);

    $request = new Request();
    $request->headers->set('x-real-ip', '127.0.0.1');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $this->apimanTokenService->method('getApimanAccessToken')->willReturn('token');
    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'apiManConfig' => ['config' => ['apiUrl' => 'http://api.com/', 'apiVersion' => 'v1']],
      'applicationConfig' => ['config' => ['ceptenantCode' => 'T1', 'idamconfig' => 'idam.com']]
    ]);

    $response = $this->createMock(ResponseInterface::class);
    $body = $this->createMock(StreamInterface::class);
    $body->method('getContents')->willReturn(json_encode(['detail' => 'Error - SCIM fail']));
    $response->method('getBody')->willReturn($body);
    $exception = new RequestException('Error', $this->createMock(\Psr\Http\Message\RequestInterface::class), $response);

    $this->httpClient->method('request')->willReturnCallback(function($method, $uri) use ($exception) {
      if (strpos($uri, 'scim2') !== false) {
        throw $exception;
      }
      return $this->createMock(ResponseInterface::class);
    });

    // Mock flow continues
    $this->oauthLoginService->method('getFlowId')->willReturn('flow123');
    $this->oauthLoginService->method('authenticateUser')->willReturn(['code' => 'auth_code']);
    $this->oauthLoginService->method('exchangeCodeForToken')->willReturn([
      'access_token' => 'at',
      'id_token' => 'header.' . base64_encode(json_encode(['sub' => 'john@example.com'])) . '.sig'
    ]);
    $this->activeSessionService->method('fetchActiveSessions')->willReturn(['sessions' => []]);
    $user = $this->createMock(UserInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($storage);
    $storage->method('create')->willReturn($user);
    $this->entityTypeRepository->method('getEntityTypeFromClass')->willReturn('user');

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormPhase3AuthFailure() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->willReturnMap([
      ['phase', 3],
      ['user_data', ['mail' => 'john@example.com']],
    ]);
    $form_state->method('getValue')->willReturnMap([
      ['password', 'pass'],
      ['confirm_password', 'pass'],
    ]);

    $request = new Request();
    $request->headers->set('x-real-ip', '127.0.0.1');
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'apiManConfig' => ['config' => ['apiUrl' => '', 'apiVersion' => '']],
      'applicationConfig' => ['config' => ['ceptenantCode' => '', 'idamconfig' => '']]
    ]);

    $this->oauthLoginService->method('getFlowId')->willReturn('flow');
    $this->oauthLoginService->method('authenticateUser')->willReturn(['code' => NULL, 'message' => 'Auth fail']);

    $this->messenger->expects($this->once())->method('addError')->with($this->callback(function($msg) {
        return strpos($msg, 'Auth fail') !== false;
    }));

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::create
   */
  public function testCreate() {
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnMap([
      ['request_stack', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->requestStack],
      ['http_client', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->httpClient],
      ['login_logout.oauth_login_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->oauthLoginService],
      ['global_module.global_variables', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->globalVariablesService],
      ['active_sessions.session_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->activeSessionService],
      ['global_module.vault_config_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->vaultConfigService],
      ['global_module.apiman_token_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->apimanTokenService],
    ]);

    $form = UserRegisterForm::create($container);
    $this->assertInstanceOf(UserRegisterForm::class, $form);
  }
}

}
