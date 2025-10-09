<?php

namespace Drupal\reportgrievance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\reportgrievance\Service\GrievanceApiService;
use Drupal\global_module\Service\FileUploadService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Cache\CacheBackendInterface;

class ReportGrievanceForm extends FormBase
{

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * @var \Drupal\reportgrievance\Service\GrievanceApiService
   */
  protected $apiService;

  /**
   * @var \Drupal\global_module\Service\FileUploadService
   */
  protected $fileUploadService;

  /**
   * Constructor.
   */
  public function __construct(
    GrievanceApiService $apiService,
    FileUploadService $fileUploadService,
    CacheBackendInterface $cache
  ) {
    $this->apiService = $apiService;
    $this->fileUploadService = $fileUploadService;
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('reportgrievance.grievance_api'),
      $container->get('global_module.file_upload_service'),
      $container->get('cache.default')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'report_grievance';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    // $grievance_types = $this->apiService->getIncidentTypes();
    // if (empty($grievance_types)) {
    //   $this->messenger()->addError($this->t('No grievance types available.'));
    //   return $form;
    // }

    // $selected_type = $form_state->getValue('grievance_type');
    // $subtype_options = [];

    // if (!empty($selected_type)) {

    //   $subtype_options = $this->apiService->getIncidentSubTypes((int) $selected_type);
    // }

    if ($cache = $this->cache->get('grievance_types')) {
      $grievance_types = $cache->data;
    } else {
      $grievance_types = $this->apiService->getIncidentTypes();
      $this->cache->set('grievance_types', $grievance_types, time() + 1800);
    }
    if (empty($grievance_types)) {
      $this->messenger()->addError($this->t('No grievance types available.'));
      return $form;
    }

    $selected_type = $form_state->getValue('grievance_type');
    $subtype_options = [];

    // if (!empty($selected_type)) {
    //   $subtype_options = $this->apiService->getIncidentSubTypes((int) $selected_type);
    // }
    if (!empty($selected_type)) {
      $cache_key = 'grievance_subtypes_' . $selected_type;
      if ($subtype_cache = $this->cache->get($cache_key)) {
        $subtype_options = $subtype_cache->data;
      } else {
        $subtype_options = $this->apiService->getIncidentSubTypes((int) $selected_type);
        $this->cache->set($cache_key, $subtype_options, time() + 1800);
      }
    }

    $form['subtype_wrapper']['#attributes']['data-cv-exclude'] = '1';
    // Grievance Type
    $form['grievance_type'] = [
      '#type' => 'select',
      // '#title' => $this->t('Select a Category'),
      '#options' => $grievance_types,
      '#empty_option' => $this->t('Select a Category'),
      '#default_value' => $selected_type,
      '#required' => TRUE,
      '#required_error' => $this->t('Please Select Category'),
      '#ajax' => [
        'callback' => '::updateSubtype',
        'wrapper' => 'subtype-wrapper',
        'event' => 'change',
        // 'progress' => ['type' => 'throbber', 'message' => NULL],
        'progress' => ['type' => 'none'],
      ],
      '#attributes' => [
        'class' => [
          'form-select',
          'w-full',
          'rounded-md',
          'border',
          'border-gray-300',
          'focus:border-yellow-500',
          'focus:ring-yellow-500',
          'text-gray-700',
          'text-base',
          'p-2.5'
        ],
      ],
    ];

    // Subtype wrapper + Grievance Subtype
    $form['subtype_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'subtype-wrapper'],
    ];

    $form['subtype_wrapper']['grievance_subtype'] = [
      '#type' => 'select',
      // '#title' => $this->t('Select Sub Category'),
      '#options' => $subtype_options,
      '#empty_option' => $this->t('Select Sub Category'),
      '#default_value' => $form_state->getValue(['subtype_wrapper', 'grievance_subtype']) ?? '',
      '#required' => TRUE,
      '#required_error' => $this->t('Please Select Sub Category'),
      '#attributes' => [
        'class' => [
          'form-select',
          'w-full',
          'rounded-md',
          'border',
          'border-gray-300',
          'focus:border-yellow-500',
          'focus:ring-yellow-500',
          'text-gray-700',
          'text-base',
          'p-2.5'
        ],
      ],
    ];

    // Remarks
    $form['remarks'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remarks'),
      // '#description' => $this->t('Please provide any additional information.'),
      '#required' => TRUE,
      '#required_error' => $this->t('Remarks is required.'),
      '#maxlength' => 255,
      '#attributes' => [
        'placeholder' => $this->t('Remarks'),
        'class' => [
          'form-input',
          'w-full',
          'rounded-md',
          'border',
          'border-gray-300',
          'focus:border-yellow-500',
          'focus:ring-yellow-500',
          'text-gray-700',
          'text-base',
          'p-2.5'
        ],
      ],
    ];

    // Address
    $form['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#required_error' => $this->t('Address is required.'),
      '#attributes' => [
        'placeholder' => $this->t('Address'),
        'class' => [
          'form-input',
          'w-full',
          'rounded-md',
          'border',
          'border-gray-300',
          'focus:border-yellow-500',
          'focus:ring-yellow-500',
          'text-gray-700',
          'text-base',
          'p-2.5'
        ],
      ],
    ];
    $form['address']['#attributes']['readonly'] = 'readonly';

    // File Upload
    $form['upload_file'] = [
      '#type' => 'file',
      // '#title' => $this->t('Upload File'),
      // '#description' => $this->t('Allowed types: jpg, jpeg, png, pdf, docx, mp4'),
      '#required' => FALSE,
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => [
          'form-input',
          // 'w-full',
          'rounded-md',
          'border',
          'border-gray-300',
          'focus:border-yellow-500',
          'focus:ring-yellow-500',
          'text-gray-700',
          'text-base',
          'p-2.5'
        ],
      ],
    ];

