<?php

namespace Loevgaard\DandomainStockBundle\Tests\Entity;

use Loevgaard\DandomainFoundation\Entity\OrderLine;
use Loevgaard\DandomainFoundation\Entity\Product;
use Loevgaard\DandomainStockBundle\Entity\StockMovement;
use Loevgaard\DandomainStockBundle\Exception\CurrencyMismatchException;
use Loevgaard\DandomainStockBundle\Exception\UnsetCurrencyException;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\TestCase;

class StockMovementTest extends TestCase
{
    /**
     * @throws \Loevgaard\DandomainStockBundle\Exception\CurrencyMismatchException
     * @throws \Loevgaard\DandomainStockBundle\Exception\UnsetCurrencyException
     */
    public function testGettersSetters()
    {
        $stockMovement = $this->getMockForAbstractClass(StockMovement::class);

        $product = new Product();
        $orderLine = new OrderLine();

        $qty = -3;
        $retailPrice = new Money(10396, new Currency('DKK'));
        $totalRetailPrice = new Money(31188, new Currency('DKK'));
        $price = new Money(7999, new Currency('DKK'));
        $totalPrice = new Money(23997, new Currency('DKK'));
        $discount = new Money(2397, new Currency('DKK'));
        $totalDiscount = new Money(7191, new Currency('DKK'));

        // with vat
        $retailPriceInclVat = new Money(12995, new Currency('DKK'));
        $totalRetailPriceInclVat = new Money(38985, new Currency('DKK'));
        $priceInclVat = new Money(9999, new Currency('DKK'));
        $totalPriceInclVat = new Money(29996, new Currency('DKK'));
        $discountInclVat = new Money(2996, new Currency('DKK'));
        $totalDiscountInclVat = new Money(8989, new Currency('DKK'));

        $stockMovement
            ->setId(1)
            ->setQuantity($qty)
            ->setRetailPrice($retailPrice)
            ->setPrice($price)
            ->setType(StockMovement::TYPE_SALE)
            ->setReference('order')
            ->setComplaint(false)
            ->setVatPercentage(25.0)
            ->setOrderLine($orderLine)
            ->setProduct($product)
        ;

        $stockMovement->validate();

        $this->assertSame(1, $stockMovement->getId());
        $this->assertSame($qty, $stockMovement->getQuantity());
        $this->assertSame('DKK', $stockMovement->getCurrency());
        $this->assertSame(StockMovement::TYPE_SALE, $stockMovement->getType());
        $this->assertSame('order', $stockMovement->getReference());
        $this->assertFalse($stockMovement->isComplaint());
        $this->assertSame(25.0, $stockMovement->getVatPercentage());
        $this->assertSame($product, $stockMovement->getProduct());
        $this->assertSame($orderLine, $stockMovement->getOrderLine());

        // test prices excl vat
        $this->assertEquals($retailPrice, $stockMovement->getRetailPrice());
        $this->assertEquals($totalRetailPrice, $stockMovement->getTotalRetailPrice());
        $this->assertEquals($price, $stockMovement->getPrice());
        $this->assertEquals($totalPrice, $stockMovement->getTotalPrice());
        $this->assertEquals($discount, $stockMovement->getDiscount());
        $this->assertEquals($totalDiscount, $stockMovement->getTotalDiscount());

        // test prices incl vat
        $this->assertEquals($retailPriceInclVat, $stockMovement->getRetailPriceInclVat());
        $this->assertEquals($totalRetailPriceInclVat, $stockMovement->getTotalRetailPriceInclVat());
        $this->assertEquals($priceInclVat, $stockMovement->getPriceInclVat());
        $this->assertEquals($totalPriceInclVat, $stockMovement->getTotalPriceInclVat());
        $this->assertEquals($discountInclVat, $stockMovement->getDiscountInclVat());
        $this->assertEquals($totalDiscountInclVat, $stockMovement->getTotalDiscountInclVat());
    }

    public function testMagicCall1()
    {
        $this->expectException(\InvalidArgumentException::class);
        $stockMovement = $this->getMockForAbstractClass(StockMovement::class);
        $stockMovement->getTest();
    }

    public function testMagicCall2()
    {
        $this->expectException(\InvalidArgumentException::class);
        $stockMovement = $this->getMockForAbstractClass(StockMovement::class);
        $stockMovement->getTestInclVat();
    }

    /**
     * @throws CurrencyMismatchException
     */
    public function testUpdateCurrency()
    {
        $this->expectException(CurrencyMismatchException::class);
        $stockMovement = $this->getMockForAbstractClass(StockMovement::class);
        $stockMovement->setPrice(new Money(100, new Currency('DKK')));
        $stockMovement->setRetailPrice(new Money(100, new Currency('EUR')));
    }

    /**
     * @throws UnsetCurrencyException
     */
    public function testMoney()
    {
        $this->expectException(UnsetCurrencyException::class);
        $stockMovement = $this->getMockForAbstractClass(StockMovement::class);
        $stockMovement->getPrice();
    }

    /**
     * @throws CurrencyMismatchException
     * @throws UnsetCurrencyException
     * @throws \Loevgaard\DandomainStockBundle\Exception\StockMovementProductMismatchException
     */
    public function testDiff()
    {
        $diffTests = [
            ['original' => 1, 'new' => 1, 'expected' => 0],
            ['original' => 0, 'new' => 1, 'expected' => 1],
            ['original' => 0, 'new' => 2, 'expected' => 2],
            ['original' => -1, 'new' => 1, 'expected' => 2],

            ['original' => 1, 'new' => -1, 'expected' => -2],
            ['original' => 0, 'new' => -1, 'expected' => -1],
            ['original' => 0, 'new' => -2, 'expected' => -2],
            ['original' => -1, 'new' => -1, 'expected' => 0],

            ['original' => 0, 'new' => 0, 'expected' => 0],

            ['original' => 2, 'new' => 3, 'expected' => 1],
            ['original' => 3, 'new' => 2, 'expected' => -1],

            ['original' => -2, 'new' => -3, 'expected' => -1],
            ['original' => -3, 'new' => -2, 'expected' => 1],

            ['original' => 3, 'new' => 0, 'expected' => -3],
            ['original' => -3, 'new' => 0, 'expected' => 3],
        ];

        foreach ($diffTests as $diffTest) {
            $originalStockMovement = $this->getMockForAbstractClass(StockMovement::class);
            $stockMovement = $this->getMockForAbstractClass(StockMovement::class);

            $product = new Product();
            $product->setId(1);

            $price = new Money(100, new Currency('DKK'));

            $originalStockMovement->setQuantity($diffTest['original'])->setPrice($price)->setProduct($product);
            $stockMovement->setQuantity($diffTest['new'])->setPrice($price)->setProduct($product);

            $diff = $originalStockMovement->diff($stockMovement);

            $this->assertSame($diffTest['expected'], $diff->getQuantity());
        }
    }
}
