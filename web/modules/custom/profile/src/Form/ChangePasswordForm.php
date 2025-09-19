<?php

namespace Drupal\profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\user\Entity\User;
use Drupal\Core\Url;

class ChangePasswordForm extends FormBase
{
  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  public function __construct(ClientInterface $http_client)
  {
    $this->httpClient = $http_client;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('http_client')
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

    $form['#attributes']['class'][] = 'form-sec lg:px-10 text-center lg:text-start s:mb-24 xs:mb-20';

    $form['old_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Old Password'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => [
          'peer',
          'w-full',
          'lg:max-w-lg',
          'px-2.5',
          'pb-2.5',
          'pt-4',
          'text-sm',
          'text-medium_dark',
          'bg-transparent',
          'rounded-lg',
          'border',
          'border-gray-300',
          'appearance-none',
          'text-base',
          's:text-sm',
          'xs:text-sm'
        ],
        'placeholder' => ' ',
        'autocomplete' => 'off',
        'id' => 'old-password',
      ],
      '#prefix' => '<div class="errors-old-password"><div class="relative">',
      '#suffix' => '</div></div>',
    ];

    $form['new_password'] = [
      '#type' => 'password',
      '#title' => $this->t('New Password'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => [
          'peer',
          'w-full',
          'lg:max-w-lg',
          'text-base',
          's:text-sm',
          'xs:text-sm',
          'rounded-lg',
          'border',
          'border-gray-300',
          'px-2.5',
          'pb-2.5',
          'pt-4'
        ],
        'maxlength' => 10,
        'minlength' => 10,
        'id' => 'new-password',
        // 'onkeypress' => 'return validateNumber(event)',
        'placeholder' => ' ',
      ],
      '#prefix' => '<div class="errors-new-password"><div class="relative">',
      '#suffix' => '</div></div>',
    ];

