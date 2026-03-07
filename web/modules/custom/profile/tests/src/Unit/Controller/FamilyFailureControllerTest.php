<?php

namespace Drupal\Tests\profile\Unit\Controller;

use Drupal\profile\Controller\FamilyFailureController;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\profile\Controller\FamilyFailureController
 * @group profile
 */
class FamilyFailureControllerTest extends UnitTestCase {

  /**
   * @covers ::content
   */
  public function testContent() {
    $controller = new FamilyFailureController();
    $result = $controller->content();
    $this->assertEquals('failed-family-member', $result['#theme']);
  }

}
