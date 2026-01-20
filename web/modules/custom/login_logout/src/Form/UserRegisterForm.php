<?php

namespace Drupal\login_logout\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\login_logout\Service\OAuthLoginService;
use Drupal\global_module\Service\GlobalVariablesService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\active_sessions\Service\ActiveSessionService;
use Drupal\global_module\Service\VaultConfigService;
use Drupal\global_module\Service\ApimanTokenService;

class UserRegisterForm extends FormBase
{
  protected $requestStack;
  protected $httpClient;
  protected $oauthLoginService;
  protected $globalVariablesService;
  protected $activeSessionService;
  protected $vaultConfigService;
  protected $apimanTokenService;

  public function __construct(
    RequestStack $requestStack,
    ClientInterface $httpClient,
    OAuthLoginService $oauthLoginService,
    GlobalVariablesService $globalVariablesService,
    ActiveSessionService $activeSessionService,
    VaultConfigService $vaultConfigService,
    ApimanTokenService $apimanTokenService
  )
  {
    $this->requestStack = $requestStack;
    $this->httpClient = $httpClient;
    $this->oauthLoginService = $oauthLoginService;
    $this->globalVariablesService = $globalVariablesService;
    $this->activeSessionService = $activeSessionService;
    $this->vaultConfigService = $vaultConfigService;
    $this->apimanTokenService = $apimanTokenService;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('request_stack'),
      $container->get('http_client'),
      $container->get('login_logout.oauth_login_service'),
      $container->get('global_module.global_variables'),
      $container->get('active_sessions.session_service'),
      $container->get('global_module.vault_config_service'),
      $container->get('global_module.apiman_token_service')
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
          '#default_value' => ($email),
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
          '#maxlength' => 10, // <-- works in Drupal
          '#attributes' => [
            'class' => $input_classes,
            'pattern' => '[0-9]{10}', // exactly 10 digits
            'title' => $this->t('Enter a valid mobile number'),
            'oninput' => 'this.value = this.value.replace(/[^0-9]/g, "").slice(0,10)', // enforce JS side
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

      $username = (string) $data['mail'];
      $uid = \Drupal::currentUser()->id() ?: 0;
      $ip = \Drupal::request()->headers->all()['x-real-ip'][0];
      /**
       * AE4 – Unexpected Quantity Of Characters In Username
       */
      if (strlen($username) < 5 || strlen($username) > 254) {
        \Drupal::logger('secaudit')->warning(
          'AE4: Abnormal username length detected for User Id: @uid, IP: @ip, Length: @length',
          [
            '@uid' => $uid,
            '@ip' => $ip,
            '@length' => strlen($username),
          ]
        );
      }

      /**
       * AE6 – Unexpected Types Of Characters In Username
       * Allow: email-safe characters only
       */
      if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        \Drupal::logger('secaudit')->warning(
          'AE6: Unexpected characters or format in username detected IP: @ip for User ID: @uid',
          [
            '@uid' => $uid,
            '@ip' => $ip,
            '@username_sample' => substr($username, 0, 50),
          ]
        );
      }

      $users = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadByProperties(['mail' => $data['mail']]);

      if ($users) {
        $this->messenger()->addError($this->t('Email already registered.'));
        return;
      }

      // Generate a 6-digit OTP with leading zeros if necessary
      $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
      $form_state->set('otp_code', $otp);
      $form_state->set('user_data', $data);

      try {
        $flood = \Drupal::service('flood');
        // Enhanced Rate Limiting: Use combination of IP, session, and email for rate limiting
        $session_id = \Drupal::service('session')->getId();  // User session ID
        $identifier = 'otp:' . $data['mail'] . ':' . $session_id;  // Combined identifier
        $limit = 1;
        $window = 120;  // 2-minute window for OTP request

        // Check if the user has exceeded the rate limit
        if (!$flood->isAllowed('get_users_limit', $limit, $window, $identifier)) {
          // Calculate remaining wait time
          $last_event = \Drupal::database()->select('flood', 'f')
            ->fields('f', ['timestamp'])
            ->condition('event', 'get_users_limit')
            ->condition('identifier', $identifier)
            ->orderBy('timestamp', 'DESC')
            ->range(0, 1)
            ->execute()
            ->fetchField();

          $remaining = $window;
          if ($last_event) {
            $elapsed = \Drupal::time()->getCurrentTime() - $last_event;
            $remaining = max($window - $elapsed, 0);
          }

          // Show rate limit exceeded message to the user
          \Drupal::messenger()->addError($this->t(
            '<span class="rate-limit-message" data-wait="@time">@msg</span>',
            [
              '@time' => $remaining,
              '@msg' => "Rate limit exceeded. Please wait {$remaining} seconds...",
            ]
          ));

          // Return a JSON response with the rate limit message
          return new JsonResponse([
            "status" => FALSE,
            "message" => "Rate limit exceeded. Please wait {$remaining} seconds.",
          ], 429);
        }

        // Lock mechanism to prevent parallel requests
        $lock_key = 'otp_lock:' . $data['mail']; // Unique lock for the user/email

        // Acquire a lock to prevent parallel submissions
        $lock_service = \Drupal::service('lock');
        if ($lock_service->acquire($lock_key)) {
          // Generate the OTP
          $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
          $form_state->set('otp_code', $otp);
          $form_state->set('user_data', $data);
          $webhook_url = $this->vaultConfigService->getGlobalVariables()['applicationConfig']['config']['otpWebhookUrl'];

          try {
            // Send OTP via secure API (ensure HTTPS and valid provider)
            $this->httpClient->request('POST', $webhook_url, [
              'headers' => ['Content-Type' => 'application/json'],
              'json' => [
                'email' => $data['mail'],
                'mobile' => $data['mobile'],
                'otp' => $otp,
                'name' => $data['first_name'] . ' ' . $data['last_name'],
              ],
              'verify' => FALSE, // Set to true for SSL verification in production
            ]);

            // Notify the user that OTP has been sent
            $this->messenger()->addStatus($this->t('OTP sent to your mobile/email.'));

            // Register the flood event for rate limiting
            $flood->register('get_users_limit', $window, $identifier);

            // Release the lock
            $lock_service->release($lock_key);
          } catch (\Exception $e) {
            // Log the error
            \Drupal::logger('register_api')->error('OTP webhook failed: @msg', ['@msg' => $e->getMessage()]);

            // Show a generic error message
            $this->messenger()->addError($this->t('Failed to send OTP. Please try again later.'));

            // Release the lock even if sending OTP fails
            $lock_service->release($lock_key);

            return new JsonResponse([
              "status" => FALSE,
              "message" => "An error occurred while processing your request. Please try again later.",
            ], 500);
          }
        } else {
          // Handle failure to acquire lock (i.e., too many parallel requests)
          $this->messenger()->addError($this->t('Unable to process OTP request. Please try again.'));
          return new JsonResponse([
            "status" => FALSE,
            "message" => "Unable to process your request at the moment. Please try again later.",
          ], 503);
        }
      } catch (\Exception $e) {
        // Log the error in case of an unexpected failure
        \Drupal::logger('register_api')->error('OTP rate limit or lock error: @msg', ['@msg' => $e->getMessage()]);

        // Show a generic error message
        $this->messenger()->addError($this->t('An unexpected error occurred. Please try again later.'));
        return new JsonResponse([
          "status" => FALSE,
          "message" => "An unexpected error occurred. Please try again later.",
        ], 500);
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

      $uid = \Drupal::currentUser()->id() ?: 0;
      $ip = \Drupal::request()->headers->all()['x-real-ip'][0];
      /**
       * AE5 – Unexpected Quantity Of Characters In Password
       */
      $len = strlen((string) $password);
      if ($len < 8 || $len > 128) {
        \Drupal::logger('secaudit')->warning(
          'AE5: Abnormal password length detected for User Id: @uid, IP: @ip, Length: @length',
          [
            '@uid' => $uid,
            '@ip' => $ip,
            '@length' => $len,
          ]
        );
      }

      /**
       * AE7 – Unexpected Types Of Characters In Password
       * Allow common secure password characters
       */
      if (preg_match('/[\x00-\x1F\x7F]/', (string) $password)) {
        \Drupal::logger('secaudit')->warning(
          'AE7: Control characters detected in password for User Id: @uid, IP: @ip',
          [
            '@uid' => $uid,
            '@ip' => $ip,
          ]
        );
      }

      if ($password !== $confirm) {
        $this->messenger()->addError($this->t('Passwords do not match.'));
        $form_state->setRebuild();
        return;
      }

      $data = $form_state->get('user_data');

      // 1. External API (tiotcitizenapp)
      try {
        $access_token = $this->apimanTokenService->getApimanAccessToken();
        $globalVariables = $this->vaultConfigService->getGlobalVariables();
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
            'verify' => FALSE,
          ]
        );
      } catch (\GuzzleHttp\Exception\RequestException $e) {
        $this->messenger()->addError($this->t('Registration failed: @msg', ['@msg' => json_decode($e->getResponse()->getBody()->getContents(), TRUE)['developerMessage']]));
        return;
      }

