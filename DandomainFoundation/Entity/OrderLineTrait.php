<?php

namespace Loevgaard\DandomainStockBundle\DandomainFoundation\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Loevgaard\DandomainStockBundle\Entity\StockMovement;
use Loevgaard\DandomainStockBundle\Exception\StockMovementProductMismatchException;

trait OrderLineTrait
{
    /**
     * @var StockMovement[]|ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="Loevgaard\DandomainStockBundle\Entity\StockMovement", mappedBy="orderLine", cascade={"persist"})
     */
    protected $stockMovements;

    /**
     * @param StockMovement $stockMovement
     *
     * @return OrderLineTrait
     *
     * @throws \Loevgaard\DandomainStockBundle\Exception\StockMovementProductMismatchException
     */
    public function addStockMovement(StockMovement $stockMovement)
    {
        $this->initStockMovements();

        if ($this->stockMovements->count()) {
            /** @var StockMovement $firstStockMovement */
            $firstStockMovement = $this->stockMovements->first();
            if ($stockMovement->getProduct()->getId() !== $firstStockMovement->getProduct()->getId()) {
                throw new StockMovementProductMismatchException('The product id of the first product is `'.$firstStockMovement->getProduct()->getId().'` while the one you are adding has this id: `'.$stockMovement->getProduct()->getId().'`');
            }
        }

        if (!$this->stockMovements->contains($stockMovement)) {
            $this->stockMovements->add($stockMovement);
        }

        return $this;
    }

    /**
     * @return \Loevgaard\DandomainStockBundle\Entity\StockMovement[]
     */
    public function getStockMovements()
    {
        $this->initStockMovements();

        return $this->stockMovements;
    }

    /**
     * @param StockMovement $stockMovements
     *
     * @return OrderLineTrait
     *
     * @throws \Loevgaard\DandomainStockBundle\Exception\StockMovementProductMismatchException
     */
    public function setStockMovements(StockMovement $stockMovements)
    {
        foreach ($stockMovements as $stockMovement) {
            $this->addStockMovement($stockMovement);
        }

        return $this;
    }

    /**
     * Say you have these two stock movements associated with this order line:.
     *
     * | qty | product |
     * -----------------
     * | 1   | Jeans   |
     * | -1  | Jeans   |
     *
     * Then the effective stock movement would be
     *
     * | qty | product |
     * -----------------
     * | 0   | Jeans   |
     *
     * And this is what we return in this method
     *
     * Returns null if the order line has 0 stock movements
     *
     * @return StockMovement|null
     *
     * @throws \Loevgaard\DandomainStockBundle\Exception\CurrencyMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UnsetCurrencyException
     */
    public function computeEffectiveStockMovement(): ?StockMovement
    {
        $this->initStockMovements();

        if (!$this->stockMovements->count()) {
            return null;
        }

        /** @var StockMovement $lastStockMovement */
        $lastStockMovement = $this->stockMovements->last();

        $qty = 0;
        $stockMovement = $lastStockMovement->copy();

        foreach ($this->stockMovements as $stockMovement) {
            $qty += $stockMovement->getQuantity();
        }

        $stockMovement->setQuantity($qty);

        return $stockMovement;
    }

    protected function initStockMovements(): void
    {
        if (!is_null($this->stockMovements)) {
            return;
        }

        $this->stockMovements = new ArrayCollection();
    }
}
