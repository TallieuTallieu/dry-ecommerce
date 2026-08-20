<?php

namespace Tnt\Ecommerce\Shop;

use Tnt\Ecommerce\Contracts\AttributeStorageAwareInterface;
use Tnt\Ecommerce\Contracts\AttributeStorageInterface;
use Tnt\Ecommerce\Contracts\FulfillmentInterface;
use Tnt\Ecommerce\Contracts\ShopInterface;

/**
 * The register of fulfillment methods a shop offers.
 *
 * It is also where a fulfillment method is handed the storage its attributes
 * live in, which is the reason the methods themselves no longer reach for the
 * session.
 */
class Shop implements ShopInterface
{
    /**
     * @var array<string|int, FulfillmentInterface>
     */
    private array $fulfillments = [];

    private AttributeStorageInterface $attributeStorage;

    /**
     * @param AttributeStorageInterface $attributeStorage
     */
    public function __construct(AttributeStorageInterface $attributeStorage)
    {
        $this->attributeStorage = $attributeStorage;
    }

    /**
     * @param FulfillmentInterface $fulfillment
     * @return mixed|void
     */
    public function addFulfillment(FulfillmentInterface $fulfillment)
    {
        if ($fulfillment instanceof AttributeStorageAwareInterface) {
            $fulfillment->setAttributeStorage($this->attributeStorage);
        }

        $this->fulfillments[$fulfillment->getId()] = $fulfillment;
    }

    /**
     * @param string|int $id
     * @return FulfillmentInterface
     */
    public function getFulfillment($id): FulfillmentInterface
    {
        return $this->fulfillments[$id];
    }

    /**
     * @param string|int $id
     * @return bool
     */
    public function hasFulfillment($id): bool
    {
        return isset($this->fulfillments[$id]);
    }

    /**
     * @return array<string|int, FulfillmentInterface>
     */
    public function getFulfillments(): array
    {
        return $this->fulfillments;
    }
}
