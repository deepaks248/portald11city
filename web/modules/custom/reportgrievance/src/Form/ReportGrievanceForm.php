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
    // ✅ Step 1: Initialize empty dropdowns.
    // They’ll be populated dynamically via JS fetching from your controller routes.
    $grievance_types = [];
    $subtype_options = [];
    $selected_type = $form_state->getValue('grievance_type') ?? '';

    // ✅ Grievance Type (will load via JS)
    $form['grievance_type'] = [
      '#type' => 'select',
      '#options' => $grievance_types,
      '#empty_option' => $this->t('Select a Category'),
      '#default_value' => $selected_type,
      '#required' => TRUE,
      '#validated' => TRUE,
      '#required_error' => $this->t('Please Select Category'),
      '#attributes' => ['class' => ['form-select','grievance-type-select','w-full','rounded-md','border','border-gray-300','focus:border-yellow-500','focus:ring-yellow-500','text-gray-700','text-base','p-2.5'], 'data-endpoint' => '/grievance/types'],
    ];

    // ✅ Subtype wrapper + Grievance Subtype
    $form['subtype_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'subtype-wrapper'],
    ];

    $form['subtype_wrapper']['grievance_subtype'] = [
      '#type' => 'select',
      '#options' => $subtype_options,
      '#empty_option' => $this->t('Select Sub Category'),
      '#default_value' => '',
      '#required' => TRUE,
      '#validated' => TRUE,
      '#required_error' => $this->t('Please Select Sub Category'),
      '#attributes' => ['class' => ['form-select','grievance-subtype-select','w-full','rounded-md','border','border-gray-300','focus:border-yellow-500','focus:ring-yellow-500','text-gray-700','text-base','p-2.5'], 'data-endpoint-template' => '/grievance/subtypes/'],
    ];

    // ✅ Remarks
    $form['remarks'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remarks'),
      '#required' => TRUE,
      '#required_error' => $this->t('Remarks is required.'),
      '#maxlength' => 255,
      '#attributes' => ['placeholder' => $this->t('Remarks'), 'class' => ['form-input','w-full','rounded-md','border','border-gray-300','focus:border-yellow-500','focus:ring-yellow-500','text-gray-700','text-base','p-2.5']],
    ];

    // ✅ Address
    $form['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address'),
      '#maxlength' => 255,
      '#required' => TRUE,
      '#required_error' => $this->t('Address is required.'),
      '#attributes' => ['placeholder' => $this->t('Address'), 'class' => ['form-input','w-full','rounded-md','border','border-gray-300','focus:border-yellow-500','focus:ring-yellow-500','text-gray-700','text-base','p-2.5'], 'readonly' => 'readonly'],
    ];

    // ✅ File Upload
    $form['upload_file'] = [
      '#type' => 'file',
      '#required' => FALSE,
      '#limit_validation_errors' => [],
      '#attributes' => ['class' => ['form-input','rounded-md','border','border-gray-300','focus:border-yellow-500','focus:ring-yellow-500','text-gray-700','text-base','p-2.5']],
    ];

    // ✅ Agree to Terms
    $form['agree_terms'] = [
      '#type' => 'checkbox',
      '#required' => TRUE,
      '#attributes' => ['class' => ['w-6','h-6','rounded','cursor-pointer','border','border-gray-400']],
    ];

    // ✅ Hidden Lat/Long
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

    // ✅ Submit Button
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['lg:h-14','lg:w-44','s:h-10','xs:h-10','bg-yellow-500','text-white','text-lg','rounded-full','px-6','py-2','hover:bg-yellow-600','transition']],
    ];

    // ✅ Attach JS and settings for endpoints
    $form['#attached']['library'][] = 'reportgrievance/report_grievance_form';
    $form['#attached']['drupalSettings']['reportgrievance'] = [
      'endpoints' => [
        'types' => '/grievance/types',
        'subtypes' => '/grievance/subtypes/',
      ],
    ];

    // ✅ Cache settings and theme
    $form['#cache']['max-age'] = 3600;
    $form['#theme'] = 'report_grievance_form';
    $form['#attributes']['enctype'] = 'multipart/form-data';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $grievance_type_id = $form_state->getValue('grievance_type');
    $grievance_subtype_id = $form_state->getValue('grievance_subtype');

    // Load the cached grievance types.
    $cache = \Drupal::cache()->get('grievance_types');
    $types = $cache ? $cache->data : [];

    // Validate type.
    if (!isset($types[$grievance_type_id])) {
      $form_state->setErrorByName('grievance_type', $this->t('Invalid grievance type selected.'));
      return;
    }
    // Load the cached subtypes (depends on the type).
    $sub_cache = \Drupal::cache()->get('grievance_subtypes_' . $grievance_type_id);
    $subtypes = $sub_cache ? $sub_cache->data : [];

    // Validate subtype.
    if (!isset($subtypes[$grievance_subtype_id])) {
      $form_state->setErrorByName('grievance_subtype', $this->t('Invalid grievance subtype selected.'));
      return;
    }
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
      if ($upload_response instanceof \Symfony\Component\HttpFoundation\JsonResponse) {
        $response_data = json_decode($upload_response->getContent(), TRUE);
        if (!empty($response_data['fileName'])) {
          $image_url = $response_data['fileName'];
        } else {
          $this->messenger()->addError($this->t('File upload failed.'));
          //Security log for upload failure
          \Drupal::logger('reportgrievance')->error('File upload failed during grievance submission. Response: @response', ['@response' => print_r($response_data, TRUE)]);
          return;
        }
      }
    }

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

    $response = $this->apiService->sendGrievance($payload);

    if (!empty($response['success']) && !empty($response['data']['status'])) {
      $grievance_id = $response['data']['data']; // GV-20251009-371833
      $secret = "my_secret_key";
      $token = hash_hmac('sha256', $grievance_id, $secret);

      // Store token => grievance_id mapping in key-value storage
      \Drupal::keyValue('reportgrievance.token_map')->set($token, $grievance_id);

      // Redirect with token only
      $url = '/success-grievance/' . $token;
      $form_state->setRedirectUrl(\Drupal\Core\Url::fromUri('internal:' . $url));
      $this->messenger()->addStatus($this->t('Grievance submitted successfully.'));
    } else {
      $form_state->setRedirect('reportgrievance.grievance_failure');
      $this->messenger()->addError($this->t('Submission failed. Please try again later.'));
    }
  }
}
