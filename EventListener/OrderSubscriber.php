<?php

namespace Loevgaard\DandomainStockBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Loevgaard\DandomainFoundation\Entity\Generated\OrderInterface;
use Loevgaard\DandomainStockBundle\Repository\StockMovementRepository;

class OrderSubscriber implements EventSubscriber
{
    /**
     * @var StockMovementRepository
     */
    private $stockMovementRepository;

    /**
     * @var array
     */
    private $orderStateIds;

    /**
     * @param StockMovementRepository $stockMovementFactory
     * @param array                   $orderStateIds        An array of external ids for order states (use the id in the Dandomain interface)
     */
    public function __construct(StockMovementRepository $stockMovementFactory, array $orderStateIds)
    {
        $this->stockMovementRepository = $stockMovementFactory;
        $this->orderStateIds = $orderStateIds;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @throws \Loevgaard\DandomainStockBundle\Exception\CurrencyMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\StockMovementProductMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UndefinedPriceForCurrencyException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UnsetCurrencyException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UnsetProductException
     */
    public function preUpdate(LifecycleEventArgs $args)
    {
        $this->update($args);
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @throws \Loevgaard\DandomainStockBundle\Exception\CurrencyMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\StockMovementProductMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UndefinedPriceForCurrencyException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UnsetCurrencyException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UnsetProductException
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $this->update($args);
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @throws \Loevgaard\DandomainStockBundle\Exception\CurrencyMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\StockMovementProductMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UndefinedPriceForCurrencyException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UnsetCurrencyException
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
                // if the quantity is 0 we don't want to add a stock movement since this will just pollute the stock movement table
                if (0 === $orderLine->getQuantity()) {
                    continue;
                }

                // if the order line does not have a valid product, we wont add it to the stock movements table
                // examples of products like this are discounts
                if (!$orderLine->getProduct()) {
                    continue;
                }

                $stockMovement = $this->stockMovementRepository->create();
                $stockMovement->populateFromOrderLine($orderLine);

                $effectiveStockMovement = $orderLine->computeEffectiveStockMovement();
                if ($effectiveStockMovement) {
                    $stockMovement = $effectiveStockMovement->diff($stockMovement);
                }

                $orderLine->addStockMovement($stockMovement);

                ++$i;
            }
        }
    }
}
