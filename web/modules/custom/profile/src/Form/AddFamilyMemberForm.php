<?php

namespace Drupal\profile\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\global_module\Service\FileUploadService;
class AddFamilyMemberForm extends FormBase
{
  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;
  protected $fileUploadService;
  protected $request;


  public function __construct(FileUploadService $fileUploadService,ClientInterface $http_client,RequestStack $request_stack)
  {
    $this->httpClient = $http_client;
    $this->fileUploadService = $fileUploadService;
    $this->request = $request_stack->getCurrentRequest();
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('global_module.file_upload_service'),
      $container->get('http_client'),
      $container->get('request_stack')

    );
  }
  public function getFormId()
  {
    return 'add_family_member_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['#prefix'] = '<div id="add-family-member-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['#attributes']['class'][] = 'form-sec p-4 lg:px-10 lg:py-12 bg-white text-center lg:text-start s:mb-24 xs:mb-20';

    $form['user_id'] = [
      '#type' => 'hidden',
      '#attributes' => ['id' => 'user_id'],
    ];

    $form['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
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
        'id' => 'first_name',
      ],
      '#prefix' => '<div class="errors-first_name"><div class="relative">',
      '#suffix' => '</div></div>',
    ];

    $form['calendar'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Birth'),
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
          'text-base',
          's:text-sm',
          'xs:text-sm'
        ],
        'placeholder' => 'DD-MM-YYYY',
        'id' => 'calendar',
        'max' => date('Y-m-d'),
      ],
      '#prefix' => '<div class="errors-calendar"><div class="relative">',
      '#suffix' => $this->getDateErrorMarkup($form_state, 'calendar') . '</div></div>',
    ];


    $form['gender'] = [
      '#type' => 'select',
      '#title' => $this->t('Gender'),
      '#required' => TRUE,
      '#options' => [
        // '' => $this->t('Gender'),
        "Male" => $this->t('Male'),
        "Female" => $this->t('Female'),
        "Others" => $this->t('Others'),
      ],
      '#attributes' => [
        'class' => [
          'peer',
          'w-full',
          'lg:max-w-lg',
          'text-base',
          's:text-sm',
          'xs:text-sm',
          'font-medium',
          'select',
          'rounded-lg',
          'border',
          'border-gray-300',
          'px-2.5',
          'pb-2.5',
          'pt-4'
        ],
        'id' => 'gender',
      ],
      '#prefix' => '<div class="errors-gender"><div class="relative">',
      '#suffix' => '</div></div>',
    ];

      $form['relations'] = [
        '#type' => 'select',
        '#title' => $this->t('Relationship'),
        '#required' => TRUE,
        '#options' => [
          '' => $this->t('Relationship*'),
          "Mother" => $this->t('Mother'),
          "Father" => $this->t('Father'),
          "Sister" => $this->t('Sister'),
          "Brother" => $this->t('Brother'),
          "Wife" => $this->t('Wife'),
          "Husband" => $this->t('Husband'),
          "Daughter" => $this->t('Daughter'),
          "Son" => $this->t('Son'),
        ],
        '#attributes' => [
          'class' => [
            'peer',
            'w-full',
            'lg:max-w-lg',
            'text-base',
            's:text-sm',
            'xs:text-sm',
            'font-medium',
            'select',
            'rounded-lg',
            'border',
            'border-gray-300',
            'px-2.5',
            'pb-2.5',
            'pt-4'
          ],
          'id' => 'relations',
        ],
        '#prefix' => '<div class="errors-relations"><div class="relative">',
        '#suffix' => '</div></div>',
      ];

    $form['phone_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mobile Number'),
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
        'id' => 'phone_number',
        // 'onkeypress' => 'return validateNumber(event)',
        'placeholder' => ' ',
      ],
      '#prefix' => '<div class="errors-phone_number"><div class="relative">',
      '#suffix' => '</div></div>',
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email ID'),
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
        'id' => 'email',
      ],
      '#prefix' => '<div class="errors-email"><div class="relative">',
      '#suffix' => '</div></div>',
    ];

    $form['upload_file'] = [
      '#type' => 'file',
      '#title' => $this->t('<span class="font-nevis">Upload Picture</span>'),
      '#description' => $this->t('<span class="text-xs">(Supported file types: JPG, JPEG & PNG, 2MB max)</span>'),
      '#required' => TRUE,
      '#required_error' => $this->t('Please upload a file'),
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg', 'jpeg', 'png'],
        'file_validate_size' => [2 * 1024 * 1024],  // 2MB max
      ],
      '#attributes' => [
        'class' => [
          'peer',
          'w-1/2',
          'lg:max-w-lg',
          'px-2.5',
          'py-2.5',
          // 'pt-4',
          'text-sm',
          'text-medium_dark',
          'bg-transparent',
          'rounded-lg',
          'text-base',
          's:text-sm',
          'xs:text-sm',
          'rounded-lg',
          'border',
          'border-gray-300 '
        ]
      ],
      '#prefix' => '<div class="file-upload-wrapper file-upload-container no-float-label">',
      '#suffix' => '<div class="upload-file-error"></div></div>',
    ];
    


   $form['terms'] = [
  '#type' => 'checkbox',
  '#title' => $this->t('<span>I agree to the <a href="" class="link link-primary text-blue-600 underline hover:text-blue-900" target="_blank">Terms and Conditions</a></span>'),
  '#required' => TRUE,
  '#required_error' => $this->t('You must agree to the Terms and Conditions'),
  '#attributes' => [
    'class' => ['checkbox','w-6', 'h-6', 'rounded', 'cursor-pointer', 'border', 'border-gray-400'],  
    'id' => 'terms',
  ],
  '#prefix' => '<div class="terms-container flex items-center space-x-2 no-float-label relative">',
  '#suffix' => '</div><div class="error-message-wrapper"></div>',
];




    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
       'class' => [
          'btn',
          'btn-warning',
          'lg:h-14',        // height ~56px (close to 50px you had)
          'lg:w-44',        // width ~176px (close to 180px)
          'xs:h-10',        // smaller height on extra-small
          'text-white',     // white text
          'capitalize',
          'text-lg',        // ~18px (close to your 20px)
          'font-semibold',  // font-weight: 600
          'cursor-pointer', // pointer cursor
          'rounded-[10px]', // border-radius 10px
          'bg-[#ffcc00]',   // background color
          'px-[2px]',       // padding left/right 2px
          'pt-[2px]',       // padding top 2px
          'pb-[4px]',       // padding bottom 4px
          'submitBtn',
          'engage-btn',
        ],

      ],
      // '#ajax' => [
      //   'callback' => '::ajaxCallback',
      //   'wrapper' => 'add-family-member-form-wrapper',
      //   'effect' => 'fade',
      // ],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#attributes' => [
        'onclick' => 'window.location.reload()',
        'class' => [
          'btn',
          'bg-transparent',
          'text-black/75',
          'px-14',
          'text-[1.125rem]',
          "font-['Open_Sans']",
          'rounded-[10px]',
          'transition-colors',
          'duration-200',
          'ease-in-out',
          'border',
          'border-black/25',
          'cursor-pointer',
          'inline',
          'font-bold',
          'btn-outline',
          'lg:h-14',
          'lg:w-44',
          'xs:h-10',
          'capitalize',
          'text-medium_dark',
          'button',
          'rounded-lg'
        ],
      ],
    ];
    $form['#theme'] = 'add-family-member';
    $form['#attributes']['class'][] = 'cv-validate-before-ajax';
    $form['#attached']['library'][] = 'profile/add-family-member-library';
    $form['#attached']['library'][] = 'global_module/ajax_loader';
    return $form;
  }

