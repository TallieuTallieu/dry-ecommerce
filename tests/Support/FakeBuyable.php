<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Contracts\BuyableInterface;

/**
 * Something sellable that is not a database row, with no stock and no tax.
 *
 * The plainest buyable the package now allows, and the one the acceptance
 * criterion "a buyable can be implemented with no stock and no tax" is about:
 * five methods, all of which any model that is for sale can answer. Before
 * `BuyableInterface` was slimmed this class could not have been written without
 * also handing back a `NullStockWorker` and a `NullTaxRate` — which it did, and
 * which is what the two capability interfaces removed the need for.
 *
 * The three fakes that add a capability extend this one, so that the difference
 * between the four combinations is exactly the method each of them adds:
 * {@see FakeTaxableBuyable}, {@see FakeStockedBuyable} and
 * {@see FakeStockedTaxableBuyable}.
 *
 * Prices are integer cents, so `1225` is €12.25.
 */
class FakeBuyable implements BuyableInterface
{
    /**
     * @param string $id
     * @param int $price The unit price, in cents.
     * @param string $title
     */
    public function __construct(
        private readonly string $id,
        private readonly int $price,
        private readonly string $title = 'A thing'
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return 'Description of ' . $this->title;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function getThumbnailSource(): string
    {
        return '';
    }
}
