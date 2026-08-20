<?php

namespace Tnt\Ecommerce\Fulfillment;

use Tnt\Ecommerce\Contracts\AttributeStorageInterface;

/**
 * The attribute half of {@see \Tnt\Ecommerce\Contracts\FulfillmentInterface}.
 *
 * A class using this trait should also declare
 * {@see \Tnt\Ecommerce\Contracts\AttributeStorageAwareInterface}; that is the
 * signal {@see \Tnt\Ecommerce\Shop\Shop::addFulfillment()} looks for before
 * handing it the shop's storage. Without the declaration the method still
 * works, but its attributes last only for the current request.
 */
trait HasFulfillmentAttributes
{
    private ?AttributeStorageInterface $attributeStorage = null;

    /**
     * @param AttributeStorageInterface $storage
     * @return void
     */
    public function setAttributeStorage(
        AttributeStorageInterface $storage
    ): void {
        $this->attributeStorage = $storage;
    }

    /**
     * @return AttributeStorageInterface
     */
    private function attributeStorage(): AttributeStorageInterface
    {
        return $this->attributeStorage ??= new InMemoryAttributeStorage();
    }

    /**
     * @param string $name
     * @return mixed
     * @throws MissingAttribute
     */
    public function getAttribute(string $name)
    {
        if (!$this->hasAttribute($name)) {
            if (in_array($name, $this->requireAttributes(), true)) {
                throw new MissingAttribute($name);
            }

            return null;
        }

        return $this->attributeStorage()->get($name);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setAttribute(string $name, $value)
    {
        $this->attributeStorage()->set($name, $value);
    }

    /**
     * @return array<int, string>
     */
    public function requireAttributes(): array
    {
        return [];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttribute(string $name): bool
    {
        return $this->attributeStorage()->has($name);
    }

    /**
     * @return bool
     */
    public function validateAttributes(): bool
    {
        foreach ($this->requireAttributes() as $reqAttr) {
            if (!$this->hasAttribute($reqAttr)) {
                return false;
            }
        }

        return true;
    }
}
