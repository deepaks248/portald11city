<?php

namespace Drupal\login_logout\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
// use Drupal\login_logout\Service\OAuthLoginService;
// use Drupal\active_sessions\Service\ActiveSessionService;

class LogoutController extends ControllerBase
{

    protected $currentUser;
    protected $sessionManager;
    protected $requestStack;

    public function __construct(AccountProxyInterface $current_user, SessionManagerInterface $session_manager, RequestStack $request_stack)
    {
        $this->currentUser = $current_user;
        $this->sessionManager = $session_manager;
        $this->requestStack = $request_stack;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('current_user'),
            $container->get('session_manager'),
            $container->get('request_stack')
        );
    }

    public function logout()
    {
        if ($this->currentUser->isAuthenticated()) {
            $this->sessionManager->destroy();
            // $this->currentUser->logout(); // Optional — mostly handled by destroy()
            $this->messenger()->addStatus($this->t('You have been logged out.'));
        }

        return new RedirectResponse('/');
    }
}
