<?php

declare(strict_types=1);

namespace Loevgaard\DandomainStockBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class LoevgaardDandomainStockExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('loevgaard_dandomain_stock.dandomain_order_state_ids', $config['dandomain_order_state_ids']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }

    public function prepend(ContainerBuilder $container)
    {
        $ext = 'doctrine';

        if(!$container->hasExtension($ext)) {
            throw new \LogicException('You need to enable the doctrine bundle');
        }

        $container->prependExtensionConfig($ext, [
            'orm' => [
                'mappings' => [
                    'Loevgaard\\DandomainStock\\Entity' => [
                        'type' => 'annotation',
                        'dir' => '%kernel.project_dir%/vendor/loevgaard/dandomain-stock-entities/src/Entity',
                        'is_bundle' => false,
                        'prefix' => 'Loevgaard\\DandomainStock\\Entity',
                    ],
                ],
            ],
        ]);
    }
}
