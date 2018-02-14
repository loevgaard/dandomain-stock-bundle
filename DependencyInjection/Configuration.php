<?php

declare(strict_types=1);

namespace Loevgaard\DandomainStockBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('loevgaard_dandomain_stock');

        $rootNode
            ->children()
                ->arrayNode('dandomain_order_state_ids')
                    ->info('The order state ids from Dandomain that should be considered as stock movements when an order is persisted')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
