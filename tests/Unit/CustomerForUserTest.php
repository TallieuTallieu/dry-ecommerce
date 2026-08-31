<?php

declare(strict_types=1);

/*
 * Customer::forUser() — the race-safe find-or-create behind "one row per
 * account".
 *
 * The method touches the database at exactly two points, the findByUser()
 * seam and save(), and Tests\Support\ForUserCustomer answers both from an
 * array — so the find, the create, and the lost race all run as themselves
 * with the container stopped. The unique index the race leans on is pinned in
 * RevisionTest and replayed live against the compose MySQL.
 */

use dry\db\DuplicateEntryException;
use Tests\Support\ForUserCustomer;

beforeEach(function (): void {
    ForUserCustomer::reset();
});

it(
    'creates the row on an account\'s first brush with the shop',
    function (): void {
        $customer = ForUserCustomer::forUser(7, 'ada@example.com');

        expect($customer->getUserId())->toBe(7);
        expect($customer->getEmail())->toBe('ada@example.com');
        expect(ForUserCustomer::$saveCalls)->toBe(1);
        expect(ForUserCustomer::$rows[7])->toBe($customer);
    }
);

it('seeds every identity column the schema requires', function (): void {
    // The remaining NOT NULL identity columns start '', not unset: the row
    // must be insertable under strict sql_mode with only an id and an email
    // known — the shop hands over what it knows and nothing more.
    $customer = ForUserCustomer::forUser(7);

    expect($customer->getEmail())->toBe('');
    expect($customer->getFirstName())->toBe('');
    expect($customer->getLastName())->toBe('');
    expect($customer->getCompanyName())->toBe('');
    expect($customer->getVatNumber())->toBe('');
    expect((string) $customer->comments)->toBe('');
    expect((string) $customer->first_contact)->toBe('');
    expect($customer->created)->toBeInt();
    expect($customer->updated)->toBeInt();
});

it('reuses the row an account already has', function (): void {
    $first = ForUserCustomer::forUser(7, 'ada@example.com');
    ForUserCustomer::$saveCalls = 0;

    $again = ForUserCustomer::forUser(7, 'ada@elsewhere.example');

    // The same row, no write — and the email seed did NOT overwrite what the
    // row holds: the seed is for a fresh row only. An account customer's
    // email is the account's to change (SyncsCustomer), not checkout's.
    expect($again)->toBe($first);
    expect($again->getEmail())->toBe('ada@example.com');
    expect(ForUserCustomer::$saveCalls)->toBe(0);
});

it('hands the loser of the insert race the winner\'s row', function (): void {
    // Two requests, no row yet, both pass the find: the second insert throws
    // into the unique index, and the catch re-reads — the index is the lock.
    $winner = new ForUserCustomer();
    $winner->user = 7;
    ForUserCustomer::$loseRaceTo = $winner;

    $customer = ForUserCustomer::forUser(7, 'ada@example.com');

    expect($customer)->toBe($winner);
    expect(ForUserCustomer::$saveCalls)->toBe(1);
});

it('gives two racing calls one row between them', function (): void {
    // The acceptance criterion spelled as code: whichever call loses, both
    // end up holding the same row, and the table holds exactly one.
    $winner = ForUserCustomer::forUser(7, 'ada@example.com');
    ForUserCustomer::$rows = [];
    ForUserCustomer::$loseRaceTo = $winner;

    $loser = ForUserCustomer::forUser(7, 'ada@example.com');

    expect($loser)->toBe($winner);
    expect(ForUserCustomer::$rows)->toHaveCount(1);
});

it('rethrows when the duplicate has no row behind it', function (): void {
    // The one case the catch cannot answer: the winner's row vanished again
    // before the re-read. Swallowing the exception here would return a saved
    // row that is not in the table.
    ForUserCustomer::$winnerVanishes = true;

    expect(fn() => ForUserCustomer::forUser(7))->toThrow(
        DuplicateEntryException::class
    );
});

it('never inserts when the find already answers', function (): void {
    $existing = new ForUserCustomer();
    $existing->user = 7;
    ForUserCustomer::$rows[7] = $existing;

    expect(ForUserCustomer::forUser(7))->toBe($existing);
    expect(ForUserCustomer::$saveCalls)->toBe(0);
});
