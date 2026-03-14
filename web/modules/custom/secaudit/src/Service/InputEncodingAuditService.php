<?php

declare(strict_types=1);

namespace Drupal\secaudit\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Audits encoding-related request anomalies.
 */
class InputEncodingAuditService
{
  protected RequestStack $requestStack;
  protected LoggerChannelFactoryInterface $loggerFactory;

  public function __construct(RequestStack $request_stack, LoggerChannelFactoryInterface $logger_factory)
  {
    $this->requestStack = $request_stack;
    $this->loggerFactory = $logger_factory;
  }

  public function detectEE1(): void
  {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return;
    }

    $inputs = array_merge(
      $request->query->all(),
      $request->request->all(),
      $request->cookies->all()
    );

    foreach ($inputs as $value) {
      if (!is_scalar($value)) {
        continue;
      }

      $value = (string) $value;
      $once = rawurldecode($value);
      $twice = rawurldecode($once);

      if ($twice !== $once || $this->containsHTMLEntity($twice)) {
        $this->logEE1($request, $value, 'double_url_encoding');
        $request->attributes->set('_secaudit_ee1_detected', TRUE);
        return;
      }

      $once = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $twice = html_entity_decode($once, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      if ($twice !== $once) {
        $this->logEE1($request, $value, 'double_html_encoding');
        $request->attributes->set('_secaudit_ee1_detected', TRUE);
        return;
      }
    }
  }

  public function detectEE2(): void
  {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request || $request->attributes->get('_secaudit_ee2_detected')) {
      return;
    }

    $inputs = array_merge(
      $request->query->all(),
      $request->request->all(),
      $request->cookies->all()
    );

    foreach ($inputs as $value) {
      if (!is_scalar($value)) {
        continue;
      }

      $value = (string) $value;
      $reason = $this->detectUnexpectedEncodingReason($value);
      if ($reason !== NULL) {
        $this->logEE2($request, $value, $reason);
        break;
      }
    }
  }

  /**
   * Returns the first matching unexpected encoding reason.
   */
  protected function detectUnexpectedEncodingReason(string $value): ?string
  {
    $checks = [
      'mixed_encoding_styles' => fn(string $candidate): bool => preg_match('/%[0-9a-fA-F]{2}/', $candidate)
        && (preg_match('/\\\\x[0-9a-fA-F]{2}/', $candidate) || preg_match('/\\\\u[0-9a-fA-F]{4}/', $candidate)),
      'hex_encoding' => fn(string $candidate): bool => preg_match('/\\\\x[0-9a-fA-F]{2}/', $candidate) === 1,
      'unicode_escape_encoding' => fn(string $candidate): bool => preg_match('/\\\\u[0-9a-fA-F]{4}/', $candidate) === 1,
      'octal_encoding' => fn(string $candidate): bool => preg_match('/\\\\[0-7]{2,3}/', $candidate) === 1,
      'multi_byte_null_padding' => fn(string $candidate): bool => preg_match('/\x00.\x00/', $candidate) === 1
        || preg_match('/.\x00.\x00/', $candidate) === 1,
      'binary_or_control_characters' => fn(string $candidate): bool => preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $candidate) === 1,
    ];

    foreach ($checks as $reason => $matcher) {
      if ($matcher($value)) {
        return $reason;
      }
    }

    return NULL;
  }

  protected function containsHTMLEntity(string $value): bool
  {
    return preg_match('/&(lt|gt|amp|quot|apos|#\d+);/', $value) === 1;
  }

  protected function logEE1($request, string $value, string $reason): void
  {
    $this->loggerFactory->get('secaudit')->warning(
      'EE1: Double Encoded Characters detected. IP: @ip, Path: @path, Reason: @reason, Sample Value: @sample',
      [
        '@ip' => $request->headers->all()['x-real-ip'][0] ?? $request->getClientIp(),
        '@path' => $request->getPathInfo(),
        '@reason' => $reason,
        '@sample' => substr($value, 0, 200),
      ]
    );
  }

  protected function logEE2($request, string $value, string $reason): void
  {
    $this->loggerFactory->get('secaudit')->warning(
      'EE2: Unexpected encoding used. IP: @ip, Path: @path, Reason: @reason, Sample: @sample',
      [
        '@ip' => $request->headers->all()['x-real-ip'][0] ?? $request->getClientIp(),
        '@path' => $request->getPathInfo(),
        '@reason' => $reason,
        '@sample' => substr($value, 0, 200),
      ]
    );

    $request->attributes->set('_secaudit_ee2_detected', TRUE);
  }
}
