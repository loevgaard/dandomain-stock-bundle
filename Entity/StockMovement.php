<?php

namespace Loevgaard\DandomainStockBundle\Entity;

use Assert\Assert;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Model\Blameable\Blameable;
use Knp\DoctrineBehaviors\Model\Timestampable\Timestampable;
use Loevgaard\DandomainFoundation\Entity\Generated\OrderLineInterface;
use Loevgaard\DandomainFoundation\Entity\Generated\ProductInterface;
use Loevgaard\DandomainStockBundle\Exception\CurrencyMismatchException;
use Loevgaard\DandomainStockBundle\Exception\StockMovementProductMismatchException;
use Loevgaard\DandomainStockBundle\Exception\UndefinedPriceForCurrencyException;
use Loevgaard\DandomainStockBundle\Exception\UnsetCurrencyException;
use Loevgaard\DandomainStockBundle\Exception\UnsetProductException;
use Money\Currency;
use Money\Money;
use Symfony\Component\Validator\Constraints as FormAssert;

/**
 * @method Money getRetailPriceInclVat()
 * @method Money getTotalRetailPriceInclVat()
 * @method Money getPriceInclVat()
 * @method Money getTotalPriceInclVat()
 * @method Money getDiscountInclVat()
 * @method Money getTotalDiscountInclVat()
 *
 * @ORM\MappedSuperclass()
 **/
abstract class StockMovement
{
    use Blameable;
    use Timestampable;

    const TYPE_SALE = 'sale';
    const TYPE_RETURN = 'return';
    const TYPE_REGULATION = 'regulation';
    const TYPE_DELIVERY = 'delivery';

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     * @ORM\Id
     */
    protected $id;

    /**
     * The number of items.
     *
     * If the quantity is negative it means an outgoing stock movement, i.e. you've sold a product
     * Contrary a positive number means an ingoing stock movement, i.e. you had a return or a delivery
     *
     * @var int
     *
     * @FormAssert\NotBlank()
     * @FormAssert\NotEqualTo(0)
     *
     * @ORM\Column(type="integer")
     */
    protected $quantity;

    /**
     * Whether this stock movement is a complaint.
     *
     * @var bool
     *
     * @ORM\Column(name="complaint", type="boolean")
     */
    protected $complaint;

    /**
     * A small text describing this stock movement.
     *
     * @var string
     *
     * @FormAssert\Length(max="191")
     *
     * @ORM\Column(name="reference", type="string", length=191)
     */
    protected $reference;

    /**
     * A valid currency code.
     *
     * @var string
     *
     * @FormAssert\Currency()
     *
     * @ORM\Column(type="string", length=3)
     */
    protected $currency;

    /**
     * This is the retail price of the product on the time when the stock movement was created
     * The price is excl vat.
     *
     * @var int
     *
     * @FormAssert\NotBlank()
     * @FormAssert\GreaterThanOrEqual(0)
     *
     * @ORM\Column(type="integer")
     */
    protected $retailPrice;

    /**
     * Effectively this is `$quantity * $retailPrice`.
     *
     * The price is excl vat
     *
     * @var int
     *
     * @FormAssert\NotBlank()
     * @FormAssert\GreaterThanOrEqual(0)
     *
     * @ORM\Column(type="integer")
     */
    protected $totalRetailPrice;

    /**
     * This is the price excl vat.
     *
     * @var int
     *
     * @FormAssert\NotBlank()
     * @FormAssert\GreaterThanOrEqual(0)
     *
     * @ORM\Column(type="integer")
     */
    protected $price;

    /**
     * Effectively this is `$quantity * $price`.
     *
     * This is the total price excl vat
     *
     * @var int
     *
     * @FormAssert\NotBlank()
     * @FormAssert\GreaterThanOrEqual(0)
     *
     * @ORM\Column(type="integer")
     */
    protected $totalPrice;

    /**
     * This is the discount on this stock movement.
     *
     * Effectively this is `$retailPrice - $price`
     *
     * @var int
     *
     * @FormAssert\NotBlank()
     *
     * @ORM\Column(type="integer")
     */
    protected $discount;

