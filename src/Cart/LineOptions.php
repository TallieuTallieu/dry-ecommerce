<?php

namespace Tnt\Ecommerce\Cart;

use JsonException;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\PricedByOptionsInterface;

/**
 * The one place per-line options are handled: turned into text and back, and
 * read for the line's unit price. The encoding is canonical — keys sorted at
 * every level, list order kept, empty is NULL — because merging compares the
 * encoded form. The price is asked of the buyable only when it implements
 * {@see PricedByOptionsInterface}. See docs/options.md.
 */
final class LineOptions
{
    private function __construct() {}

    /**
     * The canonical encoded form of a selection, or null when there is none —
     * the only form that is ever stored or compared.
     *
     * @param array<array-key, mixed> $options
     * @return string|null
     *
     * @throws JsonException If a value cannot be carried by JSON.
     */
    public static function canonical(array $options): ?string
    {
        if ($options === []) {
            return null;
        }

        return json_encode(
            self::sortedByKey($options),
            JSON_THROW_ON_ERROR |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * A stored options column read back into the array a caller can use.
     * Deliberately forgiving: NULL or unreadable text reads as no options.
     *
     * @param string|null $encoded
     * @return array<array-key, mixed>
     */
    public static function decode(?string $encoded): array
    {
        if ($encoded === null || $encoded === '') {
            return [];
        }

        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The unit price, in cents, of a line holding this buyable with these
     * options: {@see PricedByOptionsInterface::getPriceFor()} when the
     * buyable opts in, its plain price otherwise.
     *
     * @param BuyableInterface $buyable
     * @param array<array-key, mixed> $options
     * @return int
     */
    public static function unitPrice(
        BuyableInterface $buyable,
        array $options
    ): int {
        return $buyable instanceof PricedByOptionsInterface
            ? $buyable->getPriceFor($options)
            : $buyable->getPrice();
    }

    /**
     * The selection with every associative level sorted by key, recursively.
     *
     * @param array<array-key, mixed> $options
     * @return array<array-key, mixed>
     */
    private static function sortedByKey(array $options): array
    {
        ksort($options);

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $options[$key] = self::sortedByKey($value);
            }
        }

        return $options;
    }
}
