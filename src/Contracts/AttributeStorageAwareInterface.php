<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Implemented by fulfillment methods that keep attributes between requests.
 *
 * {@see \Tnt\Ecommerce\Fulfillment\HasFulfillmentAttributes} supplies the
 * method; a class using that trait only has to declare the interface so that
 * {@see ShopInterface::addFulfillment()} knows to hand it the shop's storage.
 * Without the declaration the fulfillment still works, but its attributes live
 * only for the current request.
 */
interface AttributeStorageAwareInterface
{
    /**
     * @param AttributeStorageInterface $storage
     * @return void
     */
    public function setAttributeStorage(
        AttributeStorageInterface $storage
    ): void;
}
