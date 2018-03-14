<?php

declare(strict_types=1);

namespace Loevgaard\DandomainStockBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\UnitOfWork;
use Loevgaard\DandomainFoundation\Entity\Generated\OrderLineInterface;
use Loevgaard\DandomainStock\Entity\Generated\StockMovementInterface;
use Loevgaard\DandomainStock\Entity\StockMovement;
use Loevgaard\DandomainStock\Exception\CurrencyMismatchException;
use Loevgaard\DandomainStock\Exception\StockMovementProductMismatchException;
use Loevgaard\DandomainStock\Exception\UndefinedPriceForCurrencyException;
use Loevgaard\DandomainStock\Exception\UnsetCurrencyException;
use Loevgaard\DandomainStock\Exception\UnsetProductException;

class StockMovementSubscriber implements EventSubscriber
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
            Events::onFlush,
        ];
    }

    /**
     * @param OnFlushEventArgs $args
     * @throws CurrencyMismatchException
     * @throws ORMException
     * @throws StockMovementProductMismatchException
     * @throws UndefinedPriceForCurrencyException
     * @throws UnsetCurrencyException
     * @throws UnsetProductException
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            /** @var OrderLineInterface $entity */
            if(!$this->isOrderLine($entity) || !$this->isValidState($entity)) {
                continue;
            }

            $stockMovement = $this->stockMovementFromOrderLine($entity);

            $this->persistStockMovement($stockMovement, $em, $uow);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            /** @var OrderLineInterface $entity */
            if(!$this->isOrderLine($entity) || !$this->isValidState($entity)) {
                continue;
            }

            $stockMovement = $this->stockMovementFromOrderLine($entity);
            $effectiveStockMovement = $entity->computeEffectiveStockMovement();
            if ($effectiveStockMovement) {
                $stockMovement = $effectiveStockMovement->diff($stockMovement);
                $stockMovement->setType(StockMovement::TYPE_REGULATION);
            }

            $this->persistStockMovement($stockMovement, $em, $uow);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            /** @var OrderLineInterface $entity */
            if(!$this->isOrderLine($entity)) {
                continue;
            }

            $effectiveStockMovement = $entity->computeEffectiveStockMovement();

            if ($effectiveStockMovement) {
                $stockMovement = $effectiveStockMovement->inverse();
                $stockMovement
                    ->setType(StockMovement::TYPE_REGULATION) // we set the type as regulation since it is not a sale now
                    ->setOrderLineRemoved(true)
                    ->setOrderLine(null);

                $this->persistStockMovement($stockMovement, $em, $uow);
            }

            foreach ($entity->getStockMovements() as $stockMovement) {
                $stockMovement
                    ->setOrderLineRemoved(true)
                    ->setOrderLine(null);

                $md = $this->metaData($em, $stockMovement);
                $uow->recomputeSingleEntityChangeSet($md, $stockMovement);
            }
        }
    }

    private function isOrderLine($entity) : bool
    {
        return $entity instanceof OrderLineInterface;
    }

    private function isValidState(OrderLineInterface $orderLine) : bool
    {
        return $orderLine->getOrder()
            && $orderLine->getOrder()->getState()
            && in_array($orderLine->getOrder()->getState()->getExternalId(), $this->orderStateIds);
    }

    /**
     * @param OrderLineInterface $orderLine
     * @return StockMovement
     * @throws CurrencyMismatchException
     * @throws UndefinedPriceForCurrencyException
     * @throws UnsetProductException
     */
    private function stockMovementFromOrderLine(OrderLineInterface $orderLine) : StockMovement
    {
        $stockMovement = new StockMovement();
        $stockMovement->populateFromOrderLine($orderLine);

        return $stockMovement;
    }

    private function metaData(EntityManager $entityManager, $entity) : ClassMetadata
    {
        return $entityManager->getClassMetadata(get_class($entity));
    }

    /**
     * @param StockMovementInterface $stockMovement
     * @param EntityManager $entityManager
     * @param UnitOfWork $unitOfWork
     * @return boolean
     * @throws ORMException
     */
    private function persistStockMovement(StockMovementInterface $stockMovement, EntityManager $entityManager, UnitOfWork $unitOfWork) : bool
    {
        // if the quantity is 0 we don't want to add a stock movement since this will just pollute the stock movement table
        if($stockMovement->getQuantity() === 0) {
            return false;
        }

        $stockMovement->validate();
        $entityManager->persist($stockMovement);
        $md = $this->metaData($entityManager, $stockMovement);
        $unitOfWork->computeChangeSet($md, $stockMovement);

        return true;
    }
}
