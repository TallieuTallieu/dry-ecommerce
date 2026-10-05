<?php

namespace Tnt\Ecommerce\Cart;

use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\HasVariantsInterface;
use Tnt\Ecommerce\Contracts\VariantInterface;
use Tnt\Ecommerce\Contracts\VariantStorageInterface;
use Tnt\Ecommerce\UnknownVariant;

/**
 * The one place a line's variant is resolved, referenced and priced, and the
 * holder of the configured {@see VariantStorageInterface}. Static because the
 * line models are ORM rows the container never builds; the service provider
 * sets the storage from `ecommerce.variant_storage`. See docs/variants.md.
 */
final class Variants
{
    /**
     * The option a line's variant rides under. Reserved: whatever a shop puts
     * under it is replaced. On a cart line it holds the variant's id — the
     * line's identity whatever the storage, since the cart merges on options.
     */
    public const KEY = '_variant';

    private static ?VariantStorageInterface $storage = null;

    private function __construct() {}

    /**
     * @param VariantStorageInterface $storage
     * @return void
     */
    public static function useStorage(VariantStorageInterface $storage): void
    {
        self::$storage = $storage;
    }

    /**
     * @return VariantStorageInterface
     */
    public static function storage(): VariantStorageInterface
    {
        return self::$storage ??= new OptionsVariantStorage();
    }

    /**
     * The variant a line of this buyable is added as: the one given, checked
     * against the buyable, or its first when given none. Null for a buyable
     * without variants.
     *
     * @param BuyableInterface $buyable
     * @param VariantInterface|null $variant
     * @return VariantInterface|null
     *
     * @throws UnknownVariant When the buyable does not offer the variant.
     */
    public static function resolve(
        BuyableInterface $buyable,
        ?VariantInterface $variant
    ): ?VariantInterface {
        if (!($buyable instanceof HasVariantsInterface)) {
            if ($variant !== null) {
                throw UnknownVariant::on($buyable, $variant);
            }

            return null;
        }

        if ($variant === null) {
            foreach ($buyable->getVariants() as $first) {
                return $first;
            }

            return null;
        }

        return $buyable->getVariant($variant->getId()) ??
            throw UnknownVariant::on($buyable, $variant);
    }

    /**
     * The options a cart line is stored and merged with: these, referencing
     * this variant, or none. Anything a caller put under {@see KEY} goes.
     *
     * @param array<array-key, mixed> $options
     * @param VariantInterface|null $variant
     * @return array<array-key, mixed>
     */
    public static function forCart(
        array $options,
        ?VariantInterface $variant
    ): array {
        unset($options[self::KEY]);

        if ($variant !== null) {
            $options[self::KEY] = ['id' => $variant->getId()];
        }

        return $options;
    }

    /**
     * The id of the variant a line's options reference, or null.
     *
     * @param array<array-key, mixed> $options
     * @return string|null
     */
    public static function idIn(array $options): ?string
    {
        $id = self::entry($options)['id'] ?? null;

        return is_string($id) || is_int($id) ? (string) $id : null;
    }

    /**
     * What the options hold under {@see KEY}, or [] when it is not a map.
     *
     * @param array<array-key, mixed> $options
     * @return array<array-key, mixed>
     */
    public static function entry(array $options): array
    {
        $entry = $options[self::KEY] ?? null;

        return is_array($entry) ? $entry : [];
    }

    /**
     * The variant a cart line's options point at, as the buyable offers it
     * now; null when there is none, or it is no longer offered.
     *
     * @param BuyableInterface $buyable
     * @param array<array-key, mixed> $options
     * @return VariantInterface|null
     */
    public static function onLine(
        BuyableInterface $buyable,
        array $options
    ): ?VariantInterface {
        $id = self::idIn($options);

        return $id !== null && $buyable instanceof HasVariantsInterface
            ? $buyable->getVariant($id)
            : null;
    }

    /**
     * The variant a line's options reference, known by its id alone: no
     * title, no price. What checkout freezes for a line whose variant the
     * buyable no longer answers for, so the order still says which one was
     * picked. Null when the options reference none.
     *
     * @param array<array-key, mixed> $options
     * @return VariantInterface|null
     */
    public static function referenced(array $options): ?VariantInterface
    {
        $id = self::idIn($options);

        return $id !== null ? new Variant($id, '', null) : null;
    }

    /**
     * The unit price, in cents, of a cart line: its variant's own price, or
     * the buyable's.
     *
     * @param BuyableInterface $buyable
     * @param array<array-key, mixed> $options
     * @return int
     */
    public static function unitPrice(
        BuyableInterface $buyable,
        array $options
    ): int {
        return self::unitPriceOf($buyable, self::onLine($buyable, $options));
    }

    /**
     * The unit price, in cents, of this buyable sold as this variant: the
     * variant's own price, or the buyable's.
     *
     * @param BuyableInterface $buyable
     * @param VariantInterface|null $variant
     * @return int
     */
    public static function unitPriceOf(
        BuyableInterface $buyable,
        ?VariantInterface $variant
    ): int {
        return $variant?->getPrice() ?? $buyable->getPrice();
    }
}
