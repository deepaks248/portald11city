<?php

namespace Drupal\Tests\profile\Unit\Plugin\Block;

use Drupal\profile\Plugin\Block\FamilyMembersBlock;
use Drupal\profile\Service\ProfileService;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @coversDefaultClass \Drupal\profile\Plugin\Block\FamilyMembersBlock
 * @group profile
 */
class FamilyMembersBlockTest extends UnitTestCase {

  /**
   * @var \Drupal\profile\Service\ProfileService|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $profileService;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $requestStack;

  /**
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $session;

  /**
   * @var \Drupal\profile\Plugin\Block\FamilyMembersBlock
   */
  protected $block;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->profileService = $this->createMock(ProfileService::class);
    $this->requestStack = $this->createMock(RequestStack::class);
    $this->session = $this->createMock(SessionInterface::class);

    $request = $this->createMock(Request::class);
    $request->method('getSession')->willReturn($this->session);
    $this->requestStack->method('getCurrentRequest')->willReturn($request);

    $container = new ContainerBuilder();
    $container->set('profile.profile_service', $this->profileService);
    $container->set('request_stack', $this->requestStack);
    \Drupal::setContainer($container);

    $this->block = new FamilyMembersBlock([], 'profile_family_members_block', [], $this->profileService);
  }

  /**
   * @covers ::create
   */
  public function testCreate() {
    $container = \Drupal::getContainer();
    $block = FamilyMembersBlock::create($container, [], 'profile_family_members_block', []);
    $this->assertInstanceOf(FamilyMembersBlock::class, $block);
  }

  /**
   * @covers ::build
   */
  public function testBuildWithUserId() {
    $user_id = 123;
    $this->session->method('get')->with('api_redirect_result')->willReturn(['userId' => $user_id]);
    
    $members = [['name' => 'Member 1']];
    $this->profileService->expects($this->once())
      ->method('fetchFamilyMembers')
      ->with($user_id)
      ->willReturn($members);

    $build = $this->block->build();

    $this->assertEquals('family_members_block', $build['#theme']);
    $this->assertEquals($members, $build['#members']);
  }

  /**
   * @covers ::build
   */
  public function testBuildWithoutUserId() {
    $this->session->method('get')->with('api_redirect_result')->willReturn([]);
    
    $this->profileService->expects($this->never())
      ->method('fetchFamilyMembers');

    $build = $this->block->build();

    $this->assertEquals('family_members_block', $build['#theme']);
    $this->assertEmpty($build['#members']);
  }

}
