<?php

namespace Drupal\login_logout\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserAuthInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\login_logout\Service\OAuthLoginService;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Database\Connection;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

class UserLoginForm extends FormBase
{

  protected $currentUser;
  protected $userAuth;
  protected $sessionManager;
  protected $requestStack;
  protected $httpClient;
  protected $oauthLoginService;
  protected $database;

  public function __construct(AccountProxyInterface $currentUser, UserAuthInterface $userAuth, SessionManagerInterface $sessionManager, RequestStack $requestStack, ClientInterface $httpClient, OAuthLoginService $oauthLoginService, Connection $database)
  {
    $this->oauthLoginService = $oauthLoginService;
    $this->currentUser = $currentUser;
    $this->userAuth = $userAuth;
    $this->sessionManager = $sessionManager;
    $this->requestStack = $requestStack;
    $this->httpClient = $httpClient;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('current_user'),
      $container->get('user.auth'),
      $container->get('session_manager'),
      $container->get('request_stack'),
      $container->get('http_client'),
      $container->get('login_logout.oauth_login_service'),
      $container->get('database')
    );
  }

  public function getFormId()
  {
    return 'user_login_email_first';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {

    if (!$form_state->isRebuilding() && !$form_state->isSubmitted()) {
      $form_state->set('email_validated', FALSE);
    }
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#attributes' => [
        'placeholder' => $this->t('Email'),
      ],
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('email'),
    ];

    if ($form_state->get('email_validated')) {
      $form['password'] = [
        '#type' => 'password',
        '#title' => $this->t('Password'),
        '#attributes' => [
          'placeholder' => $this->t('Password'),
        ],
        '#required' => TRUE,
      ];

      $form['login'] = [
        '#type' => 'submit',
        '#value' => $this->t('Login'),
      ];
    } else {
      $form['check_email'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ];
    }


    $form['email']['#attributes']['class'][] = 'w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-400';
    $form['password']['#attributes']['class'][] = 'w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-400';

    $form['login']['#attributes']['class'][] = 'bg-yellow-500 text-white rounded-xl px-6 py-2 cursor-pointer hover:bg-yellow-600 transition';
    // $form['check_email']['#attributes']['class'][] = 'w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition';
    $form['check_email']['#attributes']['class'][] = 'bg-yellow-500 text-white rounded-xl px-6 py-2 cursor-pointer hover:bg-yellow-600 transition';
    $form['#theme'] = 'user_login';

    $form['#attached']['library'][] = 'login_logout/user-login-library';
    // $form['#attached']['library'][] = 'login_logout/capture_browser_info';
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $email = $form_state->getValue('email');

    if ($form_state->get('email_validated')) {
      // Password entered — proceed with OAuth login.
      $password = $form_state->getValue('password');

      try {
        // Step 1: Get Flow ID
        $flowId = $this->oauthLoginService->getFlowId();
        if (!$flowId) {
          $this->messenger()->addError($this->t('Flow ID not received.'));
          return;
        }

        // Step 2: Authenticate user
        $authorizationCode = $this->oauthLoginService->authenticateUser($flowId, $email, $password);
        if (!$authorizationCode) {
          $this->messenger()->addError($this->t('Authorization code not received.'));
          return;
        }

        // Step 3: Exchange code for token
        $tokenData = $this->oauthLoginService->exchangeCodeForToken($authorizationCode);
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
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (empty($payload['sub'])) {
          throw new \Exception('JWT payload missing "sub" claim.');
        }
        $jwtEmail = $payload['sub'];

        $login_time = \Drupal::time()->getRequestTime(); // seconds

        // Get access token
        $accessToken = $tokenData['access_token'] ?? '';

        $activeSessionService = \Drupal::service('active_sessions.session_service');
        $activeSessions = $activeSessionService->fetchActiveSessions($accessToken);

        // Find closest matching API session by loginTime
        $closestSessionId = null;
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
        
        // Step 5: Load Drupal user and log in
        $users = \Drupal::entityTypeManager()
          ->getStorage('user')
          ->loadByProperties(['mail' => $jwtEmail]);
        $user = reset($users);

        if ($user instanceof UserInterface) {
          user_login_finalize($user);
          $this->messenger()->addStatus($this->t('Successfully logged in as @mail', ['@mail' => $jwtEmail]));
          $form_state->setRedirect('<front>');
        } else {
          $this->messenger()->addError($this->t('No Drupal user found for @mail', ['@mail' => $jwtEmail]));
        }
      } catch (\Exception $e) {
        \Drupal::logger('login_logout')->error('OAuth2 login failed: @msg', ['@msg' => $e->getMessage()]);
        $this->messenger()->addError($this->t('Login failed: @msg', ['@msg' => $e->getMessage()]));
      }
    } else {
      // Step 0: First-time email validation
      try {
        $globalVariablesService = \Drupal::service('global_module.global_variables');
        $accessToken = $globalVariablesService->getApimanAccessToken();
        $globals = $globalVariablesService->getGlobalVariables();

        $apiUrl = $globals['apiManConfig']['config']['apiUrl'] ?? '';
        $apiVersion = $globals['apiManConfig']['config']['apiVersion'] ?? '';

        if ($this->oauthLoginService->checkEmailExists($email, $accessToken, $apiUrl, $apiVersion)) {
          $form_state->set('email_validated', TRUE)->setRebuild();
        } else {
          $form_state->setRedirect('login_logout.user_register_form', [], [
            'query' => ['email' => base64_encode($email)]
          ]);
        }
      } catch (\Exception $e) {
        $this->messenger()->addError($this->t('Error checking email: @msg', ['@msg' => $e->getMessage()]));
      }
    }
  }
}
