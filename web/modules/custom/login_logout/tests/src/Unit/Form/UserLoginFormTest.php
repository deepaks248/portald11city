<?php

namespace Drupal\Tests\login_logout\Unit\Form;

use Drupal\login_logout\Form\UserLoginForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\login_logout\Service\LoginSubmitHandler;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\login_logout\Form\UserLoginForm
 * @group login_logout
 */
class UserLoginFormTest extends UnitTestCase {

  protected $currentUser;
  protected $loginSubmitHandler;
  protected $loggerFactory;
  protected $logger;
  protected $form;

  protected function setUp(): void {
    parent::setUp();

    $this->currentUser = $this->createMock(AccountProxyInterface::class);
    $this->loginSubmitHandler = $this->createMock(LoginSubmitHandler::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->logger = $this->createMock(LoggerChannelInterface::class);

    $this->loggerFactory->method('get')->willReturn($this->logger);

    $container = new ContainerBuilder();
    $container->set('current_user', $this->currentUser);
    $container->set('login_logout.login_submit_handler', $this->loginSubmitHandler);
    $container->set('logger.factory', $this->loggerFactory);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);

    $this->form = new UserLoginForm(
      $this->currentUser,
      $this->loginSubmitHandler
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
    $form_state->method('getValue')->with('email')->willReturn('a@b.c');
    $form_state->method('get')->with('email_validated')->willReturn(FALSE);

    $request = new Request();
    // We need to set the request in the container for \Drupal::request()
    \Drupal::getContainer()->set('request_stack', new \Symfony\Component\HttpFoundation\RequestStack());
    \Drupal::service('request_stack')->push($request);

    $this->logger->expects($this->once())->method('warning')->with($this->stringContains('AE4'));

    $this->form->validateForm($form, $form_state);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $this->loginSubmitHandler->expects($this->once())
      ->method('handleFormSubmission')
      ->with($form, $form_state);

    $this->form->submitForm($form, $form_state);
  }

  /**
   * @covers ::create
   */
  public function testCreate() {
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnMap([
      ['current_user', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->currentUser],
      ['login_logout.login_submit_handler', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->loginSubmitHandler],
    ]);

    $form = UserLoginForm::create($container);
    $this->assertInstanceOf(UserLoginForm::class, $form);
  }
}
