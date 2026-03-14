<?php

namespace Drupal\login_logout\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\global_module\Service\ApimanTokenService;
use Drupal\global_module\Service\VaultConfigService;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Handles user registration form submissions.
 */
class UserRegistrationSubmitHandler
{
  use StringTranslationTrait;

  protected const OTP_EVENT = 'get_users_limit';
  protected const OTP_LIMIT = 1;
  protected const OTP_WINDOW = 120;

  /**
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * @var \Drupal\global_module\Service\VaultConfigService
   */
  protected $vaultConfigService;

  /**
   * @var \Drupal\global_module\Service\ApimanTokenService
   */
  protected $apimanTokenService;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * @var \Drupal\login_logout\Service\UserRegistrationAccountManager
   */
  protected $accountManager;

  /**
   * Creates the handler.
   */
  public function __construct(
    ClientInterface $httpClient,
    VaultConfigService $vaultConfigService,
    ApimanTokenService $apimanTokenService,
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    RequestStack $requestStack,
    SessionInterface $session,
    FloodInterface $flood,
    LockBackendInterface $lock,
    Connection $database,
    LoggerChannelFactoryInterface $loggerFactory,
    UserRegistrationAccountManager $accountManager
  ) {
    $this->httpClient = $httpClient;
    $this->vaultConfigService = $vaultConfigService;
    $this->apimanTokenService = $apimanTokenService;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
    $this->session = $session;
    $this->flood = $flood;
    $this->lock = $lock;
    $this->database = $database;
    $this->loggerFactory = $loggerFactory;
    $this->accountManager = $accountManager;
  }

  /**
   * Handles the multi-step registration form submission.
   */
  public function handleFormSubmission(array &$form, FormStateInterface $form_state): ?JsonResponse
  {
    unset($form);

    return match ($form_state->get('phase') ?? 1) {
      1 => $this->handleOtpRequest($form_state),
      2 => $this->handleOtpVerification($form_state),
      3 => $this->handleFinalRegistration($form_state),
      default => NULL,
    };
  }

  /**
   * Handles phase 1 OTP generation and delivery.
   */
  protected function handleOtpRequest(FormStateInterface $form_state): ?JsonResponse
  {
    $data = $this->collectRegistrationData($form_state);
    $this->logUsernameAnomalies((string) $data['mail']);

    if ($this->emailAlreadyRegistered((string) $data['mail'])) {
      $this->messenger->addError($this->t('Email already registered.'));
      return NULL;
    }

    $form_state->set('user_data', $data);
    $response = NULL;

    try {
      $response = $this->processOtpRequest($data);
    } catch (\Exception $e) {
      $this->loggerFactory->get('register_api')->error('OTP rate limit or lock error: @msg', ['@msg' => $e->getMessage()]);
      $this->messenger->addError($this->t('An unexpected error occurred. Please try again later.'));
      $response = new JsonResponse([
        'status' => FALSE,
        'message' => 'An unexpected error occurred. Please try again later.',
      ], 500);
    }

    if (!$response instanceof JsonResponse) {
      $form_state->set('phase', 2);
      $form_state->setRebuild();
    }

    return $response;
  }

  /**
   * Processes OTP throttling and delivery.
   */
  protected function processOtpRequest(array $data): ?JsonResponse
  {
    $rateLimitResponse = $this->enforceOtpRateLimit((string) $data['mail']);
    if ($rateLimitResponse instanceof JsonResponse) {
      return $rateLimitResponse;
    }

    $otp = $this->generateOtp();
    $identifier = $this->buildOtpIdentifier((string) $data['mail']);
    return $this->sendOtpWithLock($data, $otp, $identifier);
  }

  /**
   * Handles phase 2 OTP verification.
   */
  protected function handleOtpVerification(FormStateInterface $form_state): ?JsonResponse
  {
    if ($form_state->getValue('otp') !== $form_state->get('otp_code')) {
      $this->messenger->addError($this->t('Invalid OTP. Please try again.'));
      $form_state->setRebuild();
      return NULL;
    }

    $form_state->set('phase', 3);
    $form_state->setRebuild();
    return NULL;
  }

  /**
   * Handles phase 3 final registration and login.
   */
  protected function handleFinalRegistration(FormStateInterface $form_state): ?JsonResponse
  {
    $password = (string) $form_state->getValue('password');
    $confirmPassword = (string) $form_state->getValue('confirm_password');
    $this->logPasswordAnomalies($password);

    if (!$this->passwordsMatch($password, $confirmPassword, $form_state)) {
      return NULL;
    }

    $data = $form_state->get('user_data') ?? [];
    if (!$this->registerApiUser($data)) {
      return NULL;
    }

    $this->registerScimUser($data, $password);
    $this->accountManager->finalizeRegistration($data, $password, $form_state);
    return NULL;
  }

