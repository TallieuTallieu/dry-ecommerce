<?php

namespace Tnt\Ecommerce\Contracts;

interface CartFactoryInterface
{
    public function create(): StorableInterface;
}