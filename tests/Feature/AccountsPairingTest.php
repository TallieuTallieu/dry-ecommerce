<?php

declare(strict_types=1);

/*
 * The pairing with dry-accounts, against the real dry-accounts.
 *
 * A shop running both packages is a stated first-class use case, and this is
 * the file that holds it to that: the classes below are dry-accounts' own, not
 * doubles of them, so a rename or a signature change on its side breaks here
 * rather than in a project.
 *
 * dry-accounts is in require-dev and not in require, deliberately. A shop that
 * sells without accounts must still install and run this package, so nothing in
 * src/ may need a Tnt\Account class to exist. AccountsUserResolver is the one
 * file that names one, and it does so only in a constructor type hint, which
 * PHP resolves lazily — a shop that never configures the resolver never loads
 * the file. The last test below is what keeps that true.
 *
 * No database is involved. dry's models are array wrappers until something
 * calls save() or load(), so a user can be built and read here with the
 * container stopped, like the rest of the suite.
 */

use Tests\Support\FakeAuthentication;
use Tests\Support\UnsavedCustomer;
use Tnt\Account\Model\User;
use Tnt\Ecommerce\Account\AccountsUserResolver;
use Tnt\Ecommerce\Account\GuestUserResolver;
use Tnt\Ecommerce\Contracts\UserResolverInterface;

/**
 * A dry-accounts user with an id, and nothing else it does not need.
 *
 * @param int $id
 * @return User
 */
function accountUser(int $id): User
{
    $user = new User();
    $user->id = $id;
    $user->email = 'ada@example.com';

    return $user;
}

it('reads the signed-in user out of dry-accounts', function (): void {
    $resolver = new AccountsUserResolver(
        new FakeAuthentication(accountUser(42))
    );

    // getIdentifier() is dry-accounts' name for the user's id, and this is the
    // only thing this package ever asks it for.
    expect($resolver->getCurrentUserId())->toBe(42);
});

it('reports a guest when dry-accounts has nobody signed in', function (): void {
    $resolver = new AccountsUserResolver(new FakeAuthentication());

    // Not signed in is an ordinary answer, and it is the same null the default
    // resolver gives — which is what makes guest checkout one path and not two.
    expect($resolver->getCurrentUserId())->toBeNull();
    expect($resolver->getCurrentUserId())->toBe(
        (new GuestUserResolver())->getCurrentUserId()
    );
});

it('links a customer row to a real dry-accounts user', function (
    ?int $signedIn
): void {
    // The whole feature, end to end short of the database: dry-accounts says
    // who is signed in, and the customer row that a checkout is about to write
    // records it. Both checkout paths, one code path.
    $resolver = new AccountsUserResolver(
        new FakeAuthentication(
            $signedIn === null ? null : accountUser($signedIn)
        )
    );

    $customer = new UnsavedCustomer();
    $customer->email = 'ada@example.com';

    $customer->linkTo($resolver->getCurrentUserId());

    expect($customer->getUserId())->toBe($signedIn);

    // A guest costs no write; an account costs exactly one.
    expect($customer->saves)->toBe($signedIn === null ? 0 : 1);
})->with([
    'a guest checkout' => [null],
    'an account checkout' => [42],
]);

it('resolves accounts through the package seam', function (
    string $resolver
): void {
    // Both resolvers answer the same question, so a shop swaps one config value
    // to move between them and nothing else in the package notices.
    expect(is_a($resolver, UserResolverInterface::class, true))->toBeTrue();
})->with([AccountsUserResolver::class, GuestUserResolver::class]);

it('keeps dry-accounts out of require', function (): void {
    /** @var array{require: array<string, string>, require-dev: array<string, string>} $composer */
    $composer = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
        true
    );

    // The constraint the whole design turns on. Moving this line into require
    // would make every shop that sells without accounts install an accounts
    // package, an external-api package and a JWT library to do it.
    expect($composer['require'])->not->toHaveKey('tallieutallieu/dry-accounts');
    expect($composer['require-dev'])->toHaveKey('tallieutallieu/dry-accounts');
});

it('names dry-accounts in exactly one file', function (): void {
    // The blast radius, measured rather than asserted by hand. A second file in
    // src/ referencing Tnt\Account is not automatically wrong, but it is a
    // decision about a soft dependency and should be a deliberate one.
    $offenders = [];
    $root = dirname(__DIR__, 2) . '/src';

    $files = new RegexIterator(
        new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)),
        '/\.php$/'
    );

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        $contents = file_get_contents($file->getPathname());

        if ($contents !== false && str_contains($contents, 'use Tnt\Account')) {
            $offenders[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }

    expect($offenders)->toBe(['Account/AccountsUserResolver.php']);
});
