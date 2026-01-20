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

  public function getServiceUrl($serviceName)
  {
    $serUrl = '';
    $globalVariables = $this->vaultConfigService->getGlobalVariables();

    $apiUrl = $globalVariables['apiManConfig']['config']['apiUrl'];
    $apiVer = $globalVariables['apiManConfig']['config']['apiVersion'];
    $webportalUrl = $globalVariables['applicationConfig']['config']['webportalUrl'];
    if ($serviceName == 'cep') {
      $serUrl = $apiUrl . 'trinityengage-casemanagementsystem' . $apiVer;
    } elseif ($serviceName == 'cad') {
      $serUrl = $apiUrl . 'trinity-respond' . $apiVer;
    } elseif ($serviceName == 'ngcad') {
      $serUrl = $apiUrl . 'ngcadmobileapp' . $apiVer;
    } elseif ($serviceName == 'iot') {
      $serUrl = $apiUrl . 'tiotIOTPS' . $apiVer;
    } elseif ($serviceName == 'cityapp') {
      $serUrl = $apiUrl . 'tengageCity' . $apiVer;
    } elseif ($serviceName == 'idam') {
      $serUrl = $apiUrl . 'UMA' . $apiVer;
    } elseif ($serviceName == 'tiotweb') {
      $serUrl = $apiUrl . 'tiotweb' . $apiVer;
    } elseif ($serviceName == 'tiotICCCOperator') {
      $serUrl = $apiUrl . 'tiotICCCOperator' . $apiVer;
    } elseif ($serviceName == 'tiotcitizenapp') {
      $serUrl = $apiUrl . 'tiotcitizenapp' . $apiVer;
    } elseif ($serviceName == 'innv') {
      $serUrl = $apiUrl . 'tiotcitizenapp' . $apiVer;
    } elseif ($serviceName == 'portal') {
      $serUrl = $webportalUrl;
    } else {
      $serUrl = '';
    }

    return $serUrl;
  }

  private function buildServiceUrl(array $data): ?string
  {
    $base = $this->getServiceUrl($data['service'] ?? '');
    return $base ? $base . ($data['endPoint'] ?? '') : NULL;
  }

  public function postData(Request $request): JsonResponse
  {
    try {
      $method = $request->getMethod();
      if ($method !== 'POST') {
        return new JsonResponse(['status' => FALSE, 'message' => 'Method not allowed!']);
      }

      $postData = json_decode($request->getContent(), TRUE);
      if (!$postData || !isset($postData['service'], $postData['type'])) {
        return new JsonResponse(['status' => FALSE, 'message' => 'Invalid payload!']);
      }

      $user = \Drupal::currentUser();
      $userId = $user->id();
      $url = $this->buildServiceUrl($postData);
      $response = $this->handleRequestByType($postData, $url, $request, $userId);

      return new JsonResponse($response);
    } catch (\Exception $e) {
      \Drupal::logger('Post Data Error')->error($e->getMessage());
      return new JsonResponse(['status' => FALSE, 'message' => 'Internal server error.'], 500);
    }
  }

  public function handleRequestByType(array $data, string $url, Request $request, int $userId): array
  {
    $session = \Drupal::request()->getSession();
    $user_data = $session->get('api_redirect_result') ?? [];
    $type = $data['type'] ?? NULL;
    $payload = $data[self::PAYLOADS] ?? [];

    switch ($type) {
      case 2:
        return $this->apiHttpClientService->postApiman($url, $payload); // Assuming it's a method in this class
      case 'delyUser':
        return $this->userDelete(
          userID: $user_data['userId'],
          tenantCode: $user_data['tenantCode']
        );

      default:
        return $this->apiHttpClientService->postApiman($url, $payload); // Fallback/default handler
    }
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

    if (!isset($_FILES[UPLOAD_FILE])) {
      return new JsonResponse(['status' => FALSE, 'message' => 'No file uploaded.']);
    }

    $originalName = $_FILES[UPLOAD_FILE]['name'];
    $tmpName = $_FILES[UPLOAD_FILE]['tmp_name'];
    $mimeType = mime_content_type($tmpName);
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $extnParts = explode(".", $originalName);

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

    if (count($extnParts) > 2) {
      return new JsonResponse(['message' => 'Multiple file extensions not allowed', self::STR_STS => FALSE]);
    }

    // Detect file type   
    $fileTypeVal = NULL;
    $fileTypeType = NULL;
    $extensionLower = strtolower($extension);
    if (in_array($extensionLower, ['jpg', 'jpeg', 'png'])) {
      $fileTypeVal = 2;
      $fileTypeType = "image";
    } elseif (in_array($extensionLower, ['pdf', 'doc', 'docx', 'mp3', 'xlsx'])) {
      $fileTypeVal = 4;
      $fileTypeType = "file";
    } elseif ($extensionLower === 'mp4') {
      $fileTypeVal = 1;
      $fileTypeType = "video";
    }

    if (!$fileTypeVal) {
      return new JsonResponse(['message' => 'Unsupported file type.', self::STR_STS => FALSE]);
    }

    $uuidFilename = \Drupal::service('uuid')->generate() . '.' . $extension;
    $fileUplPath = $globalVariables['applicationConfig']['config']['fileuploadPath'];

    try {
      // Upload using HTTP client with multipart
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
      // Optional profilePic update
      if ($request->request->get('userPic') === 'profilePic') {
        $profilePic = $fileUplPath . $uuidFilename;
        return $this->updateUserProfilePic($profilePic);
      }

      return new JsonResponse([
        'fileName' => $fileUplPath . $uuidFilename,
        'fileTypeId' => $fileTypeVal,
        'fileTypeVal' => $fileTypeType,
      ]);
    } catch (\Exception $e) {
      $this->logger->error('File upload failed: @error', ['@error' => $e->getMessage()]);
      return new JsonResponse(['status' => FALSE, 'message' => 'Upload error'], 500);
    }
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

  public function userDelete($userID, $tenantCode)
  {
    $globalVariables = $this->vaultConfigService->getGlobalVariables();
    $apiUrl = $globalVariables['apiManConfig']['config']['apiUrl'];
    $apiVer = $globalVariables['apiManConfig']['config']['apiVersion'];
    
    $cityUrl = $globalVariables['applicationConfig']['config']['deleteAPICA'] . $userID;
    \Drupal::logger('City App Delete Url')->notice($cityUrl);
    $deleteResponse = $this->apiHttpClientService->postApi($cityUrl);
    \Drupal::logger('Post Data response')->notice(print_r($deleteResponse, TRUE));
    if ($deleteResponse && isset($deleteResponse['status']) && $deleteResponse['status'] === TRUE) {

      $responseData = $this->apiHttpClientService->postApiman(
        $apiUrl . 'trinityengage-casemanagementsystem' . $apiVer . 'user/delete-user?userId=' . $userID . '&tenantCode=' . $tenantCode,
      );


      \Drupal::logger('CEP Delete API response')->notice(print_r($responseData, TRUE));
      if ($responseData['status']) {
        $account = User::load(\Drupal::currentUser()->id());
        $account->delete();
        return ['status' => TRUE, 'message' => 'User account deleted successfully!'];
      } else {
        return ['status' => FALSE, 'message' => 'Failed to delete user from case management system.'];
      }
    } else {
      return ['status' => FALSE, 'message' => 'Failed to delete user account.'];
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
