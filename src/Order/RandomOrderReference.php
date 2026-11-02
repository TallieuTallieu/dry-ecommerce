<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Order;

use Tnt\Ecommerce\Contracts\OrderReferenceInterface;
use Tnt\Ecommerce\Model\Order;

/**
 * The default reference: the row id and ten random characters,
 * `12-K4M7QX9RTB`. Unguessable to stop enumeration, but not a credential: a
 * page showing an order must still establish who is asking. See
 * docs/orders.md#the-order-reference.
 */
final class RandomOrderReference implements OrderReferenceInterface
{
    /**
     * Crockford's base 32 — no `I`, `L`, `O` or `U`, so a reference survives
     * being read down a telephone.
     */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * How many random characters follow the row id.
     */
    private const LENGTH = 10;

    /**
     * random_int(), never rand() or mt_rand(): Mersenne Twister's next output
     * can be worked out from its previous ones, so somebody who placed a few
     * orders could predict other people's references.
     *
     * @param Order $order
     * @return string
     *
     * @throws \Random\RandomException If the system has no source of secure
     *                                 randomness — deliberately not caught.
     */
    public function reference(Order $order): string
    {
        $reference = '';
        $last = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $reference .= self::ALPHABET[random_int(0, $last)];
        }

        return $order->id . '-' . $reference;
    }
}
