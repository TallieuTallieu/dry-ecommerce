<?php

namespace Tnt\Ecommerce;

use InvalidArgumentException;
use Tnt\Ecommerce\Contracts\BuyableInterface;
use Tnt\Ecommerce\Contracts\VariantInterface;

/**
 * A variant handed to {@see \Tnt\Ecommerce\Cart\Cart::add()} that its
 * buyable does not offer — one its {@see
 * \Tnt\Ecommerce\Contracts\HasVariantsInterface::getVariant()} does not answer
 * for, or one on a buyable without variants. See docs/variants.md.
 */
final class UnknownVariant extends InvalidArgumentException
{
    /**
     * @param BuyableInterface $buyable
     * @param VariantInterface $variant
     * @return self
     */
    public static function on(
        BuyableInterface $buyable,
        VariantInterface $variant
    ): self {
        return new self(
            sprintf(
                "%s #%s does not offer variant '%s'. Look the posted id up " .
                    'with HasVariantsInterface::getVariant() and pass what it ' .
                    'returns, or null for the first.',
                get_class($buyable),
                $buyable->getId(),
                $variant->getId()
            )
        );
    }
}