    /**
     * This is the total discount on this stock movement.
     *
     * Effectively this is `$totalRetailPrice - $totalPrice`
     *
     * @var int
     *
     * @FormAssert\NotBlank()
     *
     * @ORM\Column(type="integer")
     */
    protected $totalDiscount;

    /**
     * This is the vat percentage.
     *
     * @var float
     *
     * @FormAssert\NotBlank()
     * @FormAssert\GreaterThanOrEqual(0)
     *
     * @ORM\Column(type="decimal", precision=5, scale=2)
     */
    protected $vatPercentage;

    /**
     * This is the type of the stock movement, i.e. 'sale', 'delivery', 'return' etc.
     *
     * @var string
     *
     * @FormAssert\Choice(callback="getTypes")
     *
     * @ORM\Column(type="string", length=191)
     */
    protected $type;

    /**
     * This is the associated product.
     *
     * @var ProductInterface
     *
     * @FormAssert\NotBlank()
     *
     * @ORM\JoinColumn(nullable=false)
     * @ORM\ManyToOne(targetEntity="Loevgaard\DandomainFoundation\Entity\Product")
     */
    protected $product;

    /**
     * If the type equals 'sale' this will be the associated order line.
     *
     * @var OrderLineInterface|null
     *
     * @ORM\ManyToOne(targetEntity="Loevgaard\DandomainFoundation\Entity\OrderLine", inversedBy="stockMovements")
     */
    protected $orderLine;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $orderLineRemoved;

    public function __construct()
    {
        $this->complaint = false;
        $this->orderLineRemoved = false;
    }

    public function __call($name, $arguments)
    {
        if ('InclVat' !== substr($name, -7)) {
            throw new \InvalidArgumentException('This class only accepts magic calls ending with `InclVat`');
        }

        $method = substr($name, 0, -7);

        if (false === $method || !method_exists($this, $method)) {
            throw new \InvalidArgumentException('The method `'.$method.'` does not exist');
        }

        /** @var Money $val */
        $val = $this->{$method}();

        return $val->multiply($this->getVatMultiplier());
    }

    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function validate()
    {
        Assert::that($this->product)->isInstanceOf(ProductInterface::class);
        $productNumber = $this->product->getNumber();

        Assert::that($this->quantity)->integer('quantity needs to be an integer', 'quantity')->notEq(0, 'quantity can never be 0');
        Assert::that($this->complaint)->boolean();
        Assert::thatNullOr($this->reference)->string()->maxLength(191);
        Assert::that($this->currency)->string()->length(3);
        Assert::that($this->retailPrice)->integer('retailPrice needs to be an integer', 'retailPrice')->greaterOrEqualThan(0);
        Assert::that($this->totalRetailPrice)->integer('totalRetailPrice needs to be an integer', 'totalRetailPrice')->greaterOrEqualThan(0);
        Assert::that($this->price)->integer('price needs to be an integer', 'price')->greaterOrEqualThan(0);
        Assert::that($this->totalPrice)->integer('totalPrice needs to be an integer', 'totalPrice')->greaterOrEqualThan(0);
        Assert::that($this->discount)->integer('discount needs to be an integer', 'discount');
        Assert::that($this->totalDiscount)->integer('totalDiscount needs to be an integer', 'totalDiscount');
        Assert::that($this->vatPercentage)->float('vatPercent needs to be a float', 'vatPercentage')->greaterOrEqualThan(0);
        Assert::that($this->type)->choice(self::getTypes());

        if ($this->isType(self::TYPE_SALE)) {
            if (!$this->isOrderLineRemoved()) {
                Assert::that($this->orderLine)->isInstanceOf(OrderLineInterface::class);
            }
            Assert::that($this->quantity)->lessThan(0);
        } elseif ($this->isType(self::TYPE_RETURN)) {
            Assert::that($this->quantity)->greaterThan(0, 'quantity should be greater than 0 if the type is a return');
        }

        if ($this->complaint) {
            Assert::that($this->quantity)->lessThan(0, 'quantity needs to be negative when the stock movement is a complaint');
        }

        Assert::thatNullOr($this->product->getIsVariantMaster())->false('['.$productNumber.'] Only simple products and variants is allowed as stock movements');
    }

