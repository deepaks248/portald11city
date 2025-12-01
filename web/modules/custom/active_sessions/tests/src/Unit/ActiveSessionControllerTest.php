<?php

namespace Drupal\Tests\active_sessions\Controller;

use Drupal\active_sessions\Controller\ActiveSessionController;
use Drupal\login_logout\Service\OAuthLoginService;
use Drupal\active_sessions\Service\ActiveSessionService;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test class for ActiveSessionController.
 *
 * @group active_sessions
 */
class ActiveSessionControllerTest extends UnitTestCase
{
    /** @var OAuthLoginService|MockObject */
    protected $mockOAuthLoginService;

    /** @var RequestStack|MockObject */
    protected $mockRequestStack;

    /** @var ActiveSessionService|MockObject */
    protected $mockSessionService;

    /** @var DateFormatterInterface|MockObject */
    protected $mockDateFormatter;

    /** @var SessionInterface|MockObject */
    protected $mockSession;

    /** @var ActiveSessionController */
    protected $controller;

    protected function setUp(): void
    {
        // Mock dependencies
        $this->mockOAuthLoginService = $this->createMock(OAuthLoginService::class);
        $this->mockRequestStack = $this->createMock(RequestStack::class);
        $this->mockSessionService = $this->createMock(ActiveSessionService::class);
        $this->mockDateFormatter = $this->createMock(DateFormatterInterface::class);
        $this->mockSession = $this->createMock(SessionInterface::class);

        // Mock Drupal container services
        $container = new ContainerBuilder();
        $container->set('session', $this->mockSession);

        $container->set('messenger', $this->createMock(MessengerInterface::class));
        $container->set('string_translation', $this->createMock(TranslationInterface::class));

        \Drupal::setContainer($container);

        // Create controller
        $this->controller = new ActiveSessionController(
            $this->mockOAuthLoginService,
            $this->mockRequestStack,
            $this->mockSessionService,
            $this->mockDateFormatter
        );

        // Default session values
        $this->mockSession->method('get')
            ->willReturnMap([
                ['login_logout.access_token', 'dummy_access_token'],
                ['login_logout.login_time', 1234567890],
                ['login_logout.active_session_id_token', '123'],
            ]);
    }

    public function testActiveSession()
    {
        $this->mockSessionService->method('fetchActiveSessions')
            ->with('dummy_access_token')
            ->willReturn([
                'sessions' => [
                    ['id' => 1, 'loginTime' => 1234567890, 'userAgent' => 'Mozilla/5.0'],
                    ['id' => 2, 'loginTime' => 1234567900, 'userAgent' => 'Chrome'],
                ],
            ]);

        $this->mockDateFormatter->method('format')
            ->willReturn('01-01-2023, 12:00:00');

        $response = $this->controller->activeSession();

        $this->assertArrayHasKey('#title', $response);
        $this->assertArrayHasKey('#currentUserSessions', $response);
        $this->assertArrayHasKey('#otherUserSessions', $response);
        $this->assertEquals('Active Sessions', $response['#title']);
        $this->assertCount(1, $response['#currentUserSessions']);
        $this->assertCount(1, $response['#otherUserSessions']);
    }

    public function testEndSession()
    {
        $this->mockSessionService->expects($this->once())
            ->method('terminateSession')
            ->with('123', 'dummy_access_token')
            ->willReturn(TRUE); // normal flow

        $response = $this->controller->endSession('123--dummy_access_token');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/logout', $response->getTargetUrl());
    }

    public function testEndSessionWithError()
    {
        $this->mockSessionService->method('terminateSession')
            ->will($this->throwException(new \Exception('Error terminating session.')));

        $response = $this->controller->endSession('123--dummy_access_token');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/my-account', $response->getTargetUrl());
    }

    public function testEndAllSessions()
    {
        $this->mockSessionService->expects($this->once())
            ->method('terminateAllOtherSessions')
            ->with('dummy_access_token')
            ->willReturn(TRUE);

        $response = $this->controller->endAllSessions();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/logout', $response->getTargetUrl());
    }

    public function testEndAllSessionsWithError()
    {
        $this->mockSessionService->method('terminateAllOtherSessions')
            ->will($this->throwException(new \Exception('Error terminating all sessions.')));

        $response = $this->controller->endAllSessions();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/my-account', $response->getTargetUrl());
    }
}
