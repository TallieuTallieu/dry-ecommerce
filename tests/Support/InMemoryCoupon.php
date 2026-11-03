<?php

declare(strict_types=1);

namespace Tests\Support;

use Tnt\Ecommerce\Model\Coupon;
use Tnt\Ecommerce\Model\DiscountCode;
use Tnt\Ecommerce\Model\Order;

/**
 * The real coupon, with its SQL seams answered from a {@see CouponStore} and
 * a clock the test sets. Everything between the seams — refusal, reduction,
 * the honoured quote, claim-take-settle, the code rules — is production code.
 */
final class InMemoryCoupon extends Coupon
{
    /**
     * 2026-06-15 12:00 UTC.
     */
    public int $clock = 1781524800;

    /**
     * What each persist() would have written, column names only.
     *
     * @var list<list<string>>
     */
    public array $writes = [];

    /**
     * Codes generateCode() hands out first, before random ones.
     *
     * @var list<string>
     */
    public array $generated = [];

    private ?CouponStore $store = null;

    /**
     * Give the coupon a row in the store — once; a second instance of the
     * same coupon finds the row already there.
     *
     * @param CouponStore $store
     * @return $this
     */
    public function keptIn(CouponStore $store): self
    {
        $this->store = $store;

        if ($this->id === null) {
            $this->id = ++$store->lastCouponId;
        }

        $store->coupons[(int) $this->id] ??= [
            'balance' => $this->getBalance(),
            'uses' => $this->getUses(),
        ];

        return $this;
    }

    /**
     * The same coupon as another request would load it: its own instance,
     * the same row.
     *
     * @return self
     */
    public function loadedAgain(): self
    {
        return clone $this;
    }

    /**
     * @return CouponStore
     */
    public function store(): CouponStore
    {
        return $this->store ??= new CouponStore();
    }

    protected function now(): int
    {
        return $this->clock;
    }

    protected function claim(Order $order, int $amount): ?int
    {
        $store = $this->store();

        if (isset($store->redemptions[(int) $order->id])) {
            return null;
        }

        $id = ++$store->lastRedemptionId;

        $store->redemptions[(int) $order->id] = [
            'id' => $id,
            'coupon' => (int) $this->id,
            'amount' => $amount,
            'shortfall' => null,
        ];

        return $id;
    }

    protected function takeBalance(int $amount): ?int
    {
        $store = $this->store();
        $id = (int) $this->id;

        if (!isset($store->coupons[$id])) {
            return null;
        }

        $taken = min($store->coupons[$id]['balance'], $amount);
        $store->coupons[$id]['balance'] -= $taken;
        $store->coupons[$id]['uses']++;

        return $taken;
    }

    protected function countUse(): ?int
    {
        $store = $this->store();
        $id = (int) $this->id;

        if (!isset($store->coupons[$id])) {
            return null;
        }

        return ++$store->coupons[$id]['uses'];
    }

    protected function settle(int $redemption, int $shortfall): void
    {
        foreach ($this->store()->redemptions as $order => $row) {
            if ($row['id'] === $redemption) {
                $this->store()->redemptions[$order]['shortfall'] = $shortfall;
            }
        }
    }

    protected function findCode(): ?DiscountCode
    {
        return $this->id === null
            ? null
            : $this->store()->codes[(int) $this->id] ?? null;
    }

    protected function codeTaken(string $code): bool
    {
        return $this->store()->holds($code);
    }

    protected function generateCode(): string
    {
        return array_shift($this->generated) ?? parent::generateCode();
    }

    protected function persist(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->__data;

        $this->writes[] = array_values(
            array_map(
                static fn(string $key): string => substr(
                    $key,
                    strlen(self::TABLE) + 1
                ),
                array_keys($data)
            )
        );

        if ($this->id === null) {
            $this->keptIn($this->store());
        }
    }

    protected function writeCode(string $code): DiscountCode
    {
        $row = $this->findCode() ?? new DiscountCode();
        $row->coupon_class = Coupon::class;
        $row->coupon_id = (int) $this->id;
        $row->code = $code;

        return $this->store()->codes[(int) $this->id] = $row;
    }
}
