<?php

declare(strict_types=1);

/*
 * The SyncsCustomer trait: an account save drags the coupled customer row
 * along, and nothing else.
 *
 * Tests\Support\SyncingAccountUser is the trait as a project would use it —
 * its own account model, one seam overridden to answer the byUser() find from
 * memory. The coupled row is an UnsavedCustomer, whose save counter is what
 * pins "no pointless writes".
 */

use Tests\Support\SyncingAccountUser;
use Tests\Support\UnsavedCustomer;

it('propagates an email change to the customer row on save', function (): void {
    $customer = new UnsavedCustomer();
    $customer->email = 'old@example.com';

    $account = new SyncingAccountUser();
    $account->id = 7;
    $account->email = 'new@example.com';
    $account->coupledCustomer = $customer;

    $account->save();

    // The account saved as its parent always did, and the row followed —
    // one write each.
    expect($account->accountSaves)->toBe(1);
    expect($customer->getEmail())->toBe('new@example.com');
    expect($customer->saves)->toBe(1);
});

it('creates no row for an account that never shopped', function (): void {
    // Deliberately not forUser(): signing up, or renaming an account, must
    // not mint customer rows. Creation happens at the first checkout.
    $account = new SyncingAccountUser();
    $account->id = 7;
    $account->email = 'new@example.com';
    $account->coupledCustomer = null;

    $account->save();

    expect($account->accountSaves)->toBe(1);
    expect($account->coupledCustomer)->toBeNull();
});

it('writes nothing when the row is already in step', function (): void {
    $customer = new UnsavedCustomer();
    $customer->email = 'same@example.com';

    $account = new SyncingAccountUser();
    $account->id = 7;
    $account->email = 'same@example.com';
    $account->coupledCustomer = $customer;

    $account->save();

    // The account still saves; the up-to-date row costs no second write.
    expect($account->accountSaves)->toBe(1);
    expect($customer->saves)->toBe(0);
});

it('maps whatever a project says the account owns', function (): void {
    // The override point: one method, column => value. The default maps the
    // email; a project maps more by extending the array.
    $customer = new UnsavedCustomer();
    $customer->email = 'old@example.com';
    $customer->company = 'Old BV';

    $account = new class extends SyncingAccountUser {
        protected function customerAttributes(): array
        {
            return parent::customerAttributes() + [
                'company' => 'Tallieu & Tallieu',
            ];
        }
    };
    $account->id = 7;
    $account->email = 'new@example.com';
    $account->coupledCustomer = $customer;

    $account->save();

    expect($customer->getEmail())->toBe('new@example.com');
    expect($customer->getCompanyName())->toBe('Tallieu & Tallieu');
    expect($customer->saves)->toBe(1);
});

it('saves the account before it syncs the row', function (): void {
    // parent::save() first, then the sync: the account is the source of
    // truth, so the row copies from an account already written.
    $account = new class extends SyncingAccountUser {
        /** @var array<int, string> */
        public array $order = [];

        protected function syncCustomer(): void
        {
            $this->order[] = 'sync-after-' . $this->accountSaves . '-saves';
            parent::syncCustomer();
        }
    };
    $account->id = 7;
    $account->email = 'new@example.com';
    $account->coupledCustomer = null;

    $account->save();

    expect($account->order)->toBe(['sync-after-1-saves']);
});
