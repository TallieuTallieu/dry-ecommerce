<?php

namespace Tnt\Ecommerce\Contracts;

interface ShopInterface
{
    /**
     * @param FulfillmentInterface $fulfillment
     * @return mixed
     */
    public function addFulfillment(FulfillmentInterface $fulfillment);

    /**
     * @param $id
     * @return FulfillmentInterface
     */
    public function getFulfillment($id): FulfillmentInterface;

    /**
     * @param int $id
     * @return bool
     */
    public function hasFulfillment($id): bool;

    /**
     * @return array
     */
    public function getFulfillments(): array;
}