      // 2. SCIM API call
      try {
        $idamconfig = $this->vaultConfigService->getGlobalVariables()['applicationConfig']['config']['idamconfig'];
        $this->httpClient->request('POST', 'https://' . $idamconfig . '/scim2/Users/', [
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
          'verify' => FALSE,
        ]);
      } catch (\GuzzleHttp\Exception\RequestException $e) {
        \Drupal::logger('scim_user')->error('SCIM user creation failed: @error', ['@error' => $e->getMessage()]);
        $err_msg = explode('-', json_decode($e->getResponse()->getBody()->getContents(), TRUE)['detail']);
        $this->messenger()->addError('Error: ' . $err_msg[1]);
      }

      // Step 1: Get Flow ID
      $flowId = $this->oauthLoginService->getFlowId();
      if (!$flowId) {
        $this->messenger()->addError($this->t('Flow ID not received.'));
        return;
      }

      // Step 2: Authenticate user
      $authorizationCode = $this->oauthLoginService->authenticateUser($flowId, $data['mail'], $password);
      if (!$authorizationCode['code']) {
        $this->messenger()->addError($this->t($authorizationCode['message']));
        return;
      }

      $tokenData = $this->oauthLoginService->exchangeCodeForToken($authorizationCode['code']);
      if (empty($tokenData['access_token']) || empty($tokenData['id_token'])) {
        $this->messenger()->addError($this->t('Token not received.'));
        return;
      }

