<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Discount;

use Closure;
use Tnt\Ecommerce\Contracts\CartInterface;
use Tnt\Ecommerce\Contracts\DiscountableInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\Money;

/**
 * What a reduction may come off, how much of it can, and how it spreads: the
 * lines that take a discount ({@see DiscountableInterface}) and, for a coupon
 * that reaches it, delivery. One place for the reach, the clamp and the
 * split, so the cart's tax and a project's own VAT breakdown of a frozen
 * order run the same arithmetic. See docs/discounts.md#what-a-coupon-reaches.
 */
final class Reach
{
    /**
     * @param array<int, int> $weights What each line may give up, keyed as
     *                                 the lines are: its total, or 0 for a
     *                                 line that takes no discount.
     * @param int|null $delivery
     * @param (Closure(): int)|null $readDelivery Read on first use only: a
     *        percentage never reaches delivery, so a cart that never asks
     *        never runs FulfillmentInterface::getCost().
     */
    private function __construct(
        private readonly array $weights,
        private ?int $delivery,
        private readonly ?Closure $readDelivery = null
    ) {}

    /**
     * The reach of a cart — its lines and its fulfillment cost. Anything else
     * that totals (an order) reads as one line of its subtotal that takes the
     * discount, and no delivery.
     *
     * @param TotalingInterface $totaling
     * @return self
     */
    public static function of(TotalingInterface $totaling): self
    {
        if (!($totaling instanceof CartInterface)) {
            return new self([max(0, $totaling->getSubTotal())], 0);
        }

        $weights = [];

        foreach ($totaling->items() as $index => $item) {
            $buyable = $item->getBuyable();
            $takes =
                !($buyable instanceof DiscountableInterface) ||
                $buyable->takesDiscount();

            $weights[$index] = $takes ? max(0, $item->getPrice()) : 0;
        }

        return new self($weights, null, $totaling->getFulfillmentCost(...));
    }

    /**
     * The same arithmetic over amounts a project holds itself — a frozen
     * order's lines, say.
     *
     * @param array<int, array{price: int, takesDiscount: bool}> $lines In
     *        the order the cart had them: the split's last cents follow it.
     * @param int $delivery The fulfillment cost, in cents.
     * @return self
     */
    public static function fromAmounts(array $lines, int $delivery = 0): self
    {
        $weights = [];

        foreach ($lines as $index => $line) {
            $weights[$index] = $line['takesDiscount']
                ? max(0, $line['price'])
                : 0;
        }

        return new self($weights, $delivery);
    }

    /**
     * What the lines that take a discount come to, in cents — what a
     * percentage applies to and a minimum is held against.
     *
     * @return int
     */
    public function lines(): int
    {
        return array_sum($this->weights);
    }

    /**
     * The fulfillment cost, in cents; never below 0.
     *
     * @return int
     */
    public function delivery(): int
    {
        if ($this->delivery === null) {
            $this->delivery =
                $this->readDelivery === null ? 0 : ($this->readDelivery)();
        }

        return max(0, $this->delivery);
    }

    /**
     * The most a reduction may take off.
     *
     * @param bool $withDelivery Whether the coupon reaches delivery.
     * @return int
     */
    public function limit(bool $withDelivery): int
    {
        return $this->lines() + ($withDelivery ? $this->delivery() : 0);
    }

    /**
     * A reduction spread over what it reaches, in one
     * {@see Money::apportion()}: in proportion to the lines that take a
     * discount, a line that takes none weighing 0, and delivery last when it
     * is reached. A reduction past {@see limit()} is clamped first, so no
     * share exceeds what it comes off. With no line opted out and delivery
     * not reached this is exactly the split the cart always made.
     *
     * @param int $reduction In cents.
     * @param bool $withDelivery
     * @return array{lines: array<int, int>, delivery: int} The share of each
     *         line, keyed as the lines are, and delivery's.
     */
    public function split(int $reduction, bool $withDelivery): array
    {
        $amount = max(0, min($reduction, $this->limit($withDelivery)));

        $keys = array_keys($this->weights);
        $weights = array_values($this->weights);

        if ($withDelivery) {
            $weights[] = $this->delivery();
        }

        $shares = Money::apportion($amount, $weights);

        $lines = [];

        foreach ($keys as $position => $key) {
            $lines[$key] = $shares[$position];
        }

        return [
            'lines' => $lines,
            'delivery' => $withDelivery ? $shares[count($keys)] : 0,
        ];
    }
}