    /**
     * @param int              $quantity
     * @param Money            $unitPrice
     * @param float            $vatPercent
     * @param string           $type
     * @param ProductInterface $product
     * @param string           $reference
     *
     * @return StockMovement
     *
     * @throws CurrencyMismatchException
     * @throws UndefinedPriceForCurrencyException
     */
    public static function create(int $quantity, Money $unitPrice, float $vatPercent, string $type, ProductInterface $product, string $reference): self
    {
        $stockMovement = new static();
        $stockMovement
            ->setQuantity($quantity)
            ->setPrice($unitPrice)
            ->setVatPercentage($vatPercent)
            ->setType($type)
            ->setProduct($product)
            ->setReference($reference)
        ;

        if ($product->isPriceLess()) {
            $retailPrice = new Money(0, new Currency($unitPrice->getCurrency()->getCode()));
        } else {
            $retailPrice = $product->findPriceByCurrency($unitPrice->getCurrency());
            if (!$retailPrice) {
                throw new UndefinedPriceForCurrencyException('The product `'.$product->getNumber().'` does not have a price defined for currency `'.$unitPrice->getCurrency()->getCode().'`');
            }

            $retailPrice = $retailPrice->getUnitPriceExclVat($vatPercent);
        }

        $stockMovement->setRetailPrice($retailPrice);

        return $stockMovement;
    }

    /**
     * @param OrderLineInterface $orderLine
     *
     * @throws CurrencyMismatchException
     * @throws UndefinedPriceForCurrencyException
     * @throws UnsetProductException
     */
    public function populateFromOrderLine(OrderLineInterface $orderLine)
    {
        if (!$orderLine->getProduct()) {
            throw new UnsetProductException('No product set on order line with product number: '.$orderLine->getProductNumber());
        }

        $created = new \DateTime($orderLine->getOrder()->getCreatedDate()->format(\DateTime::ATOM));

        $this
            ->setQuantity(-1 * $orderLine->getQuantity()) // we multiply by -1 because we count an order as 'outgoing' from the stock
            ->setPrice($orderLine->getUnitPrice())
            ->setVatPercentage($orderLine->getVatPct())
            ->setType(static::TYPE_SALE)
            ->setProduct($orderLine->getProduct())
            ->setOrderLine($orderLine)
            ->setReference('Order '.$orderLine->getOrder()->getExternalId())
            ->setCreatedAt($created)
            ->setUpdatedAt($created) // for order lines we specifically override the createdAt and updatedAt dates because the stock movement is actually happening when the order comes in and not when the order is synced
        ;

        if ($orderLine->getProduct()->getPrices()->count()) {
            $retailPrice = $orderLine->getProduct()->findPriceByCurrency($orderLine->getUnitPrice()->getCurrency());
            if (!$retailPrice) {
                throw new UndefinedPriceForCurrencyException('The product `'.$orderLine->getProduct()->getNumber().'` does not have a price defined for currency `'.$orderLine->getUnitPrice()->getCurrency()->getCode().'`');
            }

            $retailPrice = $retailPrice->getUnitPriceExclVat($orderLine->getVatPct());
        } else {
            $retailPrice = new Money(0, new Currency($orderLine->getUnitPrice()->getCurrency()->getCode()));
        }

        $this->setRetailPrice($retailPrice);
    }

    /**
     * @return StockMovement
     *
     * @throws CurrencyMismatchException
     * @throws UnsetCurrencyException
     */
    public function copy(): self
    {
        $stockMovement = new static();
        $stockMovement
            ->setQuantity($this->getQuantity())
            ->setComplaint($this->isComplaint())
            ->setReference($this->getReference())
            ->setRetailPrice($this->getRetailPrice())
            ->setPrice($this->getPrice())
            ->setVatPercentage($this->getVatPercentage())
            ->setType($this->getType())
            ->setProduct($this->getProduct())
            ->setOrderLine($this->getOrderLine())
            ->setOrderLineRemoved($this->isOrderLineRemoved())
        ;

        return $stockMovement;
    }

