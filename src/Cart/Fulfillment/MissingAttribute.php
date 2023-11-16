<?php

namespace Tnt\Ecommerce\Fulfillment;

class MissingAttribute extends \Exception
{
    /**
     * @var string $attributeName
     */
    private $attributeName;

    /**
     * @var $data
     */
    private $data;

    /**
     * Exception constructor.
     * @param string $attributeName
     * @param null $data
     */
    public function __construct(string $attributeName, $data = null)
    {
        $this->attributeName = $attributeName;
        $this->data = $data;
    }

    public function getAttributeName()
    {
        return $this->attributeName;
    }
}