    $form['confirm_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Confirm Password'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => [
          'peer',
          'w-full',
          'lg:max-w-lg',
          'text-base',
          's:text-sm',
          'xs:text-sm',
          'rounded-lg',
          'border',
          'border-gray-300',
          'px-2.5',
          'pb-2.5',
          'pt-4'
        ],
        'placeholder' => ' ',
        'id' => 'confirm-password',
      ],
      '#prefix' => '<div class="errors-confirm-password"><div class="relative">',
      '#suffix' => '</div></div>',
    ];



    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Continue'),
      '#attributes' => [
        'class' => [
          'btn',
          'btn-warning',
          'lg:h-14',
          'lg:w-44',
          'xs:h-10',
          'text-white',
          'capitalize',
          'text-lg',
          'submitBtn',
          'engage-btn'
        ],
      ],
    ];
    $form['#theme'] = 'change-password';
    $form['#attached']['library'][] = 'profile/change-password-library';
    return $form;
  }


  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $oldPass = $form_state->getValue('old_password');
    $newPass = $form_state->getValue('new_password');
    $confirmPass = $form_state->getValue('confirm_password');

    \Drupal::logger('change_password')->notice('Submitting change password form for user ID: @uid', [
      '@uid' => \Drupal::currentUser()->id()
    ]);

    $resultPassdata = ['status' => false, 'message' => 'Something went wrong'];

    try {
      $access_token = \Drupal::service('global_module.global_variables')->getApimanAccessToken();
      $globalVariables = \Drupal::service('global_module.global_variables')->getGlobalVariables();

      // $idamClientId = $globalVariables['applicationConfig']['config']['idamClientId'];
      $idamClientId = 'Ap1tGcg_RSKEar5ueCH58XJujKUa';
      $user = User::load(\Drupal::currentUser()->id());
      $email = $user->get('mail')->value;

      $payload = [
        "schemas" => ["urn:ietf:params:scim:api:messages:2.0:SearchRequest"],
        "filter" => "userName eq $email"
      ];

      \Drupal::logger('change_password')->notice('Sending filterUser payload: <pre>@payload</pre>', [
        '@payload' => print_r($payload, TRUE)
      ]);

      // $responseData = \Drupal::service('global_module.global_variables')->curl_post_apiman(
      //   $globalVariables['apiManConfig']['config']['apiUrl'] . 'tiotweb' . 
      //   $globalVariables['apiManConfig']['config']['apiVersion'] . 'filterUser',
      //   $payload
      // );
      $url = 'https://hcsjointstacknew.trinityiot.in/scim2/Users?filter=' . urlencode("emails eq \"$email\"");

      $responseData = \Drupal::service('global_module.global_variables')->curl_get_api($url);
      // dump($responseData);

      if (!empty($responseData['Resources'][0]['id'])) {
        $idamUserId = $responseData['Resources'][0]['id'];
        $payloadoldPass = [
          // "grantType" => "password",
          "grant_type" => "password",
          "password" => $oldPass,
          "client_id" => "hVBu5NSpBJHJ84KF70nfQ8ZMdnQa",
          "username" => $email
        ];


        \Drupal::logger('change_password')->notice('Verifying old password payload: <pre>@payload</pre>', [
          '@payload' => print_r($payloadoldPass, TRUE)
        ]);

        // $resoldpass = \Drupal::service('global_module.global_variables')->curl_post_apiman(
        //   $globalVariables['apiManConfig']['config']['apiUrl'] . 'tiotweb' . 
        //   $globalVariables['apiManConfig']['config']['apiVersion'] . 'verifyPassword',
        //   $payloadoldPass
        // );
        $resoldpass = \Drupal::service('global_module.global_variables')->curl_post_idam(
          'https://hcsjointstacknew.trinityiot.in/oauth2/token/',
          $payloadoldPass
        );
        //   dump($resoldpass);exit;

        if (!empty($resoldpass['access_token'])) {
          $payloadPass = [
            "schemas" => ["urn:ietf:params:scim:schemas:extension:enterprise:2.0:User"],
            "Operations" => [[
              "op" => "replace",
              "path" => "password",
              "value" => $newPass
            ]]
          ];

          \Drupal::logger('change_password')->notice('Updating password payload: <pre>@payload</pre>', [
            '@payload' => print_r($payloadPass, TRUE)
          ]);

          // $respass = \Drupal::service('global_module.global_variables')->curl_post_apiman(
          //   $globalVariables['apiManConfig']['config']['apiUrl'] . 'tiotweb' . 
          //   $globalVariables['apiManConfig']['config']['apiVersion'] . 'UpdatePassword/' . $idamUserId,
          //   $payloadPass,
          //   'PATCH'
          // );
          $respass = \Drupal::service('global_module.global_variables')->curl_post_idam_auth(
            'https://hcsjointstacknew.trinityiot.in/scim2/Users/' . $idamUserId,
            $payloadPass,
            'PATCH'
          );

          \Drupal::logger('change_password')->notice('Update password response: <pre>@res</pre>', [
            '@res' => print_r($respass, TRUE)
          ]);

          if (!empty($respass['emails'][0]) && $respass['emails'][0] === $email) {
            $resultPassdata = ['status' => true, 'message' => 'Password updated successfully!'];
          } elseif (!empty($respass['detail'])) {
            $resultPassdata = ['status' => false, 'message' => $respass['detail']];
          } else {
            $resultPassdata = ['status' => false, 'message' => 'Password not updated!'];
          }
        } else {
          $resultPassdata = ['status' => false, 'message' => 'Old password not matching!'];
        }
      } else {
        \Drupal::logger('change_password')->error('User ID not found in SCIM filter response.');
        $resultPassdata = ['status' => false, 'message' => 'User not found in SCIM.'];
      }
    } catch (\Exception $e) {
      \Drupal::logger('change_password')->error('Exception: @msg', ['@msg' => $e->getMessage()]);
      $resultPassdata = ['status' => false, 'message' => 'Unexpected error occurred.'];
    }

    // Final logging
    \Drupal::logger('change_password')->notice('Final result: <pre>@result</pre>', [
      '@result' => print_r($resultPassdata, TRUE)
    ]);

    // Redirect to /response-status with query string
    $status = !empty($resultPassdata['status']) ? 1 : 0;
    $message = $resultPassdata['message'] ?? 'Something went wrong.';
    $redirect = 'change-password';

    $form_state->setRedirect('global_module.status', [], [
      'query' => [
        'status' => $status,
        'message' => $message,
        'formData' => $redirect,
      ],
    ]);;
    // $response = new AjaxResponse();
    // $response->addCommand(new RedirectCommand($url));
    // return $response;
  }




  private function getDateErrorMarkup(FormStateInterface $form_state, $field_name)
  {
    $errors = $form_state->getErrors();
    if (isset($errors[$field_name])) {
      return '<p class="text-red-600 text-sm mt-1">' . $errors[$field_name] . '</p>';
    }
    return '';
  }
  public function ajaxCallback(array &$form, FormStateInterface $form_state)
  {
    if ($form_state->hasAnyErrors()) {
      // Return the entire form to show errors, but do NOT process submit
      return $form;
    }
    return $form;
  }
}
