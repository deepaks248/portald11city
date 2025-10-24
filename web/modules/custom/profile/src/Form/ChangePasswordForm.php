<?php

namespace Drupal\profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\profile\Service\PasswordChangeService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ChangePasswordForm extends FormBase
{

  /**
   * The password change service.
   *
   * @var \Drupal\profile\Service\PasswordChangeService
   */
  protected $passwordChangeService;

  public function __construct(PasswordChangeService $passwordChangeService)
  {
    $this->passwordChangeService = $passwordChangeService;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('profile.password_change')
    );
  }

  public function getFormId()
  {
    return 'change_password_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['#prefix'] = '<div id="change-password-form-wrapper">';
    $form['#suffix'] = '</div>';
    // $form['#attributes']['class'][] = '';

    // Old password.
    $form['old_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Old Password'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['peer', 'w-full', 'lg:max-w-lg', 'px-2.5', 'pb-2.5', 'pt-4', 'text-sm', 'text-medium_dark', 'bg-transparent', 'rounded-lg', 'border', 'border-gray-300', 'appearance-none', 'text-base', 's:text-sm', 'xs:text-sm', 'focus:outline-none', 'focus:ring-0', 'focus:!border-yellow-500'],
        'placeholder' => ' ',
        'autocomplete' => 'off',
        'id' => 'old-password',
      ],
      '#prefix' => '<div class="errors-old-password"><div class="relative">',
      '#suffix' => '</div></div>',
    ];

    // New password.
    $form['new_password'] = [
      '#type' => 'password',
      '#title' => $this->t('New Password'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['peer', 'w-full', 'lg:max-w-lg', 'text-base', 's:text-sm', 'xs:text-sm', 'rounded-lg', 'border', 'border-gray-300', 'px-2.5', 'pb-2.5', 'pt-4', 'focus:outline-none', 'focus:ring-0', 'focus:!border-yellow-500'],
        'maxlength' => 10,
        'minlength' => 10,
        'id' => 'new-password',
        'placeholder' => ' ',
      ],
      '#prefix' => '<div class="errors-new-password"><div class="relative">',
      '#suffix' => '</div></div>',
    ];

    // Confirm password.
    $form['confirm_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Confirm Password'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => ['peer', 'w-full', 'lg:max-w-lg', 'text-base', 's:text-sm', 'xs:text-sm', 'rounded-lg', 'border', 'border-gray-300', 'px-2.5', 'pb-2.5', 'pt-4', 'focus:outline-none', 'focus:ring-0', 'focus:!border-yellow-500'],
        'placeholder' => ' ',
        'id' => 'confirm-password',
      ],
      '#prefix' => '<div class="errors-confirm-password"><div class="relative">',
      '#suffix' => '</div></div>',
    ];

    // Submit button.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#attributes' => [
        'class' => ['bg-yellow-500', 'text-white', 'rounded-xl', 'px-6', 'py-2', 'cursor-pointer', 'hover:bg-yellow-600', 'transition'],
      ],
    ];

    $form['#theme'] = 'change-password';
    $form['#attached']['library'][] = 'profile/change-password-library';

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $oldPass     = $form_state->getValue('old_password');
    $newPass     = $form_state->getValue('new_password');
    $confirmPass = $form_state->getValue('confirm_password');

    $result = $this->passwordChangeService->changePassword($oldPass, $newPass, $confirmPass);

    $status  = !empty($result['status']) ? 1 : 0;
    $message = $result['message'] ?? 'Something went wrong.';

    // Always redirect to status page
    $form_state->setRedirect('global_module.status', [], [
      'query' => [
        'status'   => $status,
        'message'  => $message,
        'formData' => 'change-password',
      ],
    ]);
  }
}
