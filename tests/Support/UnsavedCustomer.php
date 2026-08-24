<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\Customer;

/**
 * A real customer, counting its writes instead of making them.
 *
 * `new Customer()` needs no database — dry's `Model` is an array wrapper and
 * every field below is set through it — so the only step that does is `save()`,
 * and overriding that one method leaves the whole of
 * {@see Customer::linkTo()} running as itself with the container stopped.
 *
 * The count is the interesting part rather than a side effect. `linkTo()`
 * promises a guest checkout costs no extra query, and a boolean could not tell
 * "never saved" from "saved once", which is exactly the distinction between the
 * two checkout paths.
 */
final class UnsavedCustomer extends Customer
{
    /**
     * How many times `save()` has been called.
     *
     * @var int
     */
    public int $saves = 0;

    /**
     * @return void
     */
    public function save()
    {
        $this->saves++;
    }
}
