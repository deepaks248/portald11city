<?php

namespace Drupal\login_logout\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserAuthInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use GuzzleHttp\ClientInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

class UserLoginForm extends FormBase
{

  protected $currentUser;
  protected $userAuth;
  protected $sessionManager;
  protected $requestStack;
  protected $httpClient;

  public function __construct(AccountProxyInterface $currentUser, UserAuthInterface $userAuth, SessionManagerInterface $sessionManager, RequestStack $requestStack, ClientInterface $httpClient)
  {
    $this->currentUser = $currentUser;
    $this->userAuth = $userAuth;
    $this->sessionManager = $sessionManager;
    $this->requestStack = $requestStack;
    $this->httpClient = $httpClient;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('current_user'),
      $container->get('user.auth'),
      $container->get('session_manager'),
      $container->get('request_stack'),
      $container->get('http_client')
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
      // '#title' => $this->t('Email'),
      '#attributes'=>[
        'placeholder' => $this->t('Email'),
      ],
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('email'),
    ];

    if ($form_state->get('email_validated')) {
      $form['password'] = [
        '#type' => 'password',
        // '#title' => $this->t('Password'),
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
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $email = $form_state->getValue('email');

    if ($form_state->get('email_validated')) {
      $password = $form_state->getValue('password');

      try {
        // Step 1: Get flowId
        $response = $this->httpClient->request('POST', 'https://tiotidam:9443/oauth2/authorize', [
          'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
          ],
          'form_params' => [
            'client_id' => 'hVBu5NSpBJHJ84KF70nfQ8ZMdnQa',
            'response_type' => 'code',
            'redirect_uri' => 'https://cityportal.ddev.site/',
            'scope' => 'openid',
            'response_mode' => 'direct',
          ],
          'verify' => false,
        ]);
        // dump("RES!", $response);
        $authorizeResult = json_decode($response->getBody()->getContents(), TRUE);
        // dump($authorizeResult);
        \Drupal::logger('login_logout')->notice('Authorize response: <pre>@data</pre>', ['@data' => print_r($authorizeResult, TRUE)]);

        if (!empty($authorizeResult['flowId'])) {
          $flowId = $authorizeResult['flowId'];

          // Step 2: Authenticate user
          $payload = [
            "flowId" => $flowId,
            "selectedAuthenticator" => [
              "authenticatorId" => "QmFzaWNBdXRoZW50aWNhdG9yOkxPQ0FM",
              "params" => [
                "username" => $email,
                "password" => $password,
              ],
            ],
          ];
          // dump($payload);
          $response1 = $this->httpClient->request('POST', 'https://tiotidam:9443/oauth2/authn', [
            'headers' => [
              'Accept' => 'application/json',
              'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'verify' => false,
          ]);
          // dump("RES", $response1);
          $authnResult = json_decode($response1->getBody()->getContents(), TRUE);
          // dump("AUth", $authnResult);
          \Drupal::logger('login_logout')->notice('Authn response: <pre>@data</pre>', ['@data' => print_r($authnResult, TRUE)]);

          if (!empty($authnResult['authData']['code'])) {
            $authorizationCode = $authnResult['authData']['code'];

            // Step 3: Exchange code for token
            $response = $this->httpClient->request('POST', 'https://tiotidam:9443/oauth2/token', [
              'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
              ],
              'form_params' => [
                'client_id' => 'hVBu5NSpBJHJ84KF70nfQ8ZMdnQa',
                'grant_type' => 'authorization_code',
                'redirect_uri' => 'https://cityportal.ddev.site/',
                'code' => $authorizationCode,
              ],
              'verify' => false,
            ]);

            $tokenResult = json_decode($response->getBody()->getContents(), TRUE);
            \Drupal::logger('login_logout')->notice('Token response: <pre>@data</pre>', ['@data' => print_r($tokenResult, TRUE)]);
            // dump("LastResult", $tokenResult);
            // ✅ Token received — store or use as needed
            $accessToken = $tokenResult['access_token'] ?? NULL;
            $parts = explode('.', $tokenResult['id_token']);
            if (count($parts) !== 3) {
              throw new \Exception('Invalid JWT token format.');
            }

            // Base64-decode the payload (second part)
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            if (!$payload || !isset($payload['sub'])) {
              throw new \Exception('JWT payload missing or sub not found.');
            }

            $email = $payload['sub'];
            \Drupal::logger('login_logout')->debug('Decoded JWT: <pre>@data</pre>', [
              '@data' => print_r($payload, TRUE),
            ]);

            $users = \Drupal::entityTypeManager()
              ->getStorage('user')
              ->loadByProperties(['mail' => $email]);

            $user = reset($users);
            \Drupal::logger('login_logout')->debug('Decoded JWT: <pre>@data</pre>', [
              '@data' => print_r($user, TRUE),
            ]);

            if ($user instanceof UserInterface) {
              user_login_finalize($user);
              // $this->messenger()->addStatus($this->t('Successfully logged in as @mail', ['@mail' => $email]));
              $form_state->setRedirect('<front>');
            } else {
              $this->messenger()->addError($this->t('No valid user entity found for @mail', ['@mail' => $email]));
            }
            if ($accessToken) {
              // $this->messenger()->addStatus($this->t('Logged in and token received.'));
              // You can store the token in session, user data, etc.
              $form_state->setRedirect('<front>');
            } else {
              $this->messenger()->addError($this->t('Token not received.'));
            }
          } else {
            $this->messenger()->addError($this->t('Authorization code not received.'));
          }
        } else {
          $this->messenger()->addError($this->t('Flow ID not received from /authorize endpoint.'));
        }
      } catch (\Exception $e) {
        \Drupal::logger('login_logout')->error('OAuth2 flow failed: @message', ['@message' => $e->getMessage()]);
        $this->messenger()->addError($this->t('OAuth2 login failed. Please try again.'));
      }
    } else {
      // First step: Call external API to check email existence
      try {
        $access_token = \Drupal::service('global_module.global_variables')->getApimanAccessToken();
        $globalVariables = \Drupal::service('global_module.global_variables')->getGlobalVariables();
        \Drupal::logger('login_logout')->debug('Global Variables: <pre>@data</pre>', [
          '@data' => print_r($globalVariables, true),
        ]);
        $baseUrl = $globalVariables['apiManConfig']['config']['apiUrl'] ?? '';
        $version = $globalVariables['apiManConfig']['config']['apiVersion'] ?? '';
        $fullUrl = $baseUrl . 'tiotcitizenapp' . $version . 'user/details';
        if (empty($fullUrl) || !filter_var($fullUrl, FILTER_VALIDATE_URL)) {
          throw new \InvalidArgumentException('Invalid API URL: ' . print_r($fullUrl, true));
        }

        $response = $this->httpClient->request("POST", $fullUrl, [
          'json' => ['userId' => $email],
          'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
          ],
        ]);


        $data = json_decode($response->getBody()->getContents(), TRUE);
        // dump($data);
        if (!empty($data['data'])) {
          // Email exists - show password field
          $form_state->set('email_validated', TRUE);
          $form_state->setRebuild();
        } else {
          // Redirect to register form with email
          $form_state->setRedirect('login_logout.user_register_form', [], ['query' => ['email' => base64_encode($email)]]);
        }
      } catch (\Exception $e) {
        $this->messenger()->addError($this->t('Error checking email: @message', ['@message' => $e->getMessage()]));
      }
    }
  }
}
