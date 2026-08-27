<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Fulfillment;

use Tnt\Ecommerce\Contracts\AttributeStorageInterface;
use Tnt\Ecommerce\Contracts\CartStorageInterface;

/**
 * Fulfillment attributes kept on the visitor's cart row, through whichever
 * {@see CartStorageInterface} is bound — so a chosen slot survives exactly as
 * long as the cart does, and dies with it. The shipped default binding;
 * {@see SessionAttributeStorage} remains for shops that want the old
 * behaviour. Writing with no cart yet creates the row, the same rule as
 * putting something in the cart. See docs/fulfillment.md.
 */
class CartAttributeStorage implements AttributeStorageInterface
{
    private CartStorageInterface $storage;

    /**
     * @param CartStorageInterface $storage
     */
    public function __construct(CartStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function get(string $name)
    {
        return $this->storage->getFulfillmentAttributes()[$name] ?? null;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function set(string $name, $value): void
    {
        $attributes = $this->storage->getFulfillmentAttributes();
        $attributes[$name] = $value;

        $this->storage->setFulfillmentAttributes($attributes);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->storage->getFulfillmentAttributes()[$name]);
    }
}
