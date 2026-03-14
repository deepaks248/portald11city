<?php

namespace Drupal\Tests\secaudit\Unit\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\secaudit\Service\InputEncodingAuditService;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @group secaudit
 */
class InputEncodingAuditServiceTest extends UnitTestCase
{
  public function testEncodingDetectorsCoverPositiveAndSkipPaths(): void
  {
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory->method('get')->with('secaudit')->willReturn($logger);

    $requestStack = new RequestStack();
    $service = new class($requestStack, $loggerFactory) extends InputEncodingAuditService {
      public function callContainsHtmlEntity(string $value): bool {
        return $this->containsHTMLEntity($value);
      }
    };

    $service->detectEE1();
    $service->detectEE2();
    $this->assertTrue($service->callContainsHtmlEntity('&lt;script&gt;'));

    $request = Request::create('/path', 'GET', ['value' => '%253Cscript%253E']);
    $request->headers->set('x-real-ip', '127.0.0.1');
    $requestStack->push($request);
    $logger->expects($this->once())->method('warning');
    $service->detectEE1();
    $this->assertTrue($request->attributes->get('_secaudit_ee1_detected'));

    $requestStack2 = new RequestStack();
    $loggerFactory2 = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger2 = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory2->method('get')->with('secaudit')->willReturn($logger2);
    $service2 = new InputEncodingAuditService($requestStack2, $loggerFactory2);
    $request2 = Request::create('/path', 'GET', ['value' => '\x3cscript']);
    $request2->headers->set('x-real-ip', '127.0.0.1');
    $requestStack2->push($request2);
    $logger2->expects($this->once())->method('warning');
    $service2->detectEE2();
    $this->assertTrue($request2->attributes->get('_secaudit_ee2_detected'));

    $request3 = Request::create('/path');
    $request3->attributes->set('_secaudit_ee2_detected', TRUE);
    $requestStack3 = new RequestStack();
    $requestStack3->push($request3);
    $loggerFactory3 = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger3 = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory3->method('get')->with('secaudit')->willReturn($logger3);
    $logger3->expects($this->never())->method('warning');
    (new InputEncodingAuditService($requestStack3, $loggerFactory3))->detectEE2();
  }

  public function testDetectEe1HandlesDoubleHtmlEncoding(): void
  {
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory->method('get')->with('secaudit')->willReturn($logger);
    $requestStack = new RequestStack();
    $service = new InputEncodingAuditService($requestStack, $loggerFactory);

    $request = Request::create('/path', 'GET', ['value' => '&amp;lt;script&amp;gt;']);
    $request->headers->set('x-real-ip', '127.0.0.1');
    $requestStack->push($request);
    $logger->expects($this->once())->method('warning');

    $service->detectEE1();
    $this->assertTrue($request->attributes->get('_secaudit_ee1_detected'));
  }

  public function testDetectEe2CoversAdditionalEncodingReasons(): void
  {
    $cases = [
      ['value' => '\u003c', 'reason' => 'unicode'],
      ['value' => '\074', 'reason' => 'octal'],
      ['value' => '%3c\u003c', 'reason' => 'mixed'],
      ['value' => chr(1) . 'abc', 'reason' => 'binary'],
      ['value' => "\x00A\x00", 'reason' => 'null_padding'],
    ];

    foreach ($cases as $case) {
      $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
      $logger = $this->createMock(LoggerChannelInterface::class);
      $loggerFactory->method('get')->with('secaudit')->willReturn($logger);
      $requestStack = new RequestStack();
      $service = new InputEncodingAuditService($requestStack, $loggerFactory);

      $request = Request::create('/path', 'GET', ['value' => $case['value']]);
      $request->headers->set('x-real-ip', '127.0.0.1');
      $requestStack->push($request);
      $logger->expects($this->once())->method('warning');

      $service->detectEE2();
      $this->assertTrue($request->attributes->get('_secaudit_ee2_detected'));
    }
  }
}