    /**
     * @return StockMovement
     *
     * @throws CurrencyMismatchException
     * @throws UnsetCurrencyException
     */
    public function inverse(): self
    {
        $stockMovement = $this->copy();
        $stockMovement->setQuantity($stockMovement->getQuantity() * -1);

        return $stockMovement;
    }

    /**
     * @param StockMovement $stockMovement
     *
     * @return StockMovement
     *
     * @throws CurrencyMismatchException
     * @throws StockMovementProductMismatchException
     * @throws UnsetCurrencyException
     */
    public function diff(self $stockMovement): self
    {
        if ($this->getProduct()->getId() !== $stockMovement->getProduct()->getId()) {
            throw new StockMovementProductMismatchException('Can only compute diff between stock movements where the products equal');
        }

        $qty = -1 * ($this->getQuantity() - $stockMovement->getQuantity());

        $diff = $stockMovement->copy();
        $diff->setQuantity($qty);

        return $diff;
    }

    /******************
     * Helper methods *
     *****************/

    /**
     * Returns the valid types.
     *
     * @return array
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_DELIVERY => self::TYPE_DELIVERY,
            self::TYPE_SALE => self::TYPE_SALE,
            self::TYPE_REGULATION => self::TYPE_REGULATION,
            self::TYPE_RETURN => self::TYPE_RETURN,
        ];
    }

    /**
     * Returns true if $type equals the type of the stock movement.
     *
     * @param string $type
     *
     * @return bool
     */
    public function isType(string $type): bool
    {
        return $this->type === $type;
    }

    /*********************
     * Getters / Setters *
     ********************/

    /**
     * @return int
     */
    public function getId(): int
    {
        return (int) $this->id;
    }

    /**
     * @param int $id
     *
     * @return StockMovement
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return (int) $this->quantity;
    }

    /**
     * @param int $quantity
     *
     * @return StockMovement
     */
    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        $this->updateTotalPrice();
        $this->updateTotalRetailPrice();

