<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * A named bag of values that outlives a single request.
 *
 * Fulfillment methods collect things during checkout — a pickup point, a
 * delivery date — that have to survive until the order is placed. They used to
 * reach for the `Session` facade directly, which made every fulfillment method
 * untestable without a live session. This is the seam that replaces it.
 *
 * @see \Tnt\Ecommerce\Fulfillment\SessionAttributeStorage
 * @see \Tnt\Ecommerce\Fulfillment\InMemoryAttributeStorage
 */
interface AttributeStorageInterface
{
    /**
     * @param string $name
     * @return mixed
     */
    public function get(string $name);

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function set(string $name, $value): void;

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool;
}
