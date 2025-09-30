<?php

namespace Drupal\grievance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\grievance\Service\GrievanceReportApiService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\global_module\Service\FileUploadService;

class GrievanceForm extends FormBase
{

    protected $request;
    protected $apiService;
    protected $cache;
    protected $fileUploadService;

    public function __construct(RequestStack $request_stack, GrievanceReportApiService $apiService, CacheBackendInterface $cache, FileUploadService $fileUploadService)
    {
        $this->request = $request_stack->getCurrentRequest();
        $this->apiService = $apiService;
        $this->cache = $cache;
        $this->fileUploadService = $fileUploadService;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('request_stack'),
            $container->get('grievance.grievance_api'),
            $container->get('cache.default'),
            $container->get('global_module.file_upload_service')
        );
    }

    public function getFormId()
    {
        return 'grievance_report_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {

        // --- INCIDENT TYPES ---
        $incident_types = $this->getCachedIncidentTypes();
        $form['incident_type'] = [
            '#type' => 'select',
            '#title' => $this->t('Incident Type'),
            '#options' => $incident_types,
            '#empty_option' => $this->t('- Select -'),
            '#ajax' => [
                'callback' => '::updateSubTypes',
                'wrapper' => 'incident-subtype-wrapper',
                'event' => 'change',
            ],
        ];

        // --- INCIDENT SUBTYPES ---
        $incident_type_id = $form_state->getValue('incident_type') ?? NULL;
        $incident_subtypes = $incident_type_id ? $this->getCachedIncidentSubTypes($incident_type_id) : [];

        $form['incident_subtype_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'incident-subtype-wrapper'],
        ];

        $form['incident_subtype_wrapper']['incident_subtype'] = [
            '#type' => 'select',
            '#title' => $this->t('Incident Sub Type'),
            '#options' => $incident_subtypes,
            '#empty_option' => $this->t('- Select -'),
            '#disabled' => empty($incident_subtypes), // <-- disables until data exists
        ];

        $form['remarks'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Remarks'),
            // '#description' => $this->t('Please provide any additional information.'),
            '#maxlength' => 255,
            '#attributes' => [
                'placeholder' => $this->t('Enter remarks'),
            ],
            '#required' => TRUE,
        ];

        $form['address'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Address'),
            '#maxlength' => 255,
            '#required' => TRUE,
        ];

        $form['file_upload'] = [
            '#type' => 'file',
            '#title' => $this->t('Upload Supporting Document (if any)'),
            '#description' => $this->t('Allowed file types: jpg, jpeg, png, pdf. Max size: 2MB.'),
            '#upload_validators' => [
                'file_validate_extensions' => ['jpg jpeg png pdf'],
                'file_validate_size' => [2 * 1024 * 1024], // 2MB
            ],
            '#required' => TRUE,
        ];

        $form['agree_terms'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('I agree to the terms and conditions.'),
            '#required' => TRUE,
        ];

        // Latitude & Longitude (hidden but preserved)
        $form['latitude'] = [
            '#type' => 'textfield',
            // '#attributes' => [
            //     'class' => ['lat-input'],
            //     'readonly' => 'readonly',
            //     'style' => 'display: none;',
            // ],
        ];

        $form['longitude'] = [
            '#type' => 'textfield',
            // '#attributes' => [
            //     'class' => ['lng-input'],
            //     'readonly' => 'readonly',
            //     'style' => 'display: none;',
            // ],
        ];

        // --- Submit button ---
        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Submit'),
        ];

        return $form;
    }

    /**
     * AJAX callback to update subtypes.
     */
    public function updateSubTypes(array $form, FormStateInterface $form_state)
    {
        return $form['incident_subtype_wrapper'];
    }

    /**
     * Get cached incident types (15 mins).
     */
    protected function getCachedIncidentTypes(): array
    {
        $cid = 'incident_types_options';
        if ($cache = $this->cache->get($cid)) {
            return $cache->data;
        }
        $options = $this->apiService->getIncidentTypes();
        $this->cache->set($cid, $options, time() + 900); // 15 mins
        return $options;
    }

    /**
     * Get cached incident subtypes (15 mins).
     */
    protected function getCachedIncidentSubTypes(int $incidentTypeId): array
    {
        $cid = 'incident_subtypes_options_' . $incidentTypeId;
        if ($cache = $this->cache->get($cid)) {
            return $cache->data;
        }
        $options = $this->apiService->getIncidentSubTypes($incidentTypeId);
        $this->cache->set($cid, $options, time() + 900); // 15 mins
        return $options;
    }

    // public function submitForm(array &$form, FormStateInterface $form_state)
    // {
    //     // $incident_type = $form_state->getValue('incident_type');
    //     // $incident_subtype = $form_state->getValue('incident_subtype');
    //     $response_data = [];
    //     $request = $this->request;
    //     $image_url = NULL;
    //     $values = $form_state->getValues();


    //     if (isset($_FILES['files']['full_path']['file_upload']) && is_uploaded_file($_FILES['files']['tmp_name']['file_upload'])) {
    //         $upload_response = $this->fileUploadService->uploadFile($request);
    //         if ($upload_response instanceof JsonResponse) {
    //             $response_data = json_decode($upload_response->getContent(), true);
    //             \Drupal::logger('file_upload')->info(
    //                 'Upload response decoded: @response',
    //                 ['@response' => print_r($response_data, TRUE)]
    //             );
    //             if (!empty($response_data['fileName'])) {
    //                 $image_url = $response_data['fileName'];
    //                 \Drupal::logger('file_upload')->info('File uploaded successfully. URL: @url', [
    //                     '@url' => $image_url,
    //                 ]);
    //             } else {
    //                 \Drupal::logger('file_upload')->warning('Upload response had no fileName and no error.');
    //                 return;
    //             }
    //         }
    //     }

    //     $session = $request->getSession();
    //     $userId = ($session->get('api_redirect_result')['userId']);
    //     $payload = [
    //         'address' => $values['address'],
    //         'remarks' => $values['remarks'] ?? '',
    //         'isShareAllowed' => FALSE,
    //         'latitude' => (float)($values['latitude'] ?? ''),
    //         'longitude' => (float)($values['longitude'] ?? ''),
    //         'grievanceTypeId' => (int)$values['incident_type'],
    //         'grievanceSubTypeId' => (int)$values['incident_subtype'],
    //         'tenantCode' => 'fireppr',
    //         'userId' => (int)$userId,
    //         // 'userId' => '2506101251005301',
    //         'files' => [
    //             [
    //                 'isFileAttached' => !empty($image_url),
    //                 'fileType' => $response_data['fileTypeVal'] ?? '',
    //                 'fileTypeId' => $response_data['fileTypeId'] ?? '',
    //                 'fileUploadedUrl' => $image_url,
    //             ],
    //         ],
    //         'sourceTypeId' => 10,
    //         'linkedGrievanceId' => '',
    //         'isGrievanceLinked' => FALSE,
    //         'requestTypeId' => 1,
    //     ];

    //     $queue = \Drupal::queue('grievance_submission');
    //     $queue->createItem($payload);

    //     $this->messenger()->addMessage($this->t('Your grievance has been received and will be processed shortly.'));
    // }

    // public function submitForm(array &$form, FormStateInterface $form_state)
    // {
    //     $response_data = [];
    //     $request = $this->request;
    //     $image_url = NULL;
    //     $values = $form_state->getValues();

    //     // Handle file upload
    //     if (isset($_FILES['files']['full_path']['file_upload']) && is_uploaded_file($_FILES['files']['tmp_name']['file_upload'])) {
    //         $upload_response = $this->fileUploadService->uploadFile($request);
    //         if ($upload_response instanceof JsonResponse) {
    //             $response_data = json_decode($upload_response->getContent(), true);
    //             \Drupal::logger('file_upload')->info(
    //                 'Upload response decoded: @response',
    //                 ['@response' => print_r($response_data, TRUE)]
    //             );
    //             if (!empty($response_data['fileName'])) {
    //                 $image_url = $response_data['fileName'];
    //                 \Drupal::logger('file_upload')->info('File uploaded successfully. URL: @url', [
    //                     '@url' => $image_url,
    //                 ]);
    //             } else {
    //                 \Drupal::logger('file_upload')->warning('Upload response had no fileName and no error.');
    //                 return;
    //             }
    //         }
    //     }

    //     // Build payload
    //     $session = $request->getSession();
    //     $userId = ($session->get('api_redirect_result')['userId']);
    //     $payload = [
    //         'address' => $values['address'],
    //         'remarks' => $values['remarks'] ?? '',
    //         'isShareAllowed' => FALSE,
    //         'latitude' => (float)($values['latitude'] ?? ''),
    //         'longitude' => (float)($values['longitude'] ?? ''),
    //         'grievanceTypeId' => (int)$values['incident_type'],
    //         'grievanceSubTypeId' => (int)$values['incident_subtype'],
    //         'tenantCode' => 'fireppr',
    //         'userId' => (int)$userId,
    //         'files' => [
    //             [
    //                 'isFileAttached' => !empty($image_url),
    //                 'fileType' => $response_data['fileTypeVal'] ?? '',
    //                 'fileTypeId' => $response_data['fileTypeId'] ?? '',
    //                 'fileUploadedUrl' => $image_url,
    //             ],
    //         ],
    //         'sourceTypeId' => 10,
    //         'linkedGrievanceId' => '',
    //         'isGrievanceLinked' => FALSE,
    //         'requestTypeId' => 1,
    //     ];

    //     // Enqueue as backup
    //     $queue = \Drupal::queue('grievance_submission');
    //     $queue->createItem($payload);

    //     // Fire async immediately
    //     // try {
    //     //     $promise = $this->apiService->sendGrievanceAsync($payload);
    //     //     $promise->then(
    //     //         function ($response) use ($payload) {
    //     //             $body = json_decode($response->getBody()->getContents(), TRUE);
    //     //             \Drupal::logger('report_grievance')->info(
    //     //                 'Async grievance submitted immediately for userId @uid: @response',
    //     //                 ['@uid' => $payload['userId'], '@response' => print_r($body, TRUE)]
    //     //             );
    //     //         },
    //     //         function ($exception) use ($payload, $queue) {
    //     //             \Drupal::logger('report_grievance')->error(
    //     //                 'Immediate async grievance submission failed for userId @uid: @msg',
    //     //                 ['@uid' => $payload['userId'], '@msg' => $exception->getMessage()]
    //     //             );
    //     //             // Already enqueued, so retry will happen via cron
    //     //         }
    //     //     );
    //     // } catch (\Exception $e) {
    //     //     \Drupal::logger('report_grievance')->error(
    //     //         'Immediate async grievance submission threw exception for userId @uid: @msg',
    //     //         ['@uid' => $payload['userId'], '@msg' => $e->getMessage()]
    //     //     );
    //     // }

    //     // $this->messenger()->addMessage($this->t('Your grievance has been received and will be processed shortly.'));
    //     // --- Fire async immediately and wait ---
    //     try {
    //         $promise = $this->apiService->sendGrievanceAsync($payload);
    //         $response = $promise->wait(); // ensures the API call actually happens
    //         $body = json_decode($response->getBody()->getContents(), TRUE);
    //         \Drupal::logger('report_grievance')->info(
    //             'Immediate grievance submission successful for userId @uid: @response',
    //             ['@uid' => $payload['userId'], '@response' => print_r($body, TRUE)]
    //         );
    //         $this->messenger()->addStatus($this->t('Your grievance has been submitted successfully.'));
    //     } catch (\Exception $e) {
    //         \Drupal::logger('report_grievance')->error(
    //             'Immediate grievance submission failed for userId @uid: @msg',
    //             ['@uid' => $payload['userId'], '@msg' => $e->getMessage()]
    //         );
    //         $this->messenger()->addWarning($this->t('Your grievance has been received and will be processed shortly.'));
    //     }
    // }

    // public function submitForm(array &$form, FormStateInterface $form_state)
    // {
    //     $response_data = [];
    //     $request = $this->request;
    //     $image_url = NULL;
    //     $values = $form_state->getValues();

    //     // Handle file upload
    //     if (isset($_FILES['files']['full_path']['file_upload']) && is_uploaded_file($_FILES['files']['tmp_name']['file_upload'])) {
    //         $upload_response = $this->fileUploadService->uploadFile($request);
    //         if ($upload_response instanceof JsonResponse) {
    //             $response_data = json_decode($upload_response->getContent(), true);
    //             if (!empty($response_data['fileName'])) {
    //                 $image_url = $response_data['fileName'];
    //             } else {
    //                 \Drupal::logger('file_upload')->warning('Upload response had no fileName and no error.');
    //                 return;
    //             }
    //         }
    //     }

    //     // Build payload
    //     $session = $request->getSession();
    //     $userId = (int)($session->get('api_redirect_result')['userId'] ?? 0);

    //     $payload = [
    //         'address' => $values['address'],
    //         'remarks' => $values['remarks'] ?? '',
    //         'isShareAllowed' => FALSE,
    //         'latitude' => (float)($values['latitude'] ?? 0),
    //         'longitude' => (float)($values['longitude'] ?? 0),
    //         'grievanceTypeId' => (int)$values['incident_type'],
    //         'grievanceSubTypeId' => (int)$values['incident_subtype'],
    //         'tenantCode' => 'fireppr',
    //         'userId' => $userId,
    //         'files' => [
    //             [
    //                 'isFileAttached' => !empty($image_url),
    //                 'fileType' => $response_data['fileTypeVal'] ?? '',
    //                 'fileTypeId' => $response_data['fileTypeId'] ?? '',
    //                 'fileUploadedUrl' => $image_url,
    //             ],
    //         ],
    //         'sourceTypeId' => 10,
    //         'linkedGrievanceId' => '',
    //         'isGrievanceLinked' => FALSE,
    //         'requestTypeId' => 1,
    //     ];

    //     // Enqueue as backup (reliable)
    //     $queue = \Drupal::queue('grievance_submission');
    //     $queue->createItem($payload);

    //     // Fire async immediately (non-blocking)
    //     try {
    //         $promise = $this->apiService->sendGrievanceAsync($payload);
    //         $promise->then(
    //             function ($response) use ($payload) {
    //                 $body = json_decode($response->getBody()->getContents(), TRUE);
    //                 \Drupal::logger('report_grievance')->info(
    //                     'Async grievance submitted immediately for userId @uid',
    //                     ['@uid' => $payload['userId']]
    //                 );
    //             },
    //             function ($exception) use ($payload) {
    //                 \Drupal::logger('report_grievance')->error(
    //                     'Immediate async grievance submission failed for userId @uid: @msg',
    //                     ['@uid' => $payload['userId'], '@msg' => $exception->getMessage()]
    //                 );
    //             }
    //         );
    //     } catch (\Exception $e) {
    //         \Drupal::logger('report_grievance')->error(
    //             'Immediate async grievance submission threw exception for userId @uid: @msg',
    //             ['@uid' => $payload['userId'], '@msg' => $e->getMessage()]
    //         );
    //     }

    //     $this->messenger()->addMessage($this->t('Your grievance has been received and will be processed shortly.'));
    // }

    // public function submitForm(array &$form, FormStateInterface $form_state)
    // {
    //     $response_data = [];
    //     $request = $this->request;
    //     $image_url = NULL;
    //     $values = $form_state->getValues();

    //     // Handle file upload
    //     if (isset($_FILES['files']['full_path']['file_upload']) && is_uploaded_file($_FILES['files']['tmp_name']['file_upload'])) {
    //         $upload_response = $this->fileUploadService->uploadFile($request);
    //         if ($upload_response instanceof JsonResponse) {
    //             $response_data = json_decode($upload_response->getContent(), true);
    //             if (!empty($response_data['fileName'])) {
    //                 $image_url = $response_data['fileName'];
    //             } else {
    //                 \Drupal::logger('file_upload')->warning('Upload response had no fileName and no error.');
    //                 return;
    //             }
    //         }
    //     }

    //     // Build payload
    //     $session = $request->getSession();
    //     $userId = (int)($session->get('api_redirect_result')['userId'] ?? 0);

    //     $payload = [
    //         'address' => $values['address'],
    //         'remarks' => $values['remarks'] ?? '',
    //         'isShareAllowed' => FALSE,
    //         'latitude' => (float)($values['latitude'] ?? 0),
    //         'longitude' => (float)($values['longitude'] ?? 0),
    //         'grievanceTypeId' => (int)$values['incident_type'],
    //         'grievanceSubTypeId' => (int)$values['incident_subtype'],
    //         'tenantCode' => 'fireppr',
    //         'userId' => $userId,
    //         'files' => [
    //             [
    //                 'isFileAttached' => !empty($image_url),
    //                 'fileType' => $response_data['fileTypeVal'] ?? '',
    //                 'fileTypeId' => $response_data['fileTypeId'] ?? '',
    //                 'fileUploadedUrl' => $image_url,
    //             ],
    //         ],
    //         'sourceTypeId' => 10,
    //         'linkedGrievanceId' => '',
    //         'isGrievanceLinked' => FALSE,
    //         'requestTypeId' => 1,
    //     ];

    //     // Enqueue as backup
    //     $queue = \Drupal::queue('grievance_submission');
    //     $queue->createItem($payload);

    //     // Immediately trigger worker
    //     try {
    //         $worker = \Drupal::service('plugin.manager.queue_worker')->createInstance('grievance_submission');
    //         $worker->processItem($payload);
    //         \Drupal::logger('report_grievance')->info(
    //             'Grievance submitted immediately for userId @uid',
    //             ['@uid' => $userId]
    //         );
    //     } catch (\Exception $e) {
    //         \Drupal::logger('report_grievance')->error(
    //             'Immediate grievance submission failed for userId @uid: @msg',
    //             ['@uid' => $userId, '@msg' => $e->getMessage()]
    //         );
    //         // Cron will retry because it’s in the queue
    //     }

    //     $this->messenger()->addMessage($this->t('Your grievance has been received and will be processed shortly.'));
    // }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $request = $this->request;
        $values = $form_state->getValues();
        $response_data = [];
        $image_url = NULL;

        // Handle file upload
        if (isset($_FILES['files']['full_path']['file_upload']) && is_uploaded_file($_FILES['files']['tmp_name']['file_upload'])) {
            $upload_response = $this->fileUploadService->uploadFile($request);
            if ($upload_response instanceof JsonResponse) {
                $response_data = json_decode($upload_response->getContent(), true);
                if (!empty($response_data['fileName'])) {
                    $image_url = $response_data['fileName'];
                } else {
                    \Drupal::logger('file_upload')->warning('Upload response had no fileName and no error.');
                    $this->messenger()->addError($this->t('File upload failed.'));
                    return;
                }
            }
        }

        // Build payload
        $session = $request->getSession();
        $userId = (int)($session->get('api_redirect_result')['userId'] ?? 0);

        $payload = [
            'address' => $values['address'],
            'remarks' => $values['remarks'] ?? '',
            'isShareAllowed' => FALSE,
            'latitude' => (float)($values['latitude'] ?? 0),
            'longitude' => (float)($values['longitude'] ?? 0),
            'grievanceTypeId' => (int)$values['incident_type'],
            'grievanceSubTypeId' => (int)$values['incident_subtype'],
            'tenantCode' => 'fireppr',
            'userId' => $userId,
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

        // Enqueue as backup (cron safety net)
        $queue = \Drupal::queue('grievance_submission');
        $queue->createItem($payload);

        // Fire async immediately (non-blocking)
        try {
            $promise = $this->apiService->sendGrievanceAsync($payload);
            $promise->then(
                function ($response) use ($payload) {
                    \Drupal::logger('report_grievance')->info(
                        'Immediate async grievance submitted for userId @uid',
                        ['@uid' => $payload['userId']]
                    );
                },
                function ($exception) use ($payload) {
                    \Drupal::logger('report_grievance')->error(
                        'Immediate async grievance submission failed for userId @uid: @msg',
                        ['@uid' => $payload['userId'], '@msg' => $exception->getMessage()]
                    );
                    // Already enqueued, cron will retry
                }
            );
        } catch (\Exception $e) {
            \Drupal::logger('report_grievance')->error(
                'Async grievance submission threw exception for userId @uid: @msg',
                ['@uid' => $payload['userId'], '@msg' => $e->getMessage()]
            );
        }

        $this->messenger()->addMessage($this->t('Your grievance has been received and will be processed shortly.'));
    }
}
