<?php

namespace Drupal\active_sessions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\login_logout\Service\OAuthLoginService;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\active_sessions\Service\ActiveSessionService;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ActiveSessionController extends ControllerBase
{

    protected $oauthLoginService;
    protected $requestStack;
    protected $sessionService;
    protected $dateFormatter;

    public function __construct(
        OAuthLoginService $oauthLoginService,
        RequestStack $requestStack,
        ActiveSessionService $sessionService,
        DateFormatterInterface $dateFormatter
    ) {
        $this->oauthLoginService = $oauthLoginService;
        $this->requestStack = $requestStack;
        $this->sessionService = $sessionService;
        $this->dateFormatter = $dateFormatter;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('login_logout.oauth_login_service'),
            $container->get('request_stack'),
            $container->get('active_sessions.session_service'),
            $container->get('date.formatter')
        );
    }

    private function formatUserAgent($userAgent)
    {
        $browser = $userAgent;
        $device  = $userAgent;

        // Detect browser
        switch (true) {
            case stripos($userAgent, 'Edg') !== false:
                $browser = 'Microsoft Edge';
                break;
            case stripos($userAgent, 'Chrome') !== false && stripos($userAgent, 'Chromium') === false:
                $browser = 'Chrome';
                break;
            case stripos($userAgent, 'Firefox') !== false:
                $browser = 'Firefox';
                break;
            case stripos($userAgent, 'Safari') !== false && stripos($userAgent, 'Chrome') === false:
                $browser = 'Safari';
                break;
            case stripos($userAgent, 'Opera') !== false || stripos($userAgent, 'OPR') !== false:
                $browser = 'Opera';
                break;
        }

        // Detect device/OS
        switch (true) {
            case stripos($userAgent, 'Windows') !== false:
                $device = 'Desktop (Windows)';
                break;
            case stripos($userAgent, 'Macintosh') !== false || stripos($userAgent, 'Mac OS X') !== false:
                $device = 'Desktop (Mac)';
                break;
            case stripos($userAgent, 'iPhone') !== false:
                $device = 'Mobile (iPhone)';
                break;
            case stripos($userAgent, 'iPad') !== false:
                $device = 'Tablet (iPad)';
                break;
            case stripos($userAgent, 'Android') !== false && stripos($userAgent, 'Mobile') !== false:
                $device = 'Mobile (Android)';
                break;
            case stripos($userAgent, 'Android') !== false:
                $device = 'Tablet (Android)';
                break;
            case stripos($userAgent, 'Linux') !== false:
                $device = 'Linux';
                break;
        }

        if ($browser === $userAgent && $device === $userAgent) {
            return $userAgent;
        }
        return "{$browser}, {$device}";
    }

    public function activeSession()
    {
        $session = \Drupal::service('session');
        $accessToken = $session->get('login_logout.access_token');
        $storedLoginTime = $session->get('login_logout.login_time');
        // print_r("<pre>");
        // dump($storedLoginTime);
        // dump($accessToken);
        // Fetch sessions from API
        $sessions = $this->sessionService->fetchActiveSessions($accessToken);
        $apiSessions = $sessions['sessions'] ?? [];
        // dump($apiSessions);
        $currentUserSessions = [];
        $otherUserSessions = [];

        $closestSessionId = null;
        if (!empty($storedLoginTime) && !empty($apiSessions)) {
            $targetTimeMs = $storedLoginTime * 1000;
            $closestDiff = PHP_INT_MAX;

            foreach ($apiSessions as $apiSession) {
                if (!empty($apiSession['loginTime'])) {
                    $diff = abs($apiSession['loginTime'] - $targetTimeMs);

                    // short-circuit if exact match
                    if ($diff === 0) {
                        $closestSessionId = $apiSession['id'];
                        break;
                    }

                    if ($diff < $closestDiff) {
                        $closestDiff = $diff;
                        $closestSessionId = $apiSession['id'];
                    }
                }
            }
        }

        foreach ($apiSessions as &$apiSession) {
            $timestamp = (int) ($apiSession['loginTime'] / 1000);
            $apiSession['accessToken'] = $accessToken;
            $apiSession['userAgentFormatted'] = $this->formatUserAgent($apiSession['userAgent'] ?? '');
            $apiSession['loginTimeSeconds'] = $timestamp;
            $apiSession['formattedLoginTime'] = $this->dateFormatter
                ->format($timestamp, 'custom', 'd-m-Y, h:i:s', 'Asia/Kolkata');

            if ($apiSession['id'] === $closestSessionId) {
                $currentUserSessions[] = $apiSession;
            } else {
                $otherUserSessions[] = $apiSession;
            }
        }

        // dump($currentUserSessions);
        // dump($otherUserSessions);
        // exit();

        return [
            '#title' => $this->t('Active Sessions'),
            '#theme' => 'active_sessions_page',
            '#currentUserSessions' => $currentUserSessions,
            '#otherUserSessions' => $otherUserSessions,
            '#cache' => ['max-age' => 0],
        ];
    }

    public function endSession(string $session_id)
    {
        $session = \Drupal::service('session');
        $accessToken = $session->get('login_logout.access_token');
        $active_session_id_token = $session->get('login_logout.active_session_id_token');

        if ($accessToken === null) {
            $this->messenger()->addError($this->t('Failed to retrieve access token.'));
            return new RedirectResponse('/my-account');
        }

        [$id, $access_token] = explode('--', $session_id) + [null, null];

        // print_r("<pre>");
        // dump($accessToken);
        // dump($session_id);
        // dump($id);
        // dump($session->get('login_logout.active_session_id_token'));
        // dump($active_session_id_token);
        // dump($id == $active_session_id_token);
        // exit();
        try {
            $is_my_session = ($active_session_id_token == $id);
            if ($is_my_session) {
                $this->sessionService->terminateSession($active_session_id_token, $accessToken);
                return new RedirectResponse('/logout');
            } else {
                $this->sessionService->terminateSession($id, $accessToken);
                return new RedirectResponse('/my-account');
            }
        } catch (\Exception $e) {
            $this->messenger()->addError($this->t('An error occurred while ending the session: @message', ['@message' => $e->getMessage()]));
            return new RedirectResponse('/my-account');
        }
    }

    public function endAllSessions()
    {
        $session = \Drupal::service('session');
        $accessToken = $session->get('login_logout.access_token');
        // $active_session_id_token = $session->get('login_logout.active_session_id_token');

        if ($accessToken === null) {
            $this->messenger()->addError($this->t('Failed to retrieve access token.'));
            return new RedirectResponse('/my-account');
        }

        try {
            $this->sessionService->terminateAllOtherSessions($accessToken);
            return new RedirectResponse('/logout');
        } catch (\Exception $e) {
            $this->messenger()->addError($this->t('An error occurred while ending all sessions: @message', ['@message' => $e->getMessage()]));
            return new RedirectResponse('/my-account');
        }
    }
}
