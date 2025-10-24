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
use Drupal\global_module\Service\GlobalVariablesService;
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
  protected $globalVariablesService;

  public function __construct(AccountProxyInterface $currentUser, UserAuthInterface $userAuth, SessionManagerInterface $sessionManager, RequestStack $requestStack, ClientInterface $httpClient, OAuthLoginService $oauthLoginService, Connection $database, GlobalVariablesService $globalVariablesService)
  {
    $this->oauthLoginService = $oauthLoginService;
    $this->currentUser = $currentUser;
    $this->userAuth = $userAuth;
    $this->sessionManager = $sessionManager;
    $this->requestStack = $requestStack;
    $this->httpClient = $httpClient;
    $this->database = $database;
    $this->globalVariablesService = $globalVariablesService;
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
      $container->get('database'),
      $container->get('global_module.global_variables')
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
      // '#attributes' => [
      //   'placeholder' => $this->t('Email'),
      // ],
      '#attributes' => [
        'placeholder' => $this->t('Email'),
        'onpaste' => 'return false;',
        'oncopy' => 'return false;',
        'oncut' => 'return false;',
        'autocomplete' => 'off',
      ],
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('email'),
    ];

    if ($form_state->get('email_validated')) {
      $form['password'] = [
        '#type' => 'password',
        '#title' => $this->t('Password'),
        // '#attributes' => [
        //   'placeholder' => $this->t('Password'),
        // ],
        '#attributes' => [
          // 'placeholder' => $this->t('Password'),
          'onpaste' => 'return false;',
          'oncopy' => 'return false;',
          'oncut' => 'return false;',
          'autocomplete' => 'new-password',
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


    $form['email']['#attributes']['class'][] = 'w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-0 focus:!border-yellow-500';
    $form['password']['#attributes']['class'][] = 'w-full px-4 py-2 border border-gray-300 rounded focus:outline-none focus:ring-0 focus:!border-yellow-500';

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
    $session = \Drupal::service('session');
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');

    if (!$form_state->get('email_validated')) {
      try {
        if ($this->oauthLoginService->validateEmail($email)) {
          $form_state->set('email_validated', TRUE)->setRebuild();
        } else {
          $tempstore = \Drupal::service('tempstore.private')->get('login_logout');
          $tempstore->set('registration_email', $email);
          $form_state->setRedirect('login_logout.user_register_form');
        }
      } catch (\Exception $e) {
        $this->messenger()->addError($this->t('Error checking email: @msg', ['@msg' => $e->getMessage()]));
      }
      return;
    }

    $password = $form_state->getValue('password');
    try {
      $tokenData = $this->oauthLoginService->performOAuthLogin($email, $password);

      $session->set('login_logout.access_token', $tokenData['access_token']);
      $session->set('login_logout.id_token', $tokenData['id_token']);
      $session->set('login_logout.login_time', \Drupal::time()->getRequestTime());

      $jwtEmail = $this->oauthLoginService->extractEmailFromJwt($tokenData['id_token']);
      if (!$jwtEmail) {
        throw new \Exception('JWT payload missing "sub" claim.');
      }

      $user = reset($user_storage->loadByProperties(['mail' => $jwtEmail]));

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
  }
}
