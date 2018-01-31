<?php

namespace Loevgaard\DandomainStockBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Loevgaard\DandomainFoundation\Entity\Generated\OrderInterface;
use Loevgaard\DandomainFoundation\Entity\Generated\OrderLineInterface;
use Loevgaard\DandomainStockBundle\Factory\StockMovementFactory;

class OrderSubscriber implements EventSubscriber
{
    private $stockMovementFactory;

    /**
     * @var array
     */
    private $orderStateIds;

    /**
     * @param StockMovementFactory $stockMovementFactory
     * @param array                $orderStateIds        An array of external ids for order states (use the id in the Dandomain interface)
     */
    public function __construct(StockMovementFactory $stockMovementFactory, array $orderStateIds)
    {
        $this->stockMovementFactory = $stockMovementFactory;
        $this->orderStateIds = $orderStateIds;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
        ];
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Loevgaard\DandomainStockBundle\Exception\CurrencyMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UndefinedPriceForCurrencyException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UnsetProductException
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->update($args);
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Loevgaard\DandomainStockBundle\Exception\CurrencyMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UndefinedPriceForCurrencyException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UnsetProductException
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $this->update($args);
    }

    // @todo to make this work we need to extend the hostnet entities from dandomain foundation entities
    public function postRemove(LifecycleEventArgs $args)
    {
        /** @var OrderLineInterface $entity */
        $entity = $args->getObject();

        if ($entity instanceof OrderLineInterface) {
            $stockMovement = $entity->getStockMovement();

            if ($stockMovement) {
                $objectManager = $args->getObjectManager();
                $objectManager->remove($stockMovement);
                $objectManager->flush();
            }
        }
    }

    // @todo we should not allow editing an existing stock movement

    /**
     * @param LifecycleEventArgs $args
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Loevgaard\DandomainStockBundle\Exception\CurrencyMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UndefinedPriceForCurrencyException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UnsetProductException
     */
    private function update(LifecycleEventArgs $args)
    {
        /** @var OrderInterface $entity */
        $entity = $args->getObject();

        if ($entity instanceof OrderInterface) {
            // only log a stock movement when the order state is in the specified order states, typically 'completed'
            if (!in_array($entity->getState()->getExternalId(), $this->orderStateIds)) {
                return;
            }

            $i = 0;
            foreach ($entity->getOrderLines() as $orderLine) {
                // if the quantity is 0 we don't want to add a stock movement since this will just pollude the stock movement table
                if (0 === $orderLine->getQuantity()) {
                    continue;
                }

                // if the order line does not have a valid product, we wont add it to the stock movements table
                // examples of products like this are discounts
                if (!$orderLine->getProduct()) {
                    continue;
                }

                /** @var EntityManager $objectManager */
                $objectManager = $args->getObjectManager();

                $stockMovement = $orderLine->getStockMovement();
                if (!$stockMovement) {
                    $stockMovement = $this->stockMovementFactory->create();
                }
                $stockMovement->populateFromOrderLine($orderLine);

                $orderLine->setStockMovement($stockMovement);

                $objectManager->persist($stockMovement);
                $objectManager->flush($stockMovement);

                ++$i;
            }
        }
    }
}
