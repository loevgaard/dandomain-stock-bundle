<?php

declare(strict_types=1);

namespace Loevgaard\DandomainStockBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMException;
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
     * @throws ORMException
     */
    public function preRemove(LifecycleEventArgs $args) : bool
    {
        /** @var OrderLineInterface $entity */
        $entity = $args->getObject();

        if (!($entity instanceof OrderLineInterface)) {
            return false;
        }

        /** @var EntityManager $em */
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $effectiveStockMovement = $entity->computeEffectiveStockMovement();

        // if the quantity is 0 we don't want to add a stock movement since this will just pollute the stock movement table
        if ($effectiveStockMovement && $effectiveStockMovement->getQuantity() !== 0) {
            $stockMovement = $effectiveStockMovement->inverse();
            $stockMovement
                ->setType(StockMovement::TYPE_REGULATION) // we set the type as regulation since it is not a sale now
                ->setOrderLineRemoved(true)
                ->setOrderLine(null);

            $em->persist($stockMovement);
            $uow->computeChangeSet($em->getClassMetadata(get_class($stockMovement)), $stockMovement);
        }

        foreach ($entity->getStockMovements() as $stockMovement) {
            $stockMovement
                ->setOrderLineRemoved(true)
                ->setOrderLine(null);

            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(get_class($stockMovement)), $stockMovement);
        }

        // the preRemove can be called from the onFlush event which has some limitations regarding changing entities
        // see http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/events.html#onflush
        // and http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/events.html#preremove
        //$uow->computeChangeSets();

        return true;
    }
}
