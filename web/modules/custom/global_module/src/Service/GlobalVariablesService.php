<?php

namespace Drupal\global_module\Service;

use Drupal\Core\Site\Settings;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\global_module\Service\ApimanTokenService;
use Drupal\global_module\Service\VaultConfigService;
use Drupal\global_module\Service\ApiHttpClientService;

class GlobalVariablesService
{

  protected $logger;
  protected $cache;
  public const APP_JSON = 'application/json' ;
  public const BEARER = 'Bearer ';

  /**
   * HTTP client for making external requests.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  public const STR_STS = 'status';
  const PAYLOADS = 'payload';
  protected $httpClient;
  protected $apimanTokenService;
  protected $vaultConfigService;
  protected $apiHttpClientService;

  /**
   * Constructs a new GlobalVariablesService.
   */
  public function __construct(
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
    CacheBackendInterface $cache,
    ApimanTokenService $apimanTokenService,
    VaultConfigService $vaultConfigService,
    ApiHttpClientService $apiHttpClientService
  )
  {
    $this->logger = $logger_factory->get('global_variables_service');
    $this->cache = $cache;
    $this->httpClient = $http_client;
    $this->apimanTokenService = $apimanTokenService;
    $this->vaultConfigService = $vaultConfigService;
    $this->apiHttpClientService = $apiHttpClientService;
  }

  public function decrypt($value)
  {
    $key = "Fl%JTt%d954n@PoU";
    $cipher = "AES-128-ECB";

    try {

      $ciphertext = base64_decode($value);

      $decrypted = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

      if ($decrypted !== FALSE) {

        $pad = ord($decrypted[strlen($decrypted) - 1]);
        $decrypted = substr($decrypted, 0, -$pad);
      }

      return $decrypted;
    } catch (\Exception $e) {
      error_log('Decryption error: ' . $e->getMessage());
      return NULL;
    }
  }




  public function fileUploadser(Request $request)
  {
    $path = $request->getPathInfo();
    if ($path !== '/fileupload') {
      throw new NotFoundHttpException();
    }
    define('UPLOAD_FILE', 'uploadedfile1');
    $globalVariables = $this->vaultConfigService->getGlobalVariables();

    $result = $this->validateUploadedFile();
    if (!$result instanceof JsonResponse) {
      $result = $this->buildFileUploadResponse($request, $globalVariables);
    }

    return $result;
  }

  protected function validateUploadedFile(): ?JsonResponse
  {
    if (!isset($_FILES[UPLOAD_FILE])) {
      return new JsonResponse(['status' => FALSE, 'message' => 'No file uploaded.']);
    }

    $mimeType = mime_content_type($_FILES[UPLOAD_FILE]['tmp_name']);
    $allowedTypes = [
      'image/jpeg',
      'image/png',
      'application/pdf',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'video/mp4'
    ];

    if (!in_array($mimeType, $allowedTypes)) {
      return new JsonResponse(['status' => FALSE, 'message' => 'File content not allowed!']);
    }

    return NULL;
  }

  protected function detectUploadedFileType(string $extension): ?array
  {
    $extensionLower = strtolower($extension);
    $fileType = NULL;

    if (in_array($extensionLower, ['jpg', 'jpeg', 'png'])) {
      $fileType = ['id' => 2, 'type' => 'image'];
    }
    elseif (in_array($extensionLower, ['pdf', 'doc', 'docx', 'mp3', 'xlsx'])) {
      $fileType = ['id' => 4, 'type' => 'file'];
    }
    elseif ($extensionLower === 'mp4') {
      $fileType = ['id' => 1, 'type' => 'video'];
    }

    return $fileType;
  }

  protected function buildFileUploadResponse(Request $request, ?array $globalVariables): JsonResponse
  {
    $originalName = $_FILES[UPLOAD_FILE]['name'];
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $extnParts = explode(".", $originalName);

    if (count($extnParts) > 2) {
      return new JsonResponse(['message' => 'Multiple file extensions not allowed', self::STR_STS => FALSE]);
    }

    $fileType = $this->detectUploadedFileType($extension);
    if ($fileType === NULL) {
      return new JsonResponse(['message' => 'Unsupported file type.', self::STR_STS => FALSE]);
    }

    return $this->uploadProcessedFile($request, $globalVariables, $extension, $fileType);
  }