public function submitForm(array &$form, FormStateInterface $form_state) {
  $values = $form_state->getValues();
  $session = \Drupal::request()->getSession();
  $user_data = $session->get('api_redirect_result') ?? [];

 $image_url = NULL;
  $response_data = [];

  // Upload file using custom file upload service

  if (
    isset($_FILES['files']['full_path']['upload_file']) &&
    is_uploaded_file($_FILES['files']['tmp_name']['upload_file'])
  ) {
    $upload_response = $this->fileUploadService->uploadFile($this->request);
    if ($upload_response instanceof \Symfony\Component\HttpFoundation\JsonResponse) {

  // Debug what we really have
      $response_data = json_decode($upload_response->getContent(), true);
      if (!empty($response_data['fileName'])) {
        $image_url = $response_data['fileName'];
      } elseif (!empty($response_data['error'])) {
        $this->messenger()->addError($this->t('File upload error: @error', [
          '@error' => $response_data['error'],
        ]));
        return;
      }
    }
  }


  $access_token = \Drupal::service('global_module.global_variables')->getApimanAccessToken();
  $globalVariables = \Drupal::service('global_module.global_variables')->getGlobalVariables();

  $payload = [
    'name'         => $values['first_name'],
    'dateOfBirth'  => $values['calendar'],
    'gender'       => $values['gender'],
    'relationship' => $values['relations'],
    'phone'        => $values['phone_number'],
    'emailId'      => $values['email'],
    'userId'       => $user_data['userId'] ?? '',
    'imageUrl'     => $image_url,
  ];
// dump($payload);
  \Drupal::logger('profile')->info('Submitting family member: <pre>@payload</pre>', [
    '@payload' => print_r($payload, TRUE),
  ]);

  $endpoint = $globalVariables['apiManConfig']['config']['apiUrl'] .
              'tiotcitizenapp' .
              $globalVariables['apiManConfig']['config']['apiVersion'] .
              'family-members/add-family-member';

  try {
    $response = $this->httpClient->post($endpoint, [
      'json'    => $payload,
      'headers' => [
        'Content-Type'  => 'application/json',
        'Authorization' => 'Bearer ' . $access_token,
      ],
    ]);
    $data = json_decode($response->getBody()->getContents(), true);
    \Drupal::logger('profile')->info('API response: <pre>@response</pre>', [
      '@response' => print_r($data, TRUE),
    ]);

   if ($data['status'] && $data['status'] === true) {
  \Drupal::logger('profile')->info('Redirecting to success route.');
  $form_state->setRedirect('profile.family_success');
} else {
  \Drupal::logger('profile')->info('Redirecting to failure route.');
  $form_state->setRedirect('profile.family_failure');
}
  }
  catch (\Exception $e) {
    \Drupal::logger('profile')->error('API error: @message', ['@message' => $e->getMessage()]);
    $form_state->setRedirect('profile.family_failure');
  }
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
