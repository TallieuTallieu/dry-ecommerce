<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Something a shop sells.
 *
 * # What a buyable has to have
 *
 * An identity, a title, a price, and the two things every shop template already
 * prints next to them: a description and a thumbnail. That is the whole
 * contract, and everything in it is answerable by any model that is for sale.
 *
 * # What it used to have, and why that was wrong
 *
 * Until this contract was slimmed it also demanded
 * `getStockWorker(): StockWorkerInterface` and
 * `getTaxRate(): TaxRateInterface`. Both were mandatory, so a model with no
 * stock concept and no VAT rate of its own could not be sold at all without
 * first inventing answers to two questions nobody had asked it. The package
 * shipped those inventions itself — a `NullStockWorker` that said everything was
 * always in stock and a `NullTaxRate` that taxed nothing — which is the clearest
 * evidence there is that the two methods did not belong on every buyable. A null
 * implementation exported to consumers is a contract apologising for itself.
 *
 * Stock and tax are now capabilities a buyable opts into, one interface each:
 *
 * - {@see HasStockInterface} — this buyable is counted, and the cart consults
 *   its stock before letting it be added;
 * - {@see TaxableInterface} — this buyable carries a tax rate, and the cart
 *   includes it in {@see CartInterface::getTax()}.
 *
 * Implement neither, either or both. A buyable that implements neither is a
 * complete buyable, not a degenerate one: it is always addable and contributes
 * no tax, and the cart asks it nothing it cannot answer.
 *
 * # About the id
 *
 * The type says `string`, but the value has to be an integer written as one.
 * Nothing here is free to loosen that: a cart line and an order line reference
 * their buyable by class name plus `item_id`, `ecommerce_cart_item.item_id` and
 * `ecommerce_order_item.item_id` are both `int(11)`, and
 * {@see \Tnt\Ecommerce\Model\Order::add()} casts with `(int)` on the way in. A
 * buyable answering `'sku-a'` would be stored, and read back, as item 0.
 *
 * A model with an `int $id` therefore returns `(string) $this->id`. Widening
 * this to `int|string` so that models stop casting is a fair change, and a
 * separate one: it touches every reference above and none of it is needed for
 * stock and tax to become optional.
 */
interface BuyableInterface
{
    /**
     * The buyable's id — an integer, as a string. See the note above.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @return string
     */
    public function getDescription(): string;

    /**
     * The unit price, in cents.
     *
     * @see \Tnt\Ecommerce\Money
     * @return int
     */
    public function getPrice(): int;

    /**
     * @return string
     */
    public function getThumbnailSource(): string;
}