        return $this;
    }

    /**
     * @return bool
     */
    public function isComplaint(): bool
    {
        return (bool) $this->complaint;
    }

    /**
     * @param bool $complaint
     *
     * @return StockMovement
     */
    public function setComplaint(bool $complaint): self
    {
        $this->complaint = $complaint;

        return $this;
    }

    /**
     * @return string
     */
    public function getReference(): string
    {
        return (string) $this->reference;
    }

    /**
     * @param string $reference
     *
     * @return StockMovement
     */
    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return (string) $this->currency;
    }

    /**
     * @return Money
     *
     * @throws UnsetCurrencyException
     */
    public function getRetailPrice(): Money
    {
        return $this->money((int) $this->retailPrice);
    }

    /**
     * @param Money $retailPrice
     *
     * @return $this
     *
     * @throws CurrencyMismatchException
     */
    public function setRetailPrice(Money $retailPrice): self
    {
        $this->updateCurrency($retailPrice);
        $this->retailPrice = (int) $retailPrice->getAmount();
        $this->updateTotalRetailPrice();

        return $this;
    }

    /**
     * @return Money
     *
     * @throws UnsetCurrencyException
     */
    public function getTotalRetailPrice(): Money
    {
        return $this->money((int) $this->totalRetailPrice);
    }

    /**
     * @return Money
     *
     * @throws UnsetCurrencyException
     */
    public function getPrice(): Money
    {
        return $this->money((int) $this->price);
    }

    /**
     * @param Money $price
     *
     * @return $this
     *
     * @throws CurrencyMismatchException
     */
    public function setPrice(Money $price): self
    {
        $this->updateCurrency($price);
        $this->price = (int) $price->getAmount();
        $this->updateTotalPrice();

        return $this;
    }

    /**
     * @return Money
     *
     * @throws UnsetCurrencyException
     */
    public function getTotalPrice(): Money
    {
        return $this->money((int) $this->totalPrice);
    }

    /**
     * @return Money
     *
     * @throws UnsetCurrencyException
     */
    public function getDiscount(): Money
    {
        return $this->money((int) $this->discount);
    }

    /**
     * @return Money
     *
     * @throws UnsetCurrencyException
     */
    public function getTotalDiscount(): Money
    {
        return $this->money((int) $this->totalDiscount);
    }

    /**
     * @return float
     */
    public function getVatPercentage(): float
    {
        return (float) $this->vatPercentage;
    }

    /**
     * @param float $vatPercentage
     *
     * @return StockMovement
     */
    public function setVatPercentage(float $vatPercentage): self
    {
        $this->vatPercentage = $vatPercentage;

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return (string) $this->type;
    }

    /**
     * @param string $type
     *
     * @return StockMovement
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return ProductInterface
     */
    public function getProduct(): ?ProductInterface
    {
        return $this->product;
    }

    /**
     * @param ProductInterface $product
     *
     * @return StockMovement
     */
    public function setProduct(ProductInterface $product): self
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return OrderLineInterface|null
     */
    public function getOrderLine(): ?OrderLineInterface
    {
        return $this->orderLine;
    }

    /**
     * @param OrderLineInterface|null $orderLine
     *
     * @return StockMovement
     */
    public function setOrderLine(?OrderLineInterface $orderLine): self
    {
        $this->orderLine = $orderLine;

        return $this;
    }

    /**
     * @return bool
     */
    public function isOrderLineRemoved(): bool
    {
        return (bool) $this->orderLineRemoved;
    }

    /**
     * @param bool $orderLineRemoved
     *
     * @return StockMovement
     */
    public function setOrderLineRemoved(bool $orderLineRemoved)
    {
        $this->orderLineRemoved = $orderLineRemoved;

        return $this;
    }

    /****************************
     * Protected helper methods *
     ***************************/

    protected function updateTotalPrice(): void
    {
        if (is_int($this->price) && is_int($this->quantity)) {
            $this->totalPrice = BigInteger::of($this->price)->multipliedBy(abs($this->quantity))->toInt();

            $this->updateDiscount();
        }
    }

    protected function updateTotalRetailPrice(): void
    {
        if (is_int($this->retailPrice) && is_int($this->quantity)) {
            $this->totalRetailPrice = BigInteger::of($this->retailPrice)->multipliedBy(abs($this->quantity))->toInt();

            $this->updateDiscount();
        }
    }

    protected function updateDiscount(): void
    {
        if (is_int($this->retailPrice) && is_int($this->totalRetailPrice) && is_int($this->price) && is_int($this->totalPrice)) {
            $this->discount = $this->retailPrice - $this->price;
            $this->totalDiscount = $this->totalRetailPrice - $this->totalPrice;
        }
    }

    /**
     * Updates the shared currency.
     *
     * If the currency is already set and the new currency is not the same, it throws an exception
     *
     * @param Money $money
     *
     * @return StockMovement
     *
     * @throws CurrencyMismatchException
     */
    protected function updateCurrency(Money $money): self
    {
        if ($this->currency && $money->getCurrency()->getCode() !== $this->currency) {
            throw new CurrencyMismatchException('The currency on this stock movement is not the same as the one your Money object');
        }

        $this->currency = $money->getCurrency()->getCode();

        return $this;
    }

    /**
     * Returns a new Money object based on the shared currency
     * If no currency is set, it throws an exception.
     *
     * @param int $val
     *
     * @return Money
     *
     * @throws UnsetCurrencyException
     */
    protected function money(int $val): Money
    {
        if (!$this->currency) {
            throw new UnsetCurrencyException('The currency is not set on this stock movement');
        }

        return new Money($val, new Currency($this->currency));
    }

    /**
     * Returns a vat multiplier for this stock movement.
     *
     * Example: You have a vat percentage of (float)25.0 then this method will return (string)1.25
     *
     * @return string
     */
    protected function getVatMultiplier(): string
    {
        return (string) BigDecimal::of('100')->plus($this->vatPercentage)->exactlyDividedBy(100);
    }
}
