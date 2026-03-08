<?php

namespace Drupal\login_logout\Form {
  if (!function_exists('Drupal\login_logout\Form\user_login_finalize')) {
    function user_login_finalize($account) {
      // Mock global function for unit test.
    }
  }
}

namespace Drupal\Tests\login_logout\Unit\Form {

use Drupal\login_logout\Form\UserLoginForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserAuthInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Drupal\login_logout\Service\OAuthLoginService;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Database\Connection;
use Drupal\global_module\Service\GlobalVariablesService;
use Drupal\login_logout\Service\PasswordRecoveryService;
use Drupal\active_sessions\Service\ActiveSessionService;
use Drupal\global_module\Service\VaultConfigService;
use Drupal\global_module\Service\ApimanTokenService;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\PrivateTempStore;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * @coversDefaultClass \Drupal\login_logout\Form\UserLoginForm
 * @group login_logout
 */
class UserLoginFormTest extends UnitTestCase {

  protected $currentUser;
  protected $userAuth;
  protected $sessionManager;
  protected $requestStack;
  protected $httpClient;
  protected $oauthLoginService;
  protected $database;
  protected $globalVariablesService;
  protected $passwordRecoveryService;
  protected $activeSessionService;
  protected $vaultConfigService;
  protected $apimanTokenService;
  protected $messenger;
  protected $loggerFactory;
  protected $time;
  protected $entityTypeManager;
  protected $tempStoreFactory;
  protected $session;
  protected $form;
  protected $logger;

