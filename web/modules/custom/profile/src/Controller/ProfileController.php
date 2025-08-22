<?php 

namespace Drupal\profile\Controller;

use Drupal\Core\Controller\ControllerBase;

class ProfileController extends ControllerBase {
  public function myAccount() {
    $form1 = \Drupal::formBuilder()->getForm('Drupal\profile\Form\ProfilePictureForm');
    $form2 = \Drupal::formBuilder()->getForm('Drupal\profile\Form\ProfileForm');
    return [
      '#theme' => 'profile',
      '#profile_picture_form' => $form1,
      '#form' => $form2,
      '#attached' => [
        'library' => [
          'profile/profile_assets',
        ],
      ],
    ];
  }
}
