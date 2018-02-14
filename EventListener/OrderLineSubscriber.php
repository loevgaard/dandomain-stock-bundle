<?php

declare(strict_types=1);

namespace Loevgaard\DandomainStockBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMException;
use Loevgaard\DandomainFoundation\Entity\Generated\OrderLineInterface;
use Loevgaard\DandomainStock\Exception\CurrencyMismatchException;
use Loevgaard\DandomainStock\Exception\UnsetCurrencyException;
use Loevgaard\DandomainStock\Repository\StockMovementRepository;

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
     *
     * @return bool
     *
     * @throws CurrencyMismatchException
     * @throws ORMException
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
        if ($effectiveStockMovement) {
            $stockMovement = $effectiveStockMovement->inverse();
            $stockMovement
                ->setOrderLineRemoved(true)
                ->setOrderLine(null);

            $this->stockMovementRepository->persist($stockMovement);
        }

        foreach ($entity->getStockMovements() as $stockMovement) {
            $stockMovement
                ->setOrderLineRemoved(true)
                ->setOrderLine(null);
        }

        return true;
    }
}
