<?php

namespace Drupal\page_visit_counter\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Core\State\StateInterface;

class RouteChangeSubscriber implements EventSubscriberInterface
{

  protected SessionInterface $session;
  protected StateInterface $state;

  public function __construct(SessionInterface $session, StateInterface $state)
  {
    $this->session = $session;
    $this->state = $state;
  }

  public static function getSubscribedEvents(): array
  {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse', 30],
    ];
  }

  public function onKernelResponse(ResponseEvent $event): void
  {
    // Only process main HTML requests
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $response = $event->getResponse();

    // Skip AJAX or non-HTML responses
    if ($request->isXmlHttpRequest() || $request->getRequestFormat() !== 'html') {
      return;
    }

    // Only increment if it's a 200 OK response
    if ($response->getStatusCode() !== 200) {
      return;
    }

    $current_path = $request->getPathInfo();

    // Ignore admin/system/internal paths
    if (
      $current_path !== null && substr($current_path, 0, strlen('/admin')) === '/admin' ||
      $current_path !== null && substr($current_path, 0, strlen('/core')) === '/core' ||
      $current_path !== null && substr($current_path, 0, strlen('/system')) === '/system' ||
      $current_path !== null && substr($current_path, 0, strlen('/_')) === '/_'
    ) {
      return;
    }

    // Prevent counting reloads of same page in quick succession
    if ($this->session->get('page_visit_counted_for') === $current_path) {
      return;
    }

    // Increment counter
    $count = $this->state->get('page_visit_counter.count', 0);
    $this->state->set('page_visit_counter.count', $count + 1);

    $this->session->set('page_visit_counted_for', $current_path);
  }
}
