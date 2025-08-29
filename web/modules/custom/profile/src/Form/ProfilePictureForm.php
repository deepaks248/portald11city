<?php

namespace Drupal\profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ProfilePictureForm extends FormBase
{

  public function getFormId()
  {
    return 'profile_picture_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $session = \Drupal::request()->getSession();
    $user_data = $session->get('api_redirect_result') ?? [];

    $form['#attributes']['enctype'] = 'multipart/form-data';
    $form['#method'] = 'post';
    $form['#action'] = '';

    $form['profile_picture_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'profile-form-wrapper'],
    ];

    $form['profile_picture_wrapper']['profile_picture'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['avatar', 'flex', 'flex-col', 'items-center', 'relative'],
      ],
    ];

    $profile_pic = (!empty($user_data['profilePic']) && $user_data['profilePic'] !== "null")
      ? htmlspecialchars($user_data['profilePic'], ENT_QUOTES, 'UTF-8')
      : '/themes/custom/engage_theme/images/Profile/profile_pic.png';

    $form['image'] = [
      '#type' => 'markup',
      '#markup' => '
        <div class="w-28 rounded-full mb-3 aspect-square block overflow-hidden">
          <img src="' . $profile_pic . '" class="h-full w-full object-cover profilePicSrc" alt="Profile Image">
        </div>',
    ];

    // $form['user_id'] = [
    //   '#type' => 'markup',
    //   '#markup' => '<div class="userId_no"><p class="text-[13px] text-[#646262]">User Id :- ' . ($user_data['userId'] ?? '') . '</p></div>',
    // ];

    $form['edit_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#value' => 'Edit Profile Picture',
      '#attributes' => [
        'for' => 'profilePic',
        'class' => ['text-sm', 'font-bold', 'font-[Open_Sans]', 'border-2', 'px-4', 'cursor-pointer', 'translateLabel'],
        'label-alias' => 'la_edit_profile_picture',
      ],
    ];

    $form['upload_file'] = [
      '#type' => 'file',
      '#attributes' => [
        'onchange' => "fileUpload(this, 'profilePic')",
        'class' => ['form-control', 'profilePic', 'invisible', 'hidden'],
        'id' => 'profilePic',
        'accept' => 'image/*',
      ],
      '#name' => 'upload_file',
    ];

    $form['profilePic_filename'] = [
      '#type' => 'hidden',
      '#attributes' => ['class' => ['form-control', 'profilePic_name'], 'id' => 'profilePic_name'],
      '#name' => 'profilePic_filename',
    ];

    $form['remove'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Remove'),
      '#attributes' => [
        'type' => 'button', // 🚨 Prevents form submission
        'id' => 'remove-profile-picture',
        'class' => [
          'removeImg',
          'text-sm',
          'font-bold',
          'font-[Open_Sans]',
          'border-2',
          'px-4',
          'py-0.5',
          'cursor-pointer',
          'translateLabel',
        ],
        'data-modal-target' => 'remove-profile-picture-modal',
        'data-modal-toggle' => 'remove-profile-picture-modal',
        'engage-button' => 'engage-button-modal',
      ],
    ];

    $form['note'] = [
      '#type' => 'markup',
      '#markup' => '<p class="flex justify-center text-[#a0a0a0] supportP text-sm font-[Open_Sans] font-bold my-4">(Supported file types: JPEG, PNG & file size limit is 2MB)</p>',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // No-op: form uses AJAX-only actions.
  }

  public function ajaxCallback(array &$form, FormStateInterface $form_state)
  {
    \Drupal::logger('profile_picture_form')->debug('AJAX Remove callback triggered.');

    // Get session data (like email/mobile from API call)
    $session = \Drupal::request()->getSession();
    $user_data = $session->get('api_redirect_result') ?? [];
    $first_name = $user_data['firstName'];
    $last_name = $user_data['lastName'];
    $email = $user_data['emailId'] ?? '';
    $mobile = $user_data['mobileNumber'] ?? '';
    $user_id = $user_data['userId'] ?? '';
    $payload = [
      'firstName' => $first_name,
      'lastName' => $last_name,
      'emailId' => $email,
      'mobileNumber' => $mobile,
      'tenantCode' => $user_data['tenantCode'],
      'profilePic' => 'null',
      'userId' => $user_id
    ];

    try {
      $access_token = \Drupal::service('global_module.global_variables')->getApimanAccessToken();
      $globalVariables = \Drupal::service('global_module.global_variables')->getGlobalVariables();
      $client = \Drupal::httpClient();

      $response = $client->post(
        $globalVariables['apiManConfig']['config']['apiUrl'] . 'tiotcitizenapp' . $globalVariables['apiManConfig']['config']['apiVersion'] . 'user/update',
        [
          'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
          ],
          'json' => $payload,
        ]
      );

      $data = json_decode($response->getBody(), true);
      if (!empty($data['status'])) {
        $session->remove('api_redirect_result');
        \Drupal::logger('profile')->notice('Profile removed successfully.');
        // $form_state->setRebuild();
        // $form_state->set('show_success_popup', TRUE);
      } else {
        \Drupal::logger('profile')->notice('Failed to remove profile');
      }
    } catch (\Exception $e) {
      \Drupal::logger('profile_form')->error('API Error: @message', ['@message' => $e->getMessage()]);
      \Drupal::logger('profile_form')->error($this->t('API Error. Please try again later.'));
    }
    $form['image']['#markup'] = '
      <div class="w-28 rounded-full mb-3 aspect-square block overflow-hidden">
        <img src="/themes/custom/engage_theme/images/Profile/profile_pic.png" class="h-full w-full object-cover profilePicSrc" alt="Default Image">
      </div>';

    $form['profilePic_filename']['#value'] = '';

    \Drupal::logger('profile_picture')->notice('Profile picture removed.');

    return $form['profile_picture_wrapper'];
  }
}