    // Agree to Terms
    $form['agree_terms'] = [
      '#type' => 'checkbox',
      // '#title' => $this->t('I agree to the terms and conditions.'),
      '#required' => TRUE,
      '#attributes' => [
        'class' => [
          'w-6',
          'h-6',
          'rounded',
          'cursor-pointer',
          'border',
          'border-gray-400',
        ],
      ],
    ];

    // Latitude & Longitude (hidden but preserved)
    $form['latitude'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['lat-input'],
        'readonly' => 'readonly',
        'style' => 'display: none;',
      ],
    ];

    $form['longitude'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'class' => ['lng-input'],
        'readonly' => 'readonly',
        'style' => 'display: none;',
      ],
    ];

    // Actions
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#attributes' => [
        'class' => [
          'lg:h-14',
          'lg:w-44',
          's:h-10',
          'xs:h-10',
          'bg-yellow-500',
          'text-white',
          'text-lg',
          'rounded-full',
          'px-6',
          'py-2',
          'hover:bg-yellow-600',
          'transition'
        ],
      ],
    ];

    // $form['#cache'] = ['max-age' => 0];
    // Attach theme and Tailwind-based styling
    $form['#theme'] = 'report_grievance_form';
    // $form['#attributes']['class'][] = 'cv-validate-before-ajax';
    $form['#attached']['library'][] = 'reportgrievance/report_grievance_form';
    $form['#attributes']['enctype'] = 'multipart/form-data';

    return $form;
  }


  /**
   * AJAX callback to update subtypes.
   */
  public function updateSubtype(array &$form, FormStateInterface $form_state)
  {
    $selected_type = $form_state->getValue('grievance_type') ?? '';
    if (empty($selected_type)) {
      $form['subtype_wrapper']['grievance_subtype']['#options'] = [];
    }
    return $form['subtype_wrapper'];
  }


  /**
   * Submit handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $values = $form_state->getValues();
    $image_url = NULL;
    $response_data = [];
    $request = \Drupal::request();

    // Handle file upload
    if (isset($_FILES['files']['full_path']['upload_file']) && is_uploaded_file($_FILES['files']['tmp_name']['upload_file'])) {
      $upload_response = $this->fileUploadService->uploadFile($request);
      if ($upload_response instanceof JsonResponse) {
        $response_data = json_decode($upload_response->getContent(), true);
        \Drupal::logger('file_upload')->info(
          'Upload response decoded: @response',
          ['@response' => print_r($response_data, TRUE)]
        );
        if (!empty($response_data['fileName'])) {
          $image_url = $response_data['fileName'];
          \Drupal::logger('file_upload')->info('File uploaded successfully. URL: @url', [
            '@url' => $image_url,
          ]);
        } else {
          \Drupal::logger('file_upload')->warning('Upload response had no fileName and no error.');
          $this->messenger()->addError($this->t('File upload failed.'));
          return;
        }
      }
    }

    // Get user ID from session
    $session = $request->getSession();
    $userId = $session->get('api_redirect_result')['userId'] ?? 0;

    // Prepare payload
    $payload = [
      'address' => $values['address'],
      'remarks' => $values['remarks'] ?? '',
      'isShareAllowed' => FALSE,
      'latitude' => (float)($values['latitude'] ?? ''),
      'longitude' => (float)($values['longitude'] ?? ''),
      'grievanceTypeId' => (int)$values['grievance_type'],
      'grievanceSubTypeId' => (int)$values['grievance_subtype'],
      'tenantCode' => 'fireppr',
      'userId' => (int)$userId,
      'files' => [
        [
          'isFileAttached' => !empty($image_url),
          'fileType' => $response_data['fileTypeVal'] ?? '',
          'fileTypeId' => $response_data['fileTypeId'] ?? '',
          'fileUploadedUrl' => $image_url,
        ],
      ],
      'sourceTypeId' => 10,
      'linkedGrievanceId' => '',
      'isGrievanceLinked' => FALSE,
      'requestTypeId' => 1,
    ];

    \Drupal::logger('grievance')->debug('Payload sent for grievanceTypeId: @type', [
      '@type' => $values['grievance_type']
    ]);

    // Send grievance to API
    $response = $this->apiService->sendGrievance($payload);
    \Drupal::logger('report_grievance')->debug('Grievance submission response: @response', [
      '@response' => print_r($response, TRUE)
    ]);

    // Save to TempStore using a fixed key
    $tempstore = \Drupal::service('tempstore.private')->get('reportgrievance');
    $tempstore->set('grievance_response', $response);

    // Redirect based on response
    if (!empty($response['success']) && !empty($response['data']['status'])) {
      $this->messenger()->addStatus($this->t('Grievance submitted successfully.'));
      $form_state->setRedirect('reportgrievance.grievance_success');
    } else {
      $this->messenger()->addError($this->t('Submission failed. Please try again later.'));
      $form_state->setRedirect('reportgrievance.grievance_failure');
    }
  }
}