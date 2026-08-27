# Customer

## A guest is a null customer

`ecommerce_order.customer` is nullable, and null means guest:

```php
$order = $cart->checkout(); // no customer at all
$order->getCustomer(); // null
```

A guest order needs **no customer row**. Everything an invoice prints —
identity, addresses — is frozen on the order's own columns, either by
`freezeCustomer()` when a customer object is handed in, or written
progressively onto a [draft](orders.md#the-place-step) by the project's own
checkout form. The customer row is what it is for: **account continuity** —
the thing that ties this order to the same person's next one.

A shop may still hand `checkout()` a customer for a guest — anything
implementing `CustomerInterface` works, row or not — and the order freezes its
identity as always. What no longer exists is the obligation to invent a
throwaway row just to satisfy a column.

## One row per account

A shop with a signed-in user should check out with the customer row already on
that account, so the same person keeps the same row:

```php
$customer =
    $customerRepository->byUser($userId)->firstOrNull() ?? new Customer();
```

That is what makes the address book a book — with a fresh row each checkout
there is nothing for it to accumulate into, and `ecommerce_address` would hold
one order's addresses rather than a person's.

Never match a visitor to an existing row by email. A row is reused only on the
strength of a proved account. **An email address is not an identity claim** —
matching a checkout to an existing row by email would let anyone check out as
somebody else and merge into their record. `CustomerRepository::byEmail()`
exists so an admin can _find_ the orders placed from an address; checkout does
not use it.

Either way the row is not what an invoice reads. The order takes its own copy
of the name, the email, the VAT number and both addresses at placement.

`CustomerInterface` is five getters — `getFirstName()`, `getLastName()`,
`getEmail()`, `getCompanyName()`, `getVatNumber()` — and that is everything a
checkout has to be able to answer about who placed an order. Addresses are
asked for separately; see [Address](addresses.md).

## Linking a customer to an account

`Customer.user` is a nullable id: the account that was signed in at checkout, or
`NULL` for a guest. It is what lets a shop show a person their orders without
this package and an accounts package keeping two unrelated records of the same
person.

The pairing with [dry-accounts](https://github.com/TallieuTallieu/dry-accounts)
is one config line:

```php
// config/ecommerce.php
'user_resolver' => \Tnt\Ecommerce\Account\AccountsUserResolver::class,
```

That is all. `Cart::checkout()` asks the resolver who is signed in and the
customer row records the answer; nothing else in the checkout changes, and a
guest checkout costs no extra query.

The resolver checks _signed in_, deliberately not _activated_ — dry-accounts
tracks those separately (`isAuthenticatedAndActivated()`). Whether an
unactivated account may buy is trade policy, and refusing it inside the
resolver would not block the checkout; it would silently record it as a guest
one and lose the link. A shop that wants to refuse the sale does so before
checkout.

dry-accounts is **not** a dependency of this package — a shop that sells without
accounts installs and runs exactly as before. Anything else implementing
`UserResolverInterface` works just as well:

```php
final class MyAuthResolver implements
    \Tnt\Ecommerce\Contracts\UserResolverInterface
{
    public function getCurrentUserId(): ?int
    {
        return MyAuth::user()?->id;
    }
}
```

A returning account checks out with the row already linked to its user — the
`byUser()` lookup above finds it. To read the account back:

```php
$userId = $order->getCustomer()->getUserId(); // int|null

if ($userId !== null) {
    $user = \Tnt\Account\Model\User::load($userId);
}
```

The id is deliberately not hydrated into a user object by this package. Doing
that would mean naming a user class it does not own, and a project that has
accounts has that class imported already.

### The column has no foreign key

`ecommerce_customer.user` is the one relation here without a database-level
constraint. The table it would point at belongs to dry-accounts, and a shop
without that package has no such table — MySQL refuses a constraint against a
table that is not there, so declaring one would break the `ecommerce` migrator
on exactly the shops the nullable column exists to support. The two packages
also register separate migrators with no ordering between them, so there is no
point at which the target is known to exist. Add the constraint in your own
schema if your shop always has both.

## A worked checkout, end to end

The whole pairing, from a signed-in visitor to an order that knows whose account
it is on. Nothing here is pseudo-code; this is the flow the package expects.

### 1. Point the resolver at dry-accounts

```php
// config/ecommerce.php
return [
    'user_resolver' => \Tnt\Ecommerce\Account\AccountsUserResolver::class,
];
```

Register `EcommerceServiceProvider` **after** `AccountServiceProvider` — the
resolver is constructed with dry-accounts' `AuthenticationInterface`.

That is the entire integration. There is no glue class to write, no event to
listen for, and no second code path for guests.

### 2. Find the customer row, or start one

```php
use Tnt\Ecommerce\Model\Customer;
use Tnt\Ecommerce\Repository\CustomerRepository;
use Tnt\Ecommerce\Contracts\UserResolverInterface;

$userId = $app->get(UserResolverInterface::class)->getCurrentUserId();

$customer =
    $userId !== null
        ? CustomerRepository::create()->byUser($userId)->firstOrNull()
        : null;

if ($customer === null) {
    $customer = new Customer();
    $customer->created = time();
    $customer->updated = time();
}

$customer->first_name = $form->get('first_name');
$customer->last_name = $form->get('last_name');
$customer->email = $form->get('email');
$customer->company = $form->get('company') ?? '';
$customer->vat = $form->get('vat') ?? '';
$customer->comments = '';
$customer->first_contact = '';
$customer->save();
```

A signed-in visitor gets the row already on their account, so their address
book is the one they built last time. A guest whose addresses the shop wants
frozen through the address-book machinery gets a fresh row (a guest checking
out with **no** customer at all — `checkout()` — is the
[null-customer path](#a-guest-is-a-null-customer): the shop then writes
identity and addresses onto the order or its draft itself). **Do not** look a
guest up by email — see the note above on why that would be a security hole
rather than a convenience.

### 3. Put addresses in the book

```php
use Tnt\Ecommerce\Address\AddressType;
use Tnt\Ecommerce\Model\Address;

$address = new Address();
$address->created = time();
$address->updated = time();
$address->customer = $customer;
$address->setType(AddressType::Billing);
$address->is_default = 1;
$address->street = 'Factuurstraat';
$address->number = '1';
$address->postal_code = '9700';
$address->city = 'Oudenaarde';
$address->country = 'BE';
$address->save();
```

A returning customer skips this and picks from what is already there.

### 4. Say which addresses this checkout uses

```php
$customer->useAddress($billingAddress);
$customer->useAddress($shippingAddress);
```

Deliberately not a column: it is a choice being made right now, and it lives
exactly as long as the request. A book holding one address of a kind does not
need the call — that one is the answer either way.

### 5. Check out

```php
$order = $cart->checkout($customer);
```

One call, guest or account. Inside it, in this order:

1. `Customer::linkTo($userId)` — the account link is attached **first**, before
   the order is written, because the order points at the customer row and that
   row has to be finished by then.
2. The order's money is frozen: total, subtotal, reduction, fulfillment cost,
   tax, and the price convention.
3. `Order::freezeCustomer($customer)` — the order takes its **own copy** of the
   identity and both addresses.
4. The order is saved, gets its reference, and the cart's lines are copied onto
   it.
5. `Created` is dispatched, then the gateway's `pay()` is called.

### 6. What you can ask afterwards

```php
$order->getCustomer(); // the row — whose account this is on, today —
// or null for an order placed with no customer
$order->getBillingAddress(); // a FrozenAddress — where it actually went
$order->getShippingAddress();
$order->getEmail(); // the order's copy, not the customer's

$customer->getUserId(); // int for an account, null for a guest
```

The two records of the same person are not redundant. `getCustomer()` is a
foreign key and reads the row as it stands today; the frozen copy is what the
invoice prints. Editing the address book afterwards moves the first and cannot
touch the second:

```php
foreach ($customer->getAddresses() as $address) {
    $address->street = 'VERPLAATST';
    $address->save();
}

Order::load($order->id)->getBillingAddress()->getStreet();
// 'Factuurstraat' — the order kept its copy
```

### Showing a customer their orders

One query against one column, which is what the `user` id exists for:

```php
$customers = CustomerRepository::create()->byUser($userId)->all();
```

### One caveat

`AccountsUserResolver` calls `Authentication::getUser()` unconditionally, which
reads the session. Outside a web request — a cron, an import, a test — there is
no session, and Oak's file session handler fails on a null id. A checkout in
those contexts has to be built with `GuestUserResolver` instead. See
[Installation](installation.md#checking-it-works).
