<?php

namespace Tnt\Ecommerce\Fulfillment;

use Tnt\Ecommerce\Contracts\AttributeStorageInterface;

/**
 * Fulfillment attributes kept in an array for the length of one request.
 *
 * This is what a fulfillment method falls back to when nothing has handed it a
 * storage — a method built by hand in a test, or one never registered with the
 * shop. It is also the storage to use in a console command, where there is no
 * session to speak of.
 */
class InMemoryAttributeStorage implements AttributeStorageInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * @param string $name
     * @return mixed
     */
    public function get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->attributes[$name]);
    }
}
