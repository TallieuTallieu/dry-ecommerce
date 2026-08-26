<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Something a shop sells: an identity, a title, a price, a description and a
 * thumbnail. Stock and tax are capabilities a buyable opts into separately —
 * {@see HasStockInterface} and {@see TaxableInterface}. See docs/buyable.md.
 */
interface BuyableInterface
{
    /**
     * The buyable's id — must be an integer written as a string: cart and
     * order lines store `item_id` as an int and cast on the way in.
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
