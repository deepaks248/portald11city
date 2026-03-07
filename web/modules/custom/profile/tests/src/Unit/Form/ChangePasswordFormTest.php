<?php

namespace Drupal\Tests\profile\Unit\Form;

use Drupal\profile\Form\ChangePasswordForm;
use Drupal\profile\Service\PasswordChangeService;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\profile\Form\ChangePasswordForm
 * @group profile
 */
class ChangePasswordFormTest extends UnitTestCase {

  protected $passwordChangeService;
  protected $form;

  protected function setUp(): void {
    parent::setUp();

    $this->passwordChangeService = $this->createMock(PasswordChangeService::class);

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    $container->set('profile.password_change', $this->passwordChangeService);
    \Drupal::setContainer($container);

    $this->form = new ChangePasswordForm($this->passwordChangeService);
  }

  /**
   * @covers ::getFormId
   */
  public function testGetFormId() {
    $this->assertEquals('change_password_form', $this->form->getFormId());
  }

  /**
   * @covers ::buildForm
   */
  public function testBuildForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);

    $built_form = $this->form->buildForm($form, $form_state);

    $this->assertEquals('change-password', $built_form['#theme']);
    $this->assertArrayHasKey('old_password', $built_form);
    $this->assertArrayHasKey('new_password', $built_form);
    $this->assertArrayHasKey('confirm_password', $built_form);
  }

  /**
   * @covers ::submitForm
   */
  public function testSubmitForm() {
    $form = [];
    $form_state = $this->createMock(FormStateInterface::class);
    $form_state->method('getValue')->willReturnMap([
      ['old_password', 'old'],
      ['new_password', 'new'],
      ['confirm_password', 'new'],
    ]);

    $this->passwordChangeService->method('changePassword')->willReturn([
      'status' => TRUE,
      'message' => 'Success',
    ]);

    $form_state->expects($this->once())
      ->method('setRedirect')
      ->with('global_module.status', [], $this->callback(function($options) {
        return $options['query']['status'] === 1 && $options['query']['message'] === 'Success';
      }));

    $this->form->submitForm($form, $form_state);
  }
}
