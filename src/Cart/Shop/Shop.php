<?php

namespace Tnt\Ecommerce\Shop;

use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Contracts\ShopInterface;

class Shop implements ShopInterface
{
    /**
     * @var array $fulfillments
     */
    private $fulfillments = [];

    /**
     * @param FulfillmentInterface $fulfillment
     * @return mixed|void
     */
    public function addFulfillment(FulfillmentInterface $fulfillment)
    {
        $this->fulfillments[$fulfillment->getId()] = $fulfillment;
    }

    /**
     * @param $id
     * @return FulfillmentInterface
     */
    public function getFulfillment($id): FulfillmentInterface
    {
        return $this->fulfillments[$id];
    }

    /**
     * @param int $id
     * @return bool
     */
    public function hasFulfillment($id): bool
    {
        return isset($this->fulfillments[$id]);
    }

    /**
     * @return array
     */
    public function getFulfillments(): array
    {
        return $this->fulfillments;
    }
}