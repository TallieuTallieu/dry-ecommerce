<?php

namespace Tnt\Ecommerce\Contracts;

interface CartStorageInterface
{
    /**
     * @param StorableInterface $cart
     * @return mixed
     */
    public function store(StorableInterface $cart);

    /**
     * @return null|StorableInterface
     */
    public function retrieve(): ?StorableInterface;

    /**
     * @return mixed
     */
    public function clear();
}