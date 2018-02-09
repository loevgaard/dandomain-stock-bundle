<?php

namespace Loevgaard\DandomainStockBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Loevgaard\DandomainStockBundle\Entity\StockMovement;

class StockMovementRepository extends ServiceEntityRepository
{
    public function create(): StockMovement
    {
        return new $this->_entityName();
    }

    /**
     * @param StockMovement $stockMovement
     * @throws \Doctrine\ORM\ORMException
     */
    public function persist(StockMovement $stockMovement) : void
    {
        $this->getEntityManager()->persist($stockMovement);
    }

    /**
     * @param StockMovement|null $stockMovement
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function flush(?StockMovement $stockMovement = null) : void
    {
        $this->getEntityManager()->flush($stockMovement);
    }
}
