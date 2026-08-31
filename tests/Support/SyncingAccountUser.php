<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Account\SyncsCustomer;
use Tnt\Ecommerce\Model\Customer;

/**
 * What a project opting into {@see SyncsCustomer} writes: its own account
 * model, the trait, nothing else. The one override is the trait's find seam,
 * answered from a property instead of the customer table — so the sync logic
 * itself (apply the mapping, write only on change) runs as itself.
 */
class SyncingAccountUser extends UnsavedAccountModel
{
    use SyncsCustomer;

    /**
     * The customer row on this account, or null for an account that never
     * shopped — what `CustomerRepository::byUser()` would have answered.
     *
     * @var Customer|null
     */
    public ?Customer $coupledCustomer = null;

    /**
     * @return Customer|null
     */
    protected function customerToSync(): ?Customer
    {
        return $this->coupledCustomer;
    }
}