  /**
   * Collects user data from the form.
   */
  protected function collectRegistrationData(FormStateInterface $form_state): array
  {
    return [
      'first_name' => $form_state->getValue('first_name'),
      'last_name' => $form_state->getValue('last_name'),
      'mail' => $form_state->getValue('mail'),
      'country_code' => $form_state->getValue('country_code'),
      'mobile' => $form_state->getValue('mobile'),
    ];
  }

  /**
   * Records username anomaly events.
   */
  protected function logUsernameAnomalies(string $username): void
  {
    $context = $this->buildAuditContext();
    $length = strlen($username);

    if ($length < 5 || $length > 254) {
      $this->loggerFactory->get('secaudit')->warning(
        'AE4: Abnormal username length detected for User Id: @uid, IP: @ip, Length: @length',
        $context + ['@length' => $length]
      );
    }

    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
      $this->loggerFactory->get('secaudit')->warning(
        'AE6: Unexpected characters or format in username detected IP: @ip for User ID: @uid',
        $context + ['@username_sample' => substr($username, 0, 50)]
      );
    }
  }

  /**
   * Records password anomaly events.
   */
  protected function logPasswordAnomalies(string $password): void
  {
    $context = $this->buildAuditContext();
    $length = strlen($password);

    if ($length < 8 || $length > 128) {
      $this->loggerFactory->get('secaudit')->warning(
        'AE5: Abnormal password length detected for User Id: @uid, IP: @ip, Length: @length',
        $context + ['@length' => $length]
      );
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $password)) {
      $this->loggerFactory->get('secaudit')->warning(
        'AE7: Control characters detected in password for User Id: @uid, IP: @ip',
        $context
      );
    }
  }

  /**
   * Builds common security audit context.
   */
  protected function buildAuditContext(): array
  {
    return [
      '@uid' => $this->currentUser->id() ?: 0,
      '@ip' => $this->getClientIp(),
    ];
  }

  /**
   * Checks whether a Drupal account already exists for the email.
   */
  protected function emailAlreadyRegistered(string $email): bool
  {
    $users = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);

    return !empty($users);
  }

  /**
   * Applies the OTP rate limit and returns a response if blocked.
   */
  protected function enforceOtpRateLimit(string $email): ?JsonResponse
  {
    $identifier = $this->buildOtpIdentifier($email);
    if ($this->flood->isAllowed(self::OTP_EVENT, self::OTP_LIMIT, self::OTP_WINDOW, $identifier)) {
      return NULL;
    }

    $remaining = $this->getOtpWaitTime($identifier);
    $message = "Rate limit exceeded. Please wait {$remaining} seconds...";
    $this->messenger->addError($this->t(
      '<span class="rate-limit-message" data-wait="@time">@msg</span>',
      [
        '@time' => $remaining,
        '@msg' => $message,
      ]
    ));

    return new JsonResponse([
      'status' => FALSE,
      'message' => $message,
    ], 429);
  }

  /**
   * Builds the flood identifier for OTP requests.
   */
  protected function buildOtpIdentifier(string $email): string
  {
    return 'otp:' . $email . ':' . $this->session->getId();
  }

  /**
   * Reads the remaining wait time for a throttled OTP request.
   */
  protected function getOtpWaitTime(string $identifier): int
  {
    $lastEvent = $this->database->select('flood', 'f')
      ->fields('f', ['timestamp'])
      ->condition('event', self::OTP_EVENT)
      ->condition('identifier', $identifier)
      ->orderBy('timestamp', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchField();

    if (!$lastEvent) {
      return self::OTP_WINDOW;
    }

    $elapsed = \Drupal::time()->getCurrentTime() - $lastEvent;
    return max(self::OTP_WINDOW - $elapsed, 0);
  }

  /**
   * Sends the OTP while holding a per-email lock.
   */
  protected function sendOtpWithLock(array $data, string $otp, string $identifier): ?JsonResponse
  {
    $lockKey = 'otp_lock:' . $data['mail'];
    if (!$this->lock->acquire($lockKey)) {
      $this->messenger->addError($this->t('Unable to process OTP request. Please try again.'));
      return new JsonResponse([
        'status' => FALSE,
        'message' => 'Unable to process your request at the moment. Please try again later.',
      ], 503);
    }

    try {
      $this->sendOtpWebhookRequest($data, $otp);
      $this->messenger->addStatus($this->t('OTP sent to your mobile/email.'));
      $this->flood->register(self::OTP_EVENT, self::OTP_WINDOW, $identifier);
      return NULL;
    } catch (\Exception $e) {
      $this->loggerFactory->get('register_api')->error('OTP webhook failed: @msg', ['@msg' => $e->getMessage()]);
      $this->messenger->addError($this->t('Failed to send OTP. Please try again later.'));

      return new JsonResponse([
        'status' => FALSE,
        'message' => 'An error occurred while processing your request. Please try again later.',
      ], 500);
    } finally {
      $this->lock->release($lockKey);
    }
  }

  /**
   * Sends the OTP webhook request.
   */
  protected function sendOtpWebhookRequest(array $data, string $otp): void
  {
    $formName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
    $webhookUrl = $this->vaultConfigService
      ->getGlobalVariables()['applicationConfig']['config']['otpWebhookUrl'];

    $this->httpClient->request('POST', $webhookUrl, [
      'headers' => ['Content-Type' => 'application/json'],
      'json' => [
        'email' => $data['mail'],
        'mobile' => $data['mobile'],
        'otp' => $otp,
        'name' => $formName,
      ],
      'verify' => FALSE,
    ]);
  }

  /**
   * Registers the user in the external portal API.
   */
  protected function registerApiUser(array $data): bool
  {
    try {
      $accessToken = $this->apimanTokenService->getApimanAccessToken();
      $globalVariables = $this->vaultConfigService->getGlobalVariables();
      $endpoint = $globalVariables['apiManConfig']['config']['apiUrl']
        . 'tiotcitizenapp'
        . $globalVariables['apiManConfig']['config']['apiVersion']
        . 'user/register';

      $this->httpClient->request('POST', $endpoint, [
        'headers' => [
          'accept' => 'application/hal+json',
          'Content-Type' => 'application/json',
          'Authorization' => 'Bearer ' . $accessToken,
        ],
        'json' => [
          'firstName' => $data['first_name'],
          'lastName' => $data['last_name'],
          'mobileNumber' => $data['mobile'],
          'emailId' => $data['mail'],
          'tenantCode' => $globalVariables['applicationConfig']['config']['ceptenantCode'],
          'countryCode' => $data['country_code'],
        ],
        'verify' => FALSE,
      ]);

      return TRUE;
    } catch (RequestException $e) {
      $body = $e->getResponse()?->getBody()->getContents();
      $message = json_decode((string) $body, TRUE)['developerMessage'] ?? $e->getMessage();
      $this->messenger->addError($this->t('Registration failed: @msg', ['@msg' => $message]));
      return FALSE;
    }
  }

  /**
   * Registers the user in SCIM.
   */
  protected function registerScimUser(array $data, string $password): void
  {
    try {
      $idamconfig = $this->vaultConfigService
        ->getGlobalVariables()['applicationConfig']['config']['idamconfig'];

      $this->httpClient->request('POST', 'https://' . $idamconfig . '/scim2/Users/', [
        'headers' => [
          'accept' => 'application/scim+json',
          'Content-Type' => 'application/scim+json',
          'Authorization' => 'Basic ' . base64_encode('trinity:trinity@123'),
        ],
        'json' => [
          'schemas' => [],
          'name' => [
            'givenName' => $data['first_name'],
            'familyName' => $data['last_name'],
          ],
          'userName' => $data['first_name'],
          'password' => $password,
          'emails' => [['value' => $data['mail']]],
          'phoneNumbers' => [['value' => $data['mobile'], 'type' => 'mobile']],
        ],
        'verify' => FALSE,
      ]);
    } catch (RequestException $e) {
      $this->loggerFactory->get('scim_user')->error('SCIM user creation failed: @error', ['@error' => $e->getMessage()]);
      $detail = json_decode((string) $e->getResponse()?->getBody()->getContents(), TRUE)['detail'] ?? '';
      $parts = explode('-', $detail, 2);
      $message = $parts[1] ?? $detail ?: $e->getMessage();
      $this->messenger->addError('Error: ' . $message);
    }
  }

  protected function passwordsMatch(string $password, string $confirmPassword, FormStateInterface $form_state): bool
  {
    if ($password === $confirmPassword) {
      return TRUE;
    }

    $this->messenger->addError($this->t('Passwords do not match.'));
    $form_state->setRebuild();
    return FALSE;
  }

  /**
   * Generates a six-digit OTP string.
   */
  protected function generateOtp(): string
  {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  }

  /**
   * Returns the request IP while preserving x-real-ip behaviour.
   */
  protected function getClientIp(): string
  {
    $request = $this->requestStack->getCurrentRequest();
    $headers = $request?->headers->all() ?? [];
    return $headers['x-real-ip'][0] ?? 'UNKNOWN';
  }
}
