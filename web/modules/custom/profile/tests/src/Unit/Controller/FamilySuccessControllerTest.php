<?php

namespace Drupal\Tests\profile\Unit\Controller;

use Drupal\profile\Controller\FamilySuccessController;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\profile\Controller\FamilySuccessController
 * @group profile
 */
class FamilySuccessControllerTest extends UnitTestCase {

  /**
   * @covers ::content
   */
  public function testContent() {
    $controller = new FamilySuccessController();
    $result = $controller->content();
    $this->assertEquals('success-family-member', $result['#theme']);
  }

}
