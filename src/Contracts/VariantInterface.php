<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * One version of a buyable a customer picks between — a size, a flavour, a
 * gift basket's value. What it is stored as is the shop's: a table, a list on
 * the buyable, an enum. See docs/variants.md.
 *
 * @see HasVariantsInterface
 */
interface VariantInterface
{
    /**
     * Unique among its buyable's variants; what a line stores.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * What a basket, an order and a mail show beside the buyable's title.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * The unit price in cents, or null to sell at the buyable's
     * {@see BuyableInterface::getPrice()}.
     *
     * @return int|null
     */
    public function getPrice(): ?int;
}
