<?php

namespace Tnt\Ecommerce\Facade;

use Oak\Facade;
use Tnt\Ecommerce\Contracts\ShopInterface;

class Shop extends Facade
{
    protected static function getContract(): string
    {
        return ShopInterface::class;
    }
}