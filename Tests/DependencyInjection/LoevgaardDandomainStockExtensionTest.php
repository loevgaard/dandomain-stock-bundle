<?php

declare(strict_types=1);

namespace Loevgaard\DandomainStockBundle\Tests\DependencyInjection;

use Loevgaard\DandomainStockBundle\DependencyInjection\LoevgaardDandomainStockExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Parser;

class LoevgaardDandomainStockExtensionTest extends TestCase
{
    public function testThrowsExceptionUnlessAltapayUsernameSet()
    {
        $this->expectException(InvalidConfigurationException::class);

        $loader = new LoevgaardDandomainStockExtension();
        $config = $this->getEmptyConfig();
        unset($config['dandomain_order_state_ids']);
        $loader->load([$config], new ContainerBuilder());
    }

    public function testGettersSetters()
    {
        $loader = new LoevgaardDandomainStockExtension();
        $config = $this->getEmptyConfig();
        $container = new ContainerBuilder();
        $loader->load([$config], $container);

        $this->assertSame($config['dandomain_order_state_ids'], $container->getParameter('loevgaard_dandomain_stock.dandomain_order_state_ids'));
    }

    /**
     * @return array
     */
    protected function getEmptyConfig()
    {
        $yaml = <<<'EOF'
dandomain_order_state_ids: [3]
EOF;
        $parser = new Parser();

        return $parser->parse($yaml);
    }
}
