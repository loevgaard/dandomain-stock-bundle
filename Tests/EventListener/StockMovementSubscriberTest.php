<?php

declare(strict_types=1);

namespace Loevgaard\DandomainStockBundle\Tests\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Loevgaard\DandomainFoundation\Entity\Generated\OrderInterface;
use Loevgaard\DandomainFoundation\Entity\Generated\ProductInterface;
use Loevgaard\DandomainFoundation\Entity\Order;
use Loevgaard\DandomainFoundation\Entity\OrderLine;
use Loevgaard\DandomainFoundation\Entity\State;
use Loevgaard\DandomainStock\Exception\CurrencyMismatchException;
use Loevgaard\DandomainStock\Exception\StockMovementProductMismatchException;
use Loevgaard\DandomainStock\Exception\UndefinedPriceForCurrencyException;
use Loevgaard\DandomainStock\Exception\UnsetCurrencyException;
use Loevgaard\DandomainStock\Exception\UnsetProductException;
use Loevgaard\DandomainStockBundle\EventListener\StockMovementSubscriber;
use PHPUnit\Framework\TestCase;

final class StockMovementSubscriberTest extends TestCase
{
    public function testEventsAndCorrespondingMethods()
    {
        $subscriber = new StockMovementSubscriber([]);

        $events = [
            Events::onFlush,
        ];

        $this->assertEquals($events, $subscriber->getSubscribedEvents());

        $refl = new \ReflectionClass(StockMovementSubscriber::class);

        foreach ($events as $event) {
            $this->assertTrue($refl->hasMethod($event));
        }
    }

    /**
     * @param object $object
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|LifecycleEventArgs
     */
    private function getLifecycleEventArgs($object)
    {
        $lifecycleEventArgs = $this->getMockBuilder(LifecycleEventArgs::class)->disableOriginalConstructor()->getMock();
        $lifecycleEventArgs->method('getObject')->willReturn($object);

        return $lifecycleEventArgs;
    }

    private function getOrder(int $orderStateId = 1, $orderLineQuantity = 1, ProductInterface $product = null): OrderInterface
    {
        $order = new Order();
        $order->setCreatedDate(new \DateTimeImmutable());

        // set state
        $state = new State();
        $state->setExternalId($orderStateId);
        $order->setState($state);

        // add order line
        $orderLine = new OrderLine();
        $orderLine->setQuantity($orderLineQuantity);
        if ($product) {
            $orderLine->setProduct($product);
        }
        $order->addOrderLine($orderLine);

        return $order;
    }
}
