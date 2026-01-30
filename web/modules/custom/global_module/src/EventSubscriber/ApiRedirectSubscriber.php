<?php

namespace Drupal\global_module\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Subscribes to kernel request event to call an API once per session.
 */
class ApiRedirectSubscriber implements EventSubscriberInterface
{

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array
  {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 30],
    ];
  }

  /**
   * Kernel request handler.
   */
  public function onKernelRequest(RequestEvent $event): void
  {
    if (!$this->shouldProcess($event)) {
      return;
    }

    $session = $event->getRequest()->getSession();

    if ($session->has('api_redirect_result')) {
      return;
    }

    $result = $this->callYourApi();
    $this->processApiResult($result, $session);

    if ($this->shouldRedirect($result)) {
      $this->redirectToFront($event);
    }
  }

  /* ============================================================
   * DECISION HELPERS
   * ============================================================ */

  private function shouldProcess(RequestEvent $event): bool
  {
    $request = $event->getRequest();

    return
      \Drupal::currentUser()->isAuthenticated()
      && !$request->isXmlHttpRequest()
      && $request->getRequestFormat() === 'html'
      && !$this->isAdminPath();
  }

  private function isAdminPath(): bool
  {
    $current_path = \Drupal::service('path.current')->getPath();
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath($current_path);

    return str_starts_with($alias, '/admin');
  }

  private function shouldRedirect($result): bool
  {
    return $result === 'redirect_me';
  }

  /* ============================================================
   * API RESULT HANDLING
   * ============================================================ */

  private function processApiResult($result, $session): void
  {
    if (!is_array($result)) {
      $this->logError('Invalid API response format.');
      return;
    }

    $processed = $this->decryptSensitiveFields($result);

    if (empty($processed['userId'])) {
      $session->remove('api_redirect_result');
      $this->logWarning('API response missing userId.');
      return;
    }

    $session->set('api_redirect_result', $processed);
    $this->logInfo('API data stored for userId: @uid', ['@uid' => $processed['userId']]);
  }

  private function decryptSensitiveFields(array $data): array
  {
    $globalService = \Drupal::service('global_module.global_variables');

    if (!empty($data['emailId'])) {
      $data['emailId'] = $globalService->decrypt($data['emailId']);
    }

    if (!empty($data['mobileNumber'])) {
      $data['mobileNumber'] = $globalService->decrypt($data['mobileNumber']);
    }

    return $data;
  }

  /* ============================================================
   * REDIRECT
   * ============================================================ */

  private function redirectToFront(RequestEvent $event): void
  {
    $url = Url::fromRoute('<front>')->toString();
    $event->setResponse(new RedirectResponse($url));
  }

  /* ============================================================
   * API CALL
   * ============================================================ */

  private function callYourApi()
  {
    try {
      $globalService = \Drupal::service('global_module.vault_config_service');
      $globals = $globalService->getGlobalVariables();
      $tokenService = \Drupal::service('global_module.apiman_token_service');

      $response = \Drupal::httpClient()->post(
        $globals['apiManConfig']['config']['apiUrl']
          . 'tiotcitizenapp'
          . $globals['apiManConfig']['config']['apiVersion']
          . 'user/details',
        [
          'headers' => [
            'Authorization' => 'Bearer ' . $tokenService->getApimanAccessToken(),
            'Content-Type' => 'application/json',
          ],
          'json' => [
            'userId' => \Drupal::currentUser()->getEmail(),
          ],
        ]
      );

      $decoded = json_decode($response->getBody(), TRUE);
      return $decoded['data'] ?? NULL;
    } catch (\Exception $e) {
      $this->logError('API call failed: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
  }

  /* ============================================================
   * LOGGING HELPERS
   * ============================================================ */

  private function logInfo(string $message, array $context = []): void
  {
    \Drupal::logger('api_subscriber')->info($message, $context);
  }

  private function logWarning(string $message): void
  {
    \Drupal::logger('api_subscriber')->warning($message);
  }

  private function logError(string $message, array $context = []): void
  {
    \Drupal::logger('api_subscriber')->error($message, $context);
  }
}
