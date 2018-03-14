<?php

declare(strict_types=1);

namespace Loevgaard\DandomainStockBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\UnitOfWork;
use Loevgaard\DandomainFoundation\Entity\Generated\OrderInterface;
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
            if(!$this->isValidOrderLine($entity)) {
                continue;
            }

            $stockMovement = $this->stockMovementFromOrderLine($entity);

            $this->persistStockMovement($stockMovement);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            /** @var OrderLineInterface $entity */
            if(!$this->isValidOrderLine($entity)) {
                continue;
            }

            $stockMovement = $this->stockMovementFromOrderLine($entity);
            $effectiveStockMovement = $entity->computeEffectiveStockMovement();
            if ($effectiveStockMovement) {
                $stockMovement = $effectiveStockMovement->diff($stockMovement);
                $stockMovement->setType(StockMovement::TYPE_REGULATION);
            }

            $this->persistStockMovement($stockMovement);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            /** @var OrderLineInterface $entity */
            if(!$this->isValidOrderLine($entity)) {
                continue;
            }

            $effectiveStockMovement = $entity->computeEffectiveStockMovement();

            // if the quantity is 0 we don't want to add a stock movement since this will just pollute the stock movement table
            if ($effectiveStockMovement && $effectiveStockMovement->getQuantity() !== 0) {
                $stockMovement = $effectiveStockMovement->inverse();
                $stockMovement
                    ->setType(StockMovement::TYPE_REGULATION) // we set the type as regulation since it is not a sale now
                    ->setOrderLineRemoved(true)
                    ->setOrderLine(null);

                $this->persistStockMovement($stockMovement);
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

    private function isValidOrderLine($entity) : bool
    {
        return $entity instanceof OrderLineInterface
            && $entity->getOrder()
            && $entity->getOrder()->getState()
            && in_array($entity->getOrder()->getState()->getExternalId(), $this->orderStateIds);
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
     * @throws ORMException
     */
    private function persistStockMovement(StockMovementInterface $stockMovement, EntityManager $entityManager, UnitOfWork $unitOfWork)
    {
        $stockMovement->validate();
        $entityManager->persist($stockMovement);
        $md = $this->metaData($entityManager, $stockMovement);
        $unitOfWork->computeChangeSet($md, $stockMovement);
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
