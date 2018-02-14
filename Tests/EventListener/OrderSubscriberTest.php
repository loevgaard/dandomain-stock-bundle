<?php

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
use Loevgaard\DandomainStockBundle\EventListener\OrderSubscriber;
use PHPUnit\Framework\TestCase;

final class OrderSubscriberTest extends TestCase
{
    public function testEventsAndCorrespondingMethods()
    {
        $subscriber = new OrderSubscriber([]);

        $events = [
            Events::prePersist,
            Events::preUpdate,
        ];

        $this->assertEquals($events, $subscriber->getSubscribedEvents());

        $refl = new \ReflectionClass(OrderSubscriber::class);

        foreach ($events as $event) {
            $this->assertTrue($refl->hasMethod($event));
        }
    }

    /**
     * @throws CurrencyMismatchException
     * @throws StockMovementProductMismatchException
     * @throws UndefinedPriceForCurrencyException
     * @throws UnsetCurrencyException
     * @throws UnsetProductException
     */
    public function testInstanceType()
    {
        $lifecycleEventArgs = $this->getLifecycleEventArgs(new \stdClass());

        $subscriber = new OrderSubscriber([]);
        $res = $subscriber->preUpdate($lifecycleEventArgs);

        $this->assertFalse($res);
    }

    /**
     * @throws CurrencyMismatchException
     * @throws StockMovementProductMismatchException
     * @throws UndefinedPriceForCurrencyException
     * @throws UnsetCurrencyException
     * @throws UnsetProductException
     */
    public function testOrderStateCondition()
    {
        $order = new Order();

        $state = new State();
        $state->setExternalId(1);
        $order->setState($state);

        $lifecycleEventArgs = $this->getLifecycleEventArgs($order);

        $subscriber = new OrderSubscriber([3]);
        $res = $subscriber->preUpdate($lifecycleEventArgs);

        $this->assertFalse($res);
    }

    /**
     * @throws CurrencyMismatchException
     * @throws StockMovementProductMismatchException
     * @throws UndefinedPriceForCurrencyException
     * @throws UnsetCurrencyException
     * @throws UnsetProductException
     */
    public function testQuantityEqualsZero()
    {
        $order = $this->getOrder(1, 0);

        $lifecycleEventArgs = $this->getLifecycleEventArgs($order);

        $subscriber = new OrderSubscriber([1]);
        $res = $subscriber->preUpdate($lifecycleEventArgs);

        $this->assertTrue($res);
        foreach ($order->getOrderLines() as $orderLine) {
            $this->assertCount(0, $orderLine->getStockMovements());
        }
    }

    /**
     * @throws CurrencyMismatchException
     * @throws StockMovementProductMismatchException
     * @throws UndefinedPriceForCurrencyException
     * @throws UnsetCurrencyException
     * @throws UnsetProductException
     */
    public function testNoProduct()
    {
        $orderStateId = 1;
        $order = $this->getOrder($orderStateId);

        $lifecycleEventArgs = $this->getLifecycleEventArgs($order);

        $subscriber = new OrderSubscriber([$orderStateId]);
        $res = $subscriber->preUpdate($lifecycleEventArgs);

        $this->assertTrue($res);
        foreach ($order->getOrderLines() as $orderLine) {
            $this->assertCount(0, $orderLine->getStockMovements());
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
