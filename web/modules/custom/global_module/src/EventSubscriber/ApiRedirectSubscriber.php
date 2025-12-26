<?php

namespace Drupal\global_module\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Subscribes to kernel request event to call an API once per session for logged-in users.
 */
class ApiRedirectSubscriber implements EventSubscriberInterface
{

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents()
  {
    return [
      KernelEvents::REQUEST => ['checkApiAndRedirect', 30],
    ];
  }

  /**
   * Call the API once per session and optionally redirect.
   */
  public function checkApiAndRedirect(RequestEvent $event)
  {
    $request = $event->getRequest();
    $session = $request->getSession();
    $current_user = \Drupal::currentUser();

    // Run only for authenticated users
    if (!$current_user->isAuthenticated()) {
      return;
    }

    // Run only on HTML main requests, skip AJAX, JSON, etc.
    if (!$request->isXmlHttpRequest() && $request->getRequestFormat() === 'html') {
      // Skip admin pages
      $current_path = \Drupal::service('path.current')->getPath();
      $path_alias = \Drupal::service('path_alias.manager')->getAliasByPath($current_path);
      if (strpos($path_alias, '/admin') === 0) {
        return;
      }

      // Only call API once per session
      //   $session = \Drupal::request()->getSession();
      // $session->remove('api_redirect_result');
      //     dump($session->all(), 'Session contents');
      if (!$session->has('api_redirect_result')) {
        $result = $this->callYourApi();

        // Defensive check if result is array
        if (is_array($result)) {
          $global_service = \Drupal::service('global_module.global_variables');
          $result['emailId'] = $global_service->decrypt($result['emailId']);
          $result['mobileNumber'] = $global_service->decrypt($result['mobileNumber']);

          // Check if userId is present
          if (!empty($result['userId'])) {
            $session->set('api_redirect_result', $result);
            \Drupal::logger('api_subscriber')->info('API data stored in session for userId: @uid', ['@uid' => $result['userId']]);
          } else {
            // Remove incomplete data
            $session->remove('api_redirect_result');
            \Drupal::logger('api_subscriber')->warning('API response missing userId. Session data not stored.');
          }
        } else {
          \Drupal::logger('api_subscriber')->error('Invalid API response format.');
        }

        // Optional redirect condition
        if ($result === 'redirect_me') {
          $url = Url::fromRoute('<front>')->toString();
          $event->setResponse(new RedirectResponse($url));
        }
      }
    }
  }

  /**
   * Make the actual API call.
   *
   * @return mixed
   *   API result (array, string, etc.) depending on your API.
   */




  private function callYourApi()
  {
    // dump("HttpClient");exit;
    try {

      $global_service = \Drupal::service('global_module.global_variables');

      $globalVariables = $global_service->getGlobalVariables();
      $access_token = $global_service->getApimanAccessToken();
      $client = \Drupal::httpClient();
      $email = \Drupal::currentUser()->getEmail();
      // $email = 'deepak.s@trinitymobility.com';
      $payload = [
        'userId' => $email
      ];
      //   echo $globalVariables['apiManConfig']['config']['apiUrl'] . 'tiotcitizenapp' . $globalVariables['apiManConfig']['config']['apiVersion'] . 'user/details';
      $response = $client->post(
        $globalVariables['apiManConfig']['config']['apiUrl'] . 'tiotcitizenapp' . $globalVariables['apiManConfig']['config']['apiVersion'] . 'user/details',
        [
          'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
          ],
          'json' => $payload,
        ]
      );
      $data = json_decode($response->getBody(), true);
      // dump("Data",$data);
      return $data['data'] ?? NULL; // adjust key as per your API
    } catch (\Exception $e) {
      \Drupal::logger('global_module')->error('API call failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }
}
