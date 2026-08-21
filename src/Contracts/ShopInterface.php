<?php

namespace Tnt\Ecommerce\Contracts;

interface ShopInterface
{
    /**
     * Register a fulfillment method.
     *
     * A method that also implements {@see AttributeStorageAwareInterface} is
     * handed the shop's attribute storage here, which is how it keeps the
     * things a visitor picks during checkout between requests.
     *
     * @param FulfillmentInterface $fulfillment
     * @return mixed
     */
    public function addFulfillment(FulfillmentInterface $fulfillment);

    /**
     * @param string|int $id
     * @return FulfillmentInterface
     */
    public function getFulfillment($id): FulfillmentInterface;

    /**
     * @param string|int $id
     * @return bool
     */
    public function hasFulfillment($id): bool;

    /**
     * @return array<string|int, FulfillmentInterface>
     */
    public function getFulfillments(): array;
}
