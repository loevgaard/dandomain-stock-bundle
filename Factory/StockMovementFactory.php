<?php

namespace Loevgaard\DandomainStockBundle\Factory;

use Loevgaard\DandomainStockBundle\Entity\StockMovement;

class StockMovementFactory
{
    private $class;

    public function __construct(string $class)
    {
        $this->class = $class;
    }

    public function create(): StockMovement
    {
        return new $this->class();
    }
}
