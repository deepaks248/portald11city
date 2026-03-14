<?php

namespace Drupal\login_logout\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\login_logout\Service\UserRegistrationSubmitHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserRegisterForm extends FormBase
{
  /**
   * Handles the registration submit workflow.
   *
   * @var \Drupal\login_logout\Service\UserRegistrationSubmitHandler
   */
  protected $registrationSubmitHandler;

  public function __construct(UserRegistrationSubmitHandler $registrationSubmitHandler)
  {
    $this->registrationSubmitHandler = $registrationSubmitHandler;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('login_logout.user_registration_submit_handler')
    );
  }

  public function getFormId()
  {
    return 'user_register_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $tempstore = \Drupal::service('tempstore.private')->get('login_logout');
    $email = $tempstore->get('registration_email');
    $phase = $form_state->get('phase') ?? 1;

    // Classes reused.
    $input_classes = ['form-input', 'w-full', 'rounded-md', 'border', 'border-gray-300', 'focus:border-yellow-500', 'focus:ring-yellow-500', 'text-gray-700', 'text-base', 'p-2.5'];
    $select_classes = ['form-select', 'w-full', 'rounded-md', 'border', 'border-gray-300', 'focus:border-yellow-500', 'focus:ring-yellow-500', 'text-gray-700', 'text-base', 'p-2.5'];
    $button_classes = ['bg-yellow-500', 'hover:bg-yellow-600', 'text-white', 'font-semibold', 'py-2', 'px-4', 'rounded-2xl', 'transition-all'];

    switch ($phase) {
      case 1:
        $form['first_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('First Name'),
          '#required' => TRUE,
          '#maxlength' => 255,
          '#attributes' => ['class' => $input_classes],
        ];
        $form['last_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Last Name'),
          '#required' => TRUE,
          '#maxlength' => 255,
          '#attributes' => ['class' => $input_classes],
        ];
        $form['mail'] = [
          '#type' => 'email',
          '#title' => $this->t('Email'),
          '#default_value' => $email,
          '#required' => TRUE,
          '#maxlength' => 254,
          '#attributes' => ['class' => $input_classes],
        ];
        $form['country_code'] = [
          '#type' => 'select',
          '#title' => $this->t('Country Code'),
          '#required' => TRUE,
          '#options' => [
            '+91' => '+91 (India)',
            '+1' => '+1 (USA)',
            '+44' => '+44 (UK)',
          ],
          '#default_value' => '+91',
          '#attributes' => [
            'class' => $select_classes,
            'autocomplete' => 'off',
          ],
        ];
        $form['mobile'] = [
          '#type' => 'tel',
          '#title' => $this->t('Mobile Number'),
          '#required' => TRUE,
          '#maxlength' => 10,
          '#attributes' => [
            'class' => $input_classes,
            'pattern' => '[0-9]{10}',
            'title' => $this->t('Enter a valid mobile number'),
            'oninput' => 'this.value = this.value.replace(/[^0-9]/g, "").slice(0,10)',
          ],
        ];
        $form['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Send OTP'),
          '#attributes' => ['class' => $button_classes],
        ];
        break;

      case 2:
        $form['otp'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Enter OTP'),
          '#required' => TRUE,
          '#attributes' => [
            'maxlength' => 6,
            'class' => $input_classes,
            'onpaste' => 'return false;',
            'oncopy' => 'return false;',
            'oncut' => 'return false;',
            'autocomplete' => 'off',
            'inputmode' => 'numeric',
            'pattern' => '[0-9]*',
          ],
        ];
        $form['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Verify OTP'),
          '#attributes' => ['class' => $button_classes],
        ];
        break;

      case 3:
        $form['password'] = [
          '#type' => 'password',
          '#title' => $this->t('Password'),
          '#required' => TRUE,
          '#attributes' => [
            'class' => $input_classes,
            'onpaste' => 'return false;',
            'oncopy' => 'return false;',
            'oncut' => 'return false;',
            'autocomplete' => 'new-password',
          ],
        ];
        $form['confirm_password'] = [
          '#type' => 'password',
          '#title' => $this->t('Confirm Password'),
          '#required' => TRUE,
          '#attributes' => [
            'class' => $input_classes,
            'onpaste' => 'return false;',
            'oncopy' => 'return false;',
            'oncut' => 'return false;',
            'autocomplete' => 'new-password',
          ],
        ];
        $form['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Register'),
          '#attributes' => ['class' => $button_classes],
        ];
        break;

      default:
        break;
    }

    $form['#theme'] = 'user_register';
    $form['#attached']['library'][] = 'login_logout/user-login-library';

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    return $this->registrationSubmitHandler->handleFormSubmission($form, $form_state);
  }
}
