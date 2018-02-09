<?php

namespace Loevgaard\DandomainStockBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Loevgaard\DandomainFoundation\Entity\Generated\OrderLineInterface;
use Loevgaard\DandomainStockBundle\Repository\StockMovementRepository;

class OrderLineSubscriber implements EventSubscriber
{
    /**
     * @var StockMovementRepository
     */
    private $stockMovementRepository;

    /**
     * @param StockMovementRepository $stockMovementRepository
     */
    public function __construct(StockMovementRepository $stockMovementRepository)
    {
        $this->stockMovementRepository = $stockMovementRepository;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::preRemove,
        ];
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \Loevgaard\DandomainStockBundle\Exception\CurrencyMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UnsetCurrencyException
     */
    public function preRemove(LifecycleEventArgs $args)
    {
        /** @var OrderLineInterface $entity */
        $entity = $args->getObject();

        if ($entity instanceof OrderLineInterface) {
            $effectiveStockMovement = $entity->computeEffectiveStockMovement();
            if ($effectiveStockMovement) {
                $stockMovement = $effectiveStockMovement->inverse();
                $stockMovement
                    ->setOrderLineRemoved(true)
                    ->setOrderLine(null);

                $args->getObjectManager()->persist($stockMovement);
            }

            foreach ($entity->getStockMovements() as $stockMovement) {
                $stockMovement
                    ->setOrderLineRemoved(true)
                    ->setOrderLine(null);
            }
        }
    }
}