  protected function setUp(): void {
    parent::setUp();

    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->userAuth = $this->createMock(UserAuthInterface::class);
    $this->sessionManager = $this->createMock(SessionManagerInterface::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->httpClient = $this->createMock(ClientInterface::class);
    $this->oauthLoginService = $this->createMock(OAuthLoginService::class);
    $this->database = $this->createMock(Connection::class);
    $this->globalVariablesService = $this->createMock(GlobalVariablesService::class);
    $this->passwordRecoveryService = $this->createMock(PasswordRecoveryService::class);
    $this->activeSessionService = $this->createMock(ActiveSessionService::class);
    $this->vaultConfigService = $this->createMock(VaultConfigService::class);
    $this->apimanTokenService = $this->createMock(ApimanTokenService::class);
    $this->messenger = $this->createMock(MessengerInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->logger = $this->createMock(LoggerChannelInterface::class);
    $this->time = $this->createMock(TimeInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->tempStoreFactory = $this->createMock(PrivateTempStoreFactory::class);
    $this->session = $this->createMock(SessionInterface::class);

    $this->loggerFactory->method('get')->willReturn($this->logger);

    $container = new ContainerBuilder();
    $container->set('current_user', $this->currentUser);
    $container->set('user.auth', $this->userAuth);
    $container->set('session_manager', $this->sessionManager);
    $container->set('request_stack', $this->requestStack);
    $container->set('http_client', $this->httpClient);
    $container->set('login_logout.oauth_login_service', $this->oauthLoginService);
    $container->set('database', $this->database);
    $container->set('global_module.global_variables', $this->globalVariablesService);
    $container->set('login_logout.password_recovery_service', $this->passwordRecoveryService);
    $container->set('active_sessions.session_service', $this->activeSessionService);
    $container->set('global_module.vault_config_service', $this->vaultConfigService);
    $container->set('global_module.apiman_token_service', $this->apimanTokenService);
    $container->set('messenger', $this->messenger);
    $container->set('logger.factory', $this->loggerFactory);
    $container->set('datetime.time', $this->time);
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('tempstore.private', $this->tempStoreFactory);
    $container->set('session', $this->session);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->form = new UserLoginForm(
      $this->currentUser,
      $this->userAuth,
      $this->sessionManager,
      $this->requestStack,
      $this->httpClient,
      $this->oauthLoginService,
      $this->database,
      $this->globalVariablesService,
      $this->passwordRecoveryService,
      $this->activeSessionService,
      $this->vaultConfigService,
      $this->apimanTokenService
    );
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormId() {
    $this->assertEquals('user_login_email_first', $this->form->getFormId());
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildFormInitial() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('isRebuilding')->willReturn(FALSE);
    $form_state->method('isSubmitted')->willReturn(FALSE);
    $form_state->method('get')->with('email_validated')->willReturn(FALSE);

    $built_form = $this->form->buildForm($form, $form_state);

    $this->assertArrayHasKey('email', $built_form);
    $this->assertArrayHasKey('check_email', $built_form);
    $this->assertArrayNotHasKey('password', $built_form);
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildFormValidated() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->with('email_validated')->willReturn(TRUE);

    $built_form = $this->form->buildForm($form, $form_state);

    $this->assertArrayHasKey('email', $built_form);
    $this->assertArrayHasKey('password', $built_form);
    $this->assertArrayHasKey('login', $built_form);
    $this->assertArrayHasKey('forgot_password', $built_form);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormAbnormalEmail() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('email')->willReturn('a@b.c'); // too short (AE4)
    $form_state->method('get')->with('email_validated')->willReturn(FALSE);

    $request = new Request();
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $this->logger->expects($this->once())->method('warning')->with($this->stringContains('AE4'));

    $this->form->validateForm($form, $form_state);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormInvalidEmailFormat() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->with('email')->willReturn('invalid-email-but-long-enough'); // AE6
    $form_state->method('get')->with('email_validated')->willReturn(FALSE);

    $request = new Request();
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $this->logger->expects($this->once())->method('warning')->with($this->stringContains('AE6'));

    $this->form->validateForm($form, $form_state);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormAbnormalPassword() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->willReturnMap([
      ['email', 'test@example.com'],
      ['password', 'short'],
    ]);
    $form_state->method('get')->with('email_validated')->willReturn(TRUE);

    $request = new Request();
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $this->logger->expects($this->once())->method('warning')->with($this->stringContains('AE5'));

    $this->form->validateForm($form, $form_state);
  }

  /**
   * @covers ::validateForm
   */
  public function testValidateFormControlCharsPassword() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->willReturnMap([
      ['email', 'test@example.com'],
      ['password', "pass\0word-long-enough"], // AE7
    ]);
    $form_state->method('get')->with('email_validated')->willReturn(TRUE);

    $request = new Request();
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $this->logger->expects($this->once())->method('warning')->with($this->stringContains('AE7'));

    $this->form->validateForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormEmailValidationSuccess() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->with('email_validated')->willReturn(FALSE);
    $form_state->method('getValue')->with('email')->willReturn('test@example.com');

    $this->apimanTokenService->method('getApimanAccessToken')->willReturn('token');
    $this->vaultConfigService->method('getGlobalVariables')->willReturn([
      'apiManConfig' => ['config' => ['apiUrl' => 'http://api.com', 'apiVersion' => 'v1']]
    ]);

    $this->oauthLoginService->method('checkEmailExists')->willReturn(TRUE);

    $form_state->expects($this->once())->method('set')->with('email_validated', TRUE)->willReturnSelf();
    $form_state->expects($this->once())->method('setRebuild');

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormEmailValidationException() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->with('email_validated')->willReturn(FALSE);
    $form_state->method('getValue')->with('email')->willReturn('test@example.com');

    $this->apimanTokenService->method('getApimanAccessToken')->willThrowException(new \Exception('Token Error'));

    $this->messenger->expects($this->once())->method('addError')->with($this->callback(function($markup) {
        return $markup instanceof TranslatableMarkup && strpos($markup->getUntranslatedString(), 'Error checking email') !== false;
    }));

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormLoginSuccessWithActiveSessions() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->with('email_validated')->willReturn(TRUE);
    $form_state->method('getValue')->willReturnMap([
      ['email', 'test@example.com'],
      ['password', 'password123'],
    ]);

    $this->oauthLoginService->method('performOAuthLogin')->willReturn([
      'access_token' => 'at',
      'id_token' => 'it'
    ]);

    $this->oauthLoginService->method('decodeJwt')->willReturn(['sub' => 'test@example.com']);
    $this->time->method('getRequestTime')->willReturn(1000); // 1000s

    $this->activeSessionService->method('fetchActiveSessions')->willReturn([
      'sessions' => [
        ['id' => 's1', 'loginTime' => 950000], // diff 50s
        ['id' => 's2', 'loginTime' => 1000000], // perfect match (1000s * 1000)
        ['id' => 's3', 'loginTime' => 1050000], // diff 50s
      ]
    ]);

    $this->session->expects($this->atLeastOnce())->method('set');

    $user = $this->createMock(UserInterface::class);
    $storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($storage);
    $storage->method('loadByProperties')->with(['mail' => 'test@example.com'])->willReturn([$user]);

    $form_state->expects($this->once())->method('setRedirect')->with('<front>');

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormLoginFailure() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->with('email_validated')->willReturn(TRUE);
    $form_state->method('getValue')->willReturnMap([
      ['email', 'test@example.com'],
      ['password', 'wrong'],
    ]);

    $this->oauthLoginService->method('performOAuthLogin')->willThrowException(new \Exception('Login Fail'));

    $this->logger->expects($this->once())->method('error')->with($this->stringContains('AE2'));
    $this->messenger->expects($this->once())->method('addError')->with($this->callback(function($markup) {
        return $markup instanceof TranslatableMarkup && strpos($markup->getUntranslatedString(), 'Login failed') !== false;
    }));

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitFormNoUserFound() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('get')->with('email_validated')->willReturn(TRUE);
    $form_state->method('getValue')->willReturnMap([
      ['email', 'nonexistent@example.com'],
      ['password', 'password123'],
    ]);

    $this->oauthLoginService->method('performOAuthLogin')->willReturn([
      'access_token' => 'at',
      'id_token' => 'it'
    ]);
    $this->oauthLoginService->method('decodeJwt')->willReturn(['sub' => 'nonexistent@example.com']);

    $storage = $this->createMock(EntityStorageInterface::class);
    $this->entityTypeManager->method('getStorage')->with('user')->willReturn($storage);
    $storage->method('loadByProperties')->willReturn([]);

    $this->messenger->expects($this->once())->method('addError')->with($this->callback(function($markup) {
        return $markup instanceof TranslatableMarkup && strpos($markup->getUntranslatedString(), 'No Drupal user found') !== false;
    }));

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::create
   */
  public function testCreate() {
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnMap([
      ['current_user', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->currentUser],
      ['user.auth', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->userAuth],
      ['session_manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->sessionManager],
      ['request_stack', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->requestStack],
      ['http_client', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->httpClient],
      ['login_logout.oauth_login_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->oauthLoginService],
      ['database', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->database],
      ['global_module.global_variables', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->globalVariablesService],
      ['login_logout.password_recovery_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->passwordRecoveryService],
      ['active_sessions.session_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->activeSessionService],
      ['global_module.vault_config_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->vaultConfigService],
      ['global_module.apiman_token_service', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->apimanTokenService],
    ]);

    $form = UserLoginForm::create($container);
    $this->assertInstanceOf(UserLoginForm::class, $form);
  }
}

}
