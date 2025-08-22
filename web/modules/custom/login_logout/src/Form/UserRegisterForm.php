<?php

namespace Drupal\login_logout\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

class UserRegisterForm extends FormBase
{
  protected $requestStack;
  protected $httpClient;

  public function __construct(RequestStack $requestStack, ClientInterface $httpClient)
  {
    $this->requestStack = $requestStack;
    $this->httpClient = $httpClient;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('request_stack'),
      $container->get('http_client')
    );
  }

  public function getFormId()
  {
    return 'user_register_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $request = $this->requestStack->getCurrentRequest();
    $email = $request->query->get('email', '');
    $phase = $form_state->get('phase') ?? 1;

    $input_classes = ['form-input', 'w-full', 'rounded-md', 'border', 'border-gray-300', 'focus:border-yellow-500', 'focus:ring-yellow-500', 'text-gray-700', 'text-base', 'p-2.5'];
    $select_classes = ['form-select', 'w-full', 'rounded-md', 'border', 'border-gray-300', 'focus:border-yellow-500', 'focus:ring-yellow-500', 'text-gray-700', 'text-base', 'p-2.5'];
    $button_classes = ['bg-yellow-500', 'hover:bg-yellow-600', 'text-white', 'font-semibold', 'py-2', 'px-4', 'rounded', 'w-full', 'transition-all'];

    if ($phase === 1) {
      $form['first_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('First Name'),
        '#required' => TRUE,
        '#attributes' => ['class' => $input_classes],
      ];
      $form['last_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Last Name'),
        '#required' => TRUE,
        '#attributes' => ['class' => $input_classes],
      ];
      $form['mail'] = [
        '#type' => 'email',
        '#title' => $this->t('Email'),
        '#default_value' => base64_decode($email),
        '#required' => TRUE,
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
        '#attributes' => ['class' => $select_classes],
      ];
      $form['mobile'] = [
        '#type' => 'tel',
        '#title' => $this->t('Mobile Number'),
        '#required' => TRUE,
        '#attributes' => [
          'maxlength' => 15,
          'class' => $input_classes,
        ],
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Send OTP'),
        '#attributes' => ['class' => $button_classes],
      ];
    } elseif ($phase === 2) {
      $form['otp'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Enter OTP'),
        '#required' => TRUE,
        '#attributes' => [
          'maxlength' => 6,
          'class' => $input_classes,
        ],
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Verify OTP'),
        '#attributes' => ['class' => $button_classes],
      ];
    } elseif ($phase === 3) {
      $form['password'] = [
        '#type' => 'password',
        '#title' => $this->t('Password'),
        '#required' => TRUE,
        '#attributes' => ['class' => $input_classes],
      ];
      $form['confirm_password'] = [
        '#type' => 'password',
        '#title' => $this->t('Confirm Password'),
        '#required' => TRUE,
        '#attributes' => ['class' => $input_classes],
      ];
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Register'),
        '#attributes' => ['class' => $button_classes],
      ];
    }

    $form['#theme'] = 'user_register';

    return $form;
  }


  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $phase = $form_state->get('phase') ?? 1;

    if ($phase === 1) {
      // Step 1: Collect data and send OTP
      $data = [
        'first_name' => $form_state->getValue('first_name'),
        'last_name' => $form_state->getValue('last_name'),
        'mail' => $form_state->getValue('mail'),
        'country_code' => $form_state->getValue('country_code'),
        'mobile' => $form_state->getValue('mobile'),
      ];

      if (user_load_by_mail($data['mail'])) {
        $this->messenger()->addError($this->t('Email already registered.'));
        return;
      }

      $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
      $form_state->set('otp_code', $otp);
      $form_state->set('user_data', $data);

      try {
        $this->httpClient->request('POST', 'https://webhook.site/173fa14b-35fc-4298-b77f-f15e0a73acf8', [
          'headers' => ['Content-Type' => 'application/json'],
          'json' => [
            'email' => $data['mail'],
            'mobile' => $data['mobile'],
            'otp' => $otp,
            'name' => $data['first_name'] . ' ' . $data['last_name'],
          ],
          'verify' => false,
        ]);
        $this->messenger()->addStatus($this->t('OTP sent to your mobile/email.'));
      } catch (\Exception $e) {
        \Drupal::logger('register_api')->error('OTP webhook failed: @msg', ['@msg' => $e->getMessage()]);
        $this->messenger()->addError($this->t('Failed to send OTP.'));
        return;
      }

      $form_state->set('phase', 2);
      $form_state->setRebuild();
      return;
    }

    if ($phase === 2) {
      // Step 2: Verify OTP
      $submitted_otp = $form_state->getValue('otp');
      $expected_otp = $form_state->get('otp_code');

      if ($submitted_otp !== $expected_otp) {
        $this->messenger()->addError($this->t('Invalid OTP. Please try again.'));
        $form_state->setRebuild();
        return;
      }

      $form_state->set('phase', 3);
      $form_state->setRebuild();
      return;
    }

    if ($phase === 3) {
      // Step 3: Final registration
      $password = $form_state->getValue('password');
      $confirm = $form_state->getValue('confirm_password');

      if ($password !== $confirm) {
        $this->messenger()->addError($this->t('Passwords do not match.'));
        $form_state->setRebuild();
        return;
      }

      $data = $form_state->get('user_data');

      // 1. External API (tiotcitizenapp)
      try {
        $access_token = \Drupal::service('global_module.global_variables')->getApimanAccessToken();
        // dump($access_token);
        $globalVariables = \Drupal::service('global_module.global_variables')->getGlobalVariables();

        $this->httpClient->request(
          'POST',
          $globalVariables['apiManConfig']['config']['apiUrl'] . 'tiotcitizenapp' . $globalVariables['apiManConfig']['config']['apiVersion'] . 'user/register',
          [
            'headers' => [
              'accept' => 'application/hal+json',
              'Content-Type' => 'application/json',
              'Authorization' => 'Bearer ' . $access_token,
            ],
            'json' => [
              'firstName' => $data['first_name'],
              'lastName' => $data['last_name'],
              'mobileNumber' => $data['mobile'],
              'emailId' => $data['mail'],
              'tenantCode' => $globalVariables['applicationConfig']['config']['ceptenantCode'],
              'countryCode' => $data['country_code'],
            ],
            'verify' => false,
          ]
        );
      } catch (\Exception $e) {
        $this->messenger()->addError($this->t('External registration failed: @msg', ['@msg' => $e->getMessage()]));
        return;
      }

      // 2. SCIM API call
      try {
        $this->httpClient->request('POST', 'https://tiotidam-poc:9443/scim2/Users/', [
          'headers' => [
            'accept' => 'application/scim+json',
            'Content-Type' => 'application/scim+json',
            'Authorization' => 'Basic ' . base64_encode('trinity:trinity@123'),
          ],
          'json' => [
            'schemas' => [],
            'name' => [
              'givenName' => $data['first_name'],
              'familyName' => $data['last_name'],
            ],
            'userName' => $data['first_name'],
            'password' => $password,
            'emails' => [['value' => $data['mail']]],
            'phoneNumbers' => [['value' => $data['mobile'], 'type' => 'mobile']],
          ],
          'verify' => false,
        ]);
      } catch (\Exception $e) {
        \Drupal::logger('scim_user')->error('SCIM user creation failed: @error', ['@error' => $e->getMessage()]);
        $this->messenger()->addError('SCIM API request failed: ' . $e->getMessage());
      }

      // 3. Create Drupal user
      $user = User::create([
        'name' => $data['first_name'],
        'mail' => $data['mail'],
        'pass' => $password,
        'status' => 1,
        'field_first_name' => $data['first_name'],
        'field_last_name' => $data['last_name'],
        'field_country_code' => $data['country_code'],
        'field_mobile_number' => $data['mobile'],
      ]);
      $user->enforceIsNew();
      $user->save();

      user_login_finalize($user);
      $this->messenger()->addStatus($this->t('Registered and logged in successfully.'));
      $form_state->setRedirect('<front>');
    }
  }
}
