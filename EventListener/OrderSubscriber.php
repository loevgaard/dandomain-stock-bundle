<?php

declare(strict_types=1);

namespace Loevgaard\DandomainStockBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Loevgaard\DandomainFoundation\Entity\Generated\OrderInterface;
use Loevgaard\DandomainStock\Entity\StockMovement;
use Loevgaard\DandomainStock\Exception\CurrencyMismatchException;
use Loevgaard\DandomainStock\Exception\StockMovementProductMismatchException;
use Loevgaard\DandomainStock\Exception\UndefinedPriceForCurrencyException;
use Loevgaard\DandomainStock\Exception\UnsetCurrencyException;
use Loevgaard\DandomainStock\Exception\UnsetProductException;

class OrderSubscriber implements EventSubscriber
{
    /**
     * @var array
     */
    private $orderStateIds;

    /**
     * @param array $orderStateIds An array of external ids for order states (use the id in the Dandomain interface)
     */
    public function __construct(array $orderStateIds)
    {
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
     * @return bool
     *
     * @throws CurrencyMismatchException
     * @throws StockMovementProductMismatchException
     * @throws UndefinedPriceForCurrencyException
     * @throws UnsetCurrencyException
     * @throws UnsetProductException
     */
    public function preUpdate(LifecycleEventArgs $args)
    {
        return $this->update($args);
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @return bool
     *
     * @throws CurrencyMismatchException
     * @throws StockMovementProductMismatchException
     * @throws UndefinedPriceForCurrencyException
     * @throws UnsetCurrencyException
     * @throws UnsetProductException
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        return $this->update($args);
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @return bool
     *
     * @throws CurrencyMismatchException
     * @throws StockMovementProductMismatchException
     * @throws UndefinedPriceForCurrencyException
     * @throws UnsetCurrencyException
     * @throws UnsetProductException
     */
    private function update(LifecycleEventArgs $args)
    {
        /** @var OrderInterface $entity */
        $entity = $args->getObject();

        if (!($entity instanceof OrderInterface)) {
            return false;
        }

        // only log a stock movement when the order state is in the specified order states, typically 'completed'
        if (!in_array($entity->getState()->getExternalId(), $this->orderStateIds)) {
            return false;
        }

        $i = 0;
        foreach ($entity->getOrderLines() as $orderLine) {
            // if the order line does not have a valid product, we wont add it to the stock movements table
            // examples of products like this are discounts
            if (!$orderLine->getProduct()) {
                continue;
            }

            $stockMovement = new StockMovement();
            $stockMovement->populateFromOrderLine($orderLine);

            $effectiveStockMovement = $orderLine->computeEffectiveStockMovement();
            if ($effectiveStockMovement) {
                $stockMovement = $effectiveStockMovement->diff($stockMovement);
            }

            // if the quantity is 0 we don't want to add a stock movement since this will just pollute the stock movement table
            if($stockMovement->getQuantity() === 0) {
                continue;
            }

            $orderLine->addStockMovement($stockMovement);

            ++$i;
        }

        return true;
    }
}
