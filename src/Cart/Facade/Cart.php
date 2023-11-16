<?php

namespace Tnt\Ecommerce\Facade;

use Oak\Facade;
use Tnt\Ecommerce\Contracts\CartInterface;

class Cart extends Facade
{
    protected static function getContract(): string
    {
        return CartInterface::class;
    }
}