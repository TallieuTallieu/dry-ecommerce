<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Account;

use Tnt\Ecommerce\Model\Customer;
use Tnt\Ecommerce\Repository\CustomerRepository;

/**
 * Keeps the coupled customer row in step with the account it belongs to.
 *
 * Opt-in glue for the PROJECT's account-user model — any dry ORM Model with
 * an `id` and an `email` — with no dry-accounts type anywhere in it, the same
 * zero-dependency stance as {@see AccountsUserResolver}: a shop that sells
 * without accounts never loads this file. After every account save, the
 * mapped fields ({@see customerAttributes()}) are copied onto the customer
 * row on this account, when one exists.
 *
 * Deliberately no row creation here: signing up must not mint customer rows —
 * that is {@see Customer::forUser()}'s job, at the first checkout.
 *
 * Consequence: for account customers the ACCOUNT owns the email. A checkout
 * form should not overwrite the row's email — the next account save wins.
 * See docs/customer.md.
 */
trait SyncsCustomer
{
    /**
     * The account save, then the customer row it drags along.
     *
     * @return void
     */
    public function save()
    {
        parent::save();
        $this->syncCustomer();
    }

    /**
     * Copy the mapped fields onto the coupled customer row. No row (an
     * account that never shopped) is a no-op, and so is an up-to-date row —
     * nothing is written unless something actually changed.
     *
     * @return void
     */
    protected function syncCustomer(): void
    {
        $customer = $this->customerToSync();

        if ($customer === null) {
            return;
        }

        $changed = false;

        foreach ($this->customerAttributes() as $column => $value) {
            if ($customer->{$column} === $value) {
                continue;
            }

            $customer->{$column} = $value;
            $changed = true;
        }

        if ($changed) {
            $customer->save();
        }
    }

    /**
     * The customer row on this account, or null — the find behind
     * {@see syncCustomer()}, split out so a test can answer it from memory.
     *
     * @return Customer|null
     */
    protected function customerToSync(): ?Customer
    {
        return CustomerRepository::create()
            ->byUser((int) $this->id)
            ->firstOrNull();
    }

    /**
     * What the account pushes onto its customer row: column => value.
     * Override to map more; compare-then-write is strict, so hand over values
     * exactly as the column stores them.
     *
     * @return array<string, mixed>
     */
    protected function customerAttributes(): array
    {
        return ['email' => (string) $this->email];
    }
}