      // Store in session
      $session = \Drupal::service('session');
      $session->set('login_logout.access_token', $tokenData['access_token']);
      $session->set('login_logout.id_token', $tokenData['id_token']);

      // Step 4: Decode JWT to get email (or sub claim)
      $parts = explode('.', $tokenData['id_token']);
      if (count($parts) !== 3) {
        throw new \Exception('Invalid JWT token format.');
      }
      $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), TRUE);
      if (empty($payload['sub'])) {
        throw new \Exception('JWT payload missing "sub" claim.');
      }
      $login_time = \Drupal::time()->getRequestTime(); // seconds

      // Get access token
      $accessToken = $tokenData['access_token'] ?? '';

      $activeSessions = $this->activeSessionService->fetchActiveSessions($accessToken);
      $session->set('login_logout.login_time', \Drupal::time()->getRequestTime());

      // Find closest matching API session by loginTime
      $closestSessionId = NULL;
      $closestDiff = PHP_INT_MAX;
      $targetTimeMs = $login_time * 1000;

      if (!empty($activeSessions['sessions'])) {
        foreach ($activeSessions['sessions'] as $session) {
          if (!empty($session['loginTime'])) {
            $diff = abs($session['loginTime'] - $targetTimeMs);
            if ($diff < $closestDiff) {
              $closestDiff = $diff;
              $closestSessionId = $session['id']; // <-- this is what we want
            }
          }
        }
      }

      // Fallback if nothing matched
      $session = \Drupal::service('session');
      $session->set('login_logout.active_session_id_token', $closestSessionId);

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
