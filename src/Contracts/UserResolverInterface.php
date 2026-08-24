<?php

namespace Tnt\Ecommerce\Contracts;

/**
 * Who, if anyone, is signed in when a checkout happens.
 *
 * The one question this package asks about accounts, and the only thing it is
 * ever told about them. A shop pairing this package with `dry-accounts` binds
 * an implementation that reads the authenticated user; a shop with no accounts
 * at all leaves the default {@see \Tnt\Ecommerce\Account\GuestUserResolver} in
 * place and every checkout is a guest checkout.
 *
 * # Why an id and not a user
 *
 * `dry-accounts` is a supported pairing, not a dependency. It is absent from
 * this package's `require`, and it has to stay absent: a shop that sells
 * without accounts must still be able to install and run. So nothing in `src/`
 * may *require* a class from it to exist, and this contract cannot mention one.
 *
 * An `int` is what survives that. It is what the column holds, it is all
 * {@see \Tnt\Ecommerce\Model\Customer::linkTo()} needs, and it names nothing.
 * The alternative — returning the user object behind some local
 * `UserInterface` of this package's own — would mean every project's user model
 * had to implement an e-commerce interface before it could be linked to an
 * order, which is a demand this package has no business making of a model it
 * does not own.
 *
 * What a shop gives up is object access: `$customer->getUserId()` hands back an
 * id, not a user, and turning one into the other is `User::load($id)` in the
 * project, where the user class is known and already imported. That is the
 * deliberate stopping point — see {@see \Tnt\Ecommerce\Model\Customer} for why
 * the link is stored as a plain id rather than hydrated through the ORM's
 * `$special_fields`.
 *
 * # Why this is not a polymorphic customer
 *
 * The rejected design was a `CustomerInterface` implemented by both this
 * package's `Customer` and the project's user model, so that an order could
 * point at either. That forces `customer_id` + `customer_class` onto
 * `ecommerce_order` and gives up the real foreign key with it — no joins, and
 * every admin query branches on a class name stored in a varchar. An order
 * carries a `Customer` either way, and the account, when there is one, hangs
 * off the customer.
 *
 * @see \Tnt\Ecommerce\Account\GuestUserResolver
 * @see \Tnt\Ecommerce\Account\AccountsUserResolver
 */
interface UserResolverInterface
{
    /**
     * The id of the signed-in user, or null when nobody is signed in.
     *
     * Null is an ordinary answer and not a failure: it is what a guest checkout
     * looks like, and it is what every checkout looks like in a shop without
     * accounts.
     *
     * @return int|null
     */
    public function getCurrentUserId(): ?int;
}
