<?php

namespace Tnt\Ecommerce\Fulfillment;

class MissingAttribute extends \Exception
{
    /**
     * @var string $attributeName
     */
    private string $attributeName;

    /**
     * Whatever context the thrower wants to attach alongside the name. The
     * package itself throws without it, so `mixed` is deliberate: this class
     * cannot know what a shop's fulfillment considers useful debugging
     * material.
     *
     * @var mixed $data
     */
    private mixed $data;

    /**
     * Exception constructor.
     * @param string $attributeName
     * @param mixed $data
     */
    public function __construct(string $attributeName, mixed $data = null)
    {
        $this->attributeName = $attributeName;
        $this->data = $data;
    }

    public function getAttributeName(): string
    {
        return $this->attributeName;
    }

    /**
     * The context the thrower attached, or null when — as everywhere in this
     * package — none was given.
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }
}
