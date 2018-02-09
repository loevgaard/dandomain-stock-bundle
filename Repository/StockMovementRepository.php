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
}
