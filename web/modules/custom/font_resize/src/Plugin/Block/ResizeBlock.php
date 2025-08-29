<?php

namespace Drupal\font_resize\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ResizeBlock' block.
 *
 * @Block(
 *   id = "resize_block",
 *   admin_label = @Translation("Resize block"),
 * )
 */
class ResizeBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'resize_block',
      '#labels' => [
        'minus' => $this->t('Reduce Font Size'),
        'default' => $this->t('Reset Font Size'),
        'plus' => $this->t('Increase Font Size'),
      ],
      '#attached' => [
        'library' => ['font_resize/font_resize'],
      ],
    ];
  }
}
