<?php

declare(strict_types=1);

namespace Loevgaard\DandomainStockBundle\Tests\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMException;
use Loevgaard\DandomainFoundation\Entity\OrderLine;
use Loevgaard\DandomainStock\Exception\CurrencyMismatchException;
use Loevgaard\DandomainStock\Exception\UnsetCurrencyException;
use Loevgaard\DandomainStock\Repository\StockMovementRepository;
use Loevgaard\DandomainStockBundle\EventListener\OrderLineSubscriber;
use PHPUnit\Framework\TestCase;

final class OrderLineSubscriberTest extends TestCase
{
    public function testEventsAndCorrespondingMethods()
    {
        $stockMovementRepository = $this->getStockMovementRepository();
        $subscriber = new OrderLineSubscriber($stockMovementRepository);

        $events = [
            Events::preRemove,
        ];

        $this->assertEquals($events, $subscriber->getSubscribedEvents());

        $refl = new \ReflectionClass(OrderLineSubscriber::class);

        foreach ($events as $event) {
            $this->assertTrue($refl->hasMethod($event));
        }
    }

    /**
     * @throws CurrencyMismatchException
     * @throws UnsetCurrencyException
     * @throws ORMException
     */
    public function testWrongInstanceType()
    {
        $lifecycleEventArgs = $this->getLifecycleEventArgs(new \stdClass());
        $stockMovementRepository = $this->getStockMovementRepository();

        $subscriber = new OrderLineSubscriber($stockMovementRepository);
        $res = $subscriber->preRemove($lifecycleEventArgs);

        $this->assertFalse($res);
    }

    /**
     * @throws CurrencyMismatchException
     * @throws UnsetCurrencyException
     * @throws ORMException
     */
    public function testCorrectInstanceType()
    {
        $lifecycleEventArgs = $this->getLifecycleEventArgs(new OrderLine());
        $stockMovementRepository = $this->getStockMovementRepository();

        $subscriber = new OrderLineSubscriber($stockMovementRepository);
        $res = $subscriber->preRemove($lifecycleEventArgs);

        $this->assertTrue($res);
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

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|StockMovementRepository
     */
    private function getStockMovementRepository()
    {
        $repository = $this->getMockBuilder(StockMovementRepository::class)->disableOriginalConstructor()->getMock();

        return $repository;
    }
}
