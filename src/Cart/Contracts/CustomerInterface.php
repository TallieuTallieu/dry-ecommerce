<?php

namespace Tnt\Ecommerce\Contracts;

interface CustomerInterface
{
    /**
     * @return string
     */
    public function getFirstName(): string;

    /**
     * @return string
     */
    public function getLastName(): string;
}