  protected function uploadProcessedFile(Request $request, array $globalVariables, string $extension, array $fileType): JsonResponse
  {
    $tmpName = $_FILES[UPLOAD_FILE]['tmp_name'];
    $uuidFilename = \Drupal::service('uuid')->generate() . '.' . $extension;
    $fileUplPath = $globalVariables['applicationConfig']['config']['fileuploadPath'];
    $mimeType = mime_content_type($tmpName);
    $result = new JsonResponse([
      'fileName' => $fileUplPath . $uuidFilename,
      'fileTypeId' => $fileType['id'],
      'fileTypeVal' => $fileType['type'],
    ]);

    try {
      $response = $this->httpClient->request('POST', $fileUplPath . 'upload_media_test1.php', [
        'verify' => FALSE,
        'multipart' => [
          [
            'name' => UPLOAD_FILE,
            'contents' => fopen($tmpName, 'r'),
            'filename' => $uuidFilename,
            'headers' => [
              'Content-Type' => $mimeType
            ]
          ],
          [
            'name' => 'success_action_status',
            'contents' => '200',
          ]
        ]
      ]);

      $responseBody = $response->getBody()->getContents();
      $this->logger->debug('Upload raw response: ' . $responseBody);

      if ($request->request->get('userPic') === 'profilePic') {
        $result = $this->updateUserProfilePic($fileUplPath . $uuidFilename);
      }
    } catch (\Exception $e) {
      $this->logger->error('File upload failed: @error', ['@error' => $e->getMessage()]);
      $result = new JsonResponse(['status' => FALSE, 'message' => 'Upload error'], 500);
    }

    return $result;
  }


  public function updateUserProfilePic($profilePic)
  {
    try {
      $globalVariables = $this->vaultConfigService->getGlobalVariables();
      $session = \Drupal::request()->getSession();
      $user_data = $session->get('api_redirect_result') ?? [];
      $access_token = $this->apimanTokenService->getApimanAccessToken();
      if (empty($user_data['mobileNumber'])) {
        return new JsonResponse(['status' => FALSE, 'message' => 'Mobile number not found in session'], 400);
      }

      $payload = [
        'mobileNumber' => $user_data['mobileNumber'],
        'profilePic' => $profilePic,
        'firstName' => $user_data['firstName'],
        'lastName' => $user_data['lastName'],
        'emailId' => $user_data['emailId'],
        'tenantCode' => $user_data['tenantCode'],
        'userId' => $user_data['userId']
      ];
      $url = $globalVariables['apiManConfig']['config']['apiUrl'] . 'tiotcitizenapp' . $globalVariables['apiManConfig']['config']['apiVersion'] . 'user/update';
      $this->logger->debug('Profile update URL: @url', ['@url' => $url]);

      $response = $this->httpClient->request('POST', $url, [
        'headers' => [
          'Content-Type' => self::APP_JSON,
          'Authorization' => self::BEARER . $access_token,
        ],
        'json' => $payload,
        'timeout' => 10,
        'verify' => FALSE, // optional: disable SSL verification for dev
      ]);
      $result = json_decode($response->getBody()->getContents(), TRUE);
      $this->logger->debug('Profile update result:', [
        'Message' => json_encode($result['data']),
      ]);
      $session->set('api_redirect_result', array_merge($session->get('api_redirect_result', []), ['profilePic' => $result['data']['profilePic'] ?? NULL]));
      return new JsonResponse([
        'status' => TRUE,
        'profilePic' => $result['data']['profilePic'] ?? NULL,
      ]);
    } catch (\Exception $e) {
      $this->logger->error('Profile update failed: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Profile update failed',
      ], 500);
    }
  }

  public function detailsUpdate()
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
      $access_token = $this->apimanTokenService->getApimanAccessToken();
      $globalVariables = $this->vaultConfigService->getGlobalVariables();
      $client = \Drupal::httpClient();

      $response = $client->post(
        $globalVariables['apiManConfig']['config']['apiUrl'] . 'tiotcitizenapp' . $globalVariables['apiManConfig']['config']['apiVersion'] . 'user/update',
        [
          'headers' => [
            'Authorization' => self::BEARER . $access_token,
            'Content-Type' => self::APP_JSON,
          ],
          'json' => $payload,
        ]
      );

      $data = json_decode($response->getBody(), TRUE);

      if (!empty($data['status']) && ($data['status'] === TRUE || $data['status'] === 'true')) {
        $session->remove('api_redirect_result');
        \Drupal::logger('profile')->notice('Profile removed successfully.');
        return new JsonResponse([
          'status' => TRUE,
          'message' => 'Profile removed successfully',
        ]);
      } else {
        \Drupal::logger('profile')->notice('Failed to remove profile');
        return new JsonResponse([
          'status' => FALSE,
          'message' => 'Failed to remove profile',
        ]);
      }
    } catch (\Exception $e) {
      \Drupal::logger('profile_form')->error('API Error: @message', ['@message' => $e->getMessage()]);
      \Drupal::logger('profile_form')->error('API Error. Please try again later.');
    }
  }
}
