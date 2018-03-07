<?php

declare(strict_types=1);

namespace Loevgaard\DandomainStockBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Loevgaard\DandomainFoundation\Entity\Generated\OrderLineInterface;
use Loevgaard\DandomainStock\Entity\StockMovement;
use Loevgaard\DandomainStock\Exception\CurrencyMismatchException;
use Loevgaard\DandomainStock\Exception\UnsetCurrencyException;

class OrderLineSubscriber implements EventSubscriber
{
    public function getSubscribedEvents()
    {
        return [
            Events::preRemove,
        ];
    }

    /**
     * @param LifecycleEventArgs $args
     *
     * @return bool
     *
     * @throws CurrencyMismatchException
     * @throws UnsetCurrencyException
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        /** @var OrderLineInterface $entity */
        $entity = $args->getObject();

        if (!($entity instanceof OrderLineInterface)) {
            return false;
        }

        $effectiveStockMovement = $entity->computeEffectiveStockMovement();

        // if the quantity is 0 we don't want to add a stock movement since this will just pollute the stock movement table
        if ($effectiveStockMovement && $effectiveStockMovement->getQuantity() !== 0) {
            $stockMovement = $effectiveStockMovement->inverse();
            $stockMovement
                ->setType(StockMovement::TYPE_REGULATION) // we set the type as regulation since it is not a sale now
                ->setOrderLineRemoved(true)
                ->setOrderLine(null);

            $args->getObjectManager()->persist($stockMovement);
        }

        foreach ($entity->getStockMovements() as $stockMovement) {
            $stockMovement
                ->setOrderLineRemoved(true)
                ->setOrderLine(null);
        }

        return true;
    }
}
