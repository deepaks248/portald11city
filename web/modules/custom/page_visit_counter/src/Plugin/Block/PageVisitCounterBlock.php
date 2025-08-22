<?php

namespace Drupal\page_visit_counter\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides a Page Visit Counter block.
 *
 * @Block(
 *   id = "page_visit_counter_block",
 *   admin_label = @Translation("Page Visit Counter"),
 * )
 */
class PageVisitCounterBlock extends BlockBase implements ContainerFactoryPluginInterface
{

    protected StateInterface $state;

    public function __construct(array $configuration, $plugin_id, $plugin_definition, StateInterface $state)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->state = $state;
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('state')
        );
    }

    public function build(): array
    {
        $count = $this->state->get('page_visit_counter.count', 0);

        return [
            '#theme' => 'page_visit_counter_block',
            '#count' => (string) $count,
            '#cache' => ['max-age' => 0],
        ];
    }
}