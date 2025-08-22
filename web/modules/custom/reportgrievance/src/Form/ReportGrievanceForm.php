<?php

namespace Drupal\reportgrievance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\reportgrievance\Service\GrievanceApiService;
use Drupal\global_module\Service\FileUploadService;
use Symfony\Component\HttpFoundation\JsonResponse;

class ReportGrievanceForm extends FormBase
{

  /**
   * @var \Drupal\reportgrievance\Service\GrievanceApiService
   */
  protected $apiService;

  /**
   * @var \Drupal\global_module\Service\FileUploadService
   */
  protected $fileUploadService;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructor.
   */
  public function __construct(
    GrievanceApiService $apiService,
    FileUploadService $fileUploadService,
    RequestStack $request_stack
  ) {
    $this->apiService = $apiService;
    $this->fileUploadService = $fileUploadService;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('reportgrievance.grievance_api'),
      $container->get('global_module.file_upload_service'),
      $container->get('request_stack')
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
    $grievance_types = $this->apiService->getIncidentTypes();
    if (empty($grievance_types)) {
      $this->messenger()->addError($this->t('No grievance types available.'));
      return $form;
    }

    $selected_type = $form_state->getValue('grievance_type');
    $subtype_options = [];

    if (!empty($selected_type)) {
      $subtype_options = $this->apiService->getIncidentSubTypes((int) $selected_type);
    }

    // Grievance Type
    $form['grievance_type'] = [
      '#type' => 'select',
      // '#title' => $this->t('Select a Category'),
      '#options' => $grievance_types,
      '#empty_option' => $this->t('Select a Category'),
      '#default_value' => $selected_type,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateSubtype',
        'wrapper' => 'subtype-wrapper',
        'event' => 'change',
        'progress' => ['type' => 'throbber', 'message' => NULL],
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
      '#maxlength' => 255,
      '#attributes' => [
        // 'placeholder' => $this->t('Remarks'),
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
      '#attributes' => [
        // 'placeholder' => $this->t('Address'),
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
          'lg:h-14', 'lg:w-44', 's:h-10', 'xs:h-10',
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

    $form['#cache'] = ['max-age' => 0];
    // Attach theme and Tailwind-based styling
    $form['#theme'] = 'report_grievance_form';
    $form['#attached']['library'][] = 'reportgrievance/report_grievance_form';
    $form['#attributes']['enctype'] = 'multipart/form-data';

    return $form;
  }


  /**
   * AJAX callback to update subtypes.
   */
  public function updateSubtype(array &$form, FormStateInterface $form_state)
  {
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

    if (isset($_FILES['files']['full_path']['upload_file']) && is_uploaded_file($_FILES['files']['tmp_name']['upload_file'])) {
      $upload_response = $this->fileUploadService->uploadFile($this->request);
      if ($upload_response instanceof JsonResponse) {
        $response_data = json_decode($upload_response->getContent(), true);
        if (!empty($response_data['fileName'])) {
          $image_url = $response_data['fileName'];
        } elseif (!empty($response_data['error'])) {
          $this->messenger()->addError($this->t('File upload error: @error', ['@error' => $response_data['error']]));
          return;
        }
      }
    }

    $session = $session = $this->request->getSession();
    $userId = ($session->get('api_redirect_result')['userId']);

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
      // 'userId' => '2506101251005301',
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

    $response = $this->apiService->sendGrievance($payload);
    \Drupal::logger('report_grievance')->debug('Grievance submission response: @response', ['@response' => print_r($response, TRUE)]);

    // Save to TempStore
    $tempstore = \Drupal::service('tempstore.private')->get('reportgrievance');
    $tempstore->set('grievance_response', $response);

    // Redirect
    if (!empty($response['success']) && $response['data']['status'] == true) {
      $this->messenger()->addStatus($this->t('Grievance submitted successfully.'));
      $form_state->setRedirect('reportgrievance.grievance_success');
    } else {
      $this->messenger()->addError($this->t('Submission failed. Please try again later.'));
      $form_state->setRedirect('reportgrievance.grievance_failure');
    }
  }
}
