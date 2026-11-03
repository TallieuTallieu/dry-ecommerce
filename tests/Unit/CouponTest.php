<?php

declare(strict_types=1);

/*
 * The package's own coupon, without a database: InMemoryCoupon answers the
 * SQL seams from a shared store and reads a clock the test sets, so every
 * refusal, the reduction for each type, the honoured quote on an order and
 * the code rules run as production code.
 */

use Tests\Support\CouponStore;
use Tests\Support\FakeBuyable;
use Tests\Support\FakeCoupon;
use Tests\Support\FakeDiscountCode;
use Tests\Support\FakeFulfillment;
use Tests\Support\FakeUndiscountedBuyable;
use Tests\Support\InMemoryCoupon;
use Tests\Support\InMemoryOrder;
use dry\api\ConvertableToApiException;
use Tnt\Ecommerce\CouponCodeRefused;
use Tnt\Ecommerce\CouponTakesNothing;
use Tnt\Ecommerce\Discount\CodeGenerator;
use Tnt\Ecommerce\Discount\CouponRefusal;
use Tnt\Ecommerce\Model\Coupon;
use Tnt\Ecommerce\Model\DiscountCode;
use Tnt\Ecommerce\NotAPercentage;
use Tnt\Ecommerce\Repository\DiscountCodeRepository;

/**
 * A cart holding 3000 that takes a discount and 5000 that does not.
 *
 * @return Tnt\Ecommerce\Cart\Cart
 */
function cartWithDeposit(): Tnt\Ecommerce\Cart\Cart
{
    [$cart] = makeCart();
    $cart->add(new FakeBuyable('1', 3000));
    $cart->add(new FakeUndiscountedBuyable('2', 5000));

    return $cart;
}

it(
    'makes a percentage coupon single-use and a fixed one unlimited',
    function (): void {
        $percentage = Coupon::percentage(10);
        $fixed = Coupon::fixed(2500);

        expect($percentage->getMaxUses())->toBe(1);
        expect($percentage->getUses())->toBe(0);
        expect($fixed->getMaxUses())->toBeNull();
        expect($fixed->getBalance())->toBe(2500);
        expect($fixed->reachesDelivery())->toBeTrue();
        expect($percentage->reachesDelivery())->toBeFalse();
    }
);

it('stores a percentage in hundredths', function (
    int|float|string $given,
    int $stored,
    string $label
): void {
    $coupon = Coupon::percentage($given);

    expect($coupon->getValue())->toBe($stored);
    expect($coupon->getPercentageLabel())->toBe($label);
})->with([
    [7.5, 750, '7.5'],
    ['7,5', 750, '7.5'],
    ['7.50', 750, '7.5'],
    [' 12,25 ', 1225, '12.25'],
    [10, 1000, '10'],
    ['100', 10000, '100'],
    ['0.05', 5, '0.05'],
]);

it('refuses a percentage it cannot store exactly', function (
    int|float|string $given
): void {
    Coupon::percentage($given);
})
    ->with([
        ['7.555'],
        ['0'],
        ['101'],
        ['abc'],
        [''],
        ['-5'],
        ['1e2'],
        [12.345],
        ['99999999'],
    ])
    ->throws(NotAPercentage::class);

it('rounds a percentage once, on the lines it reaches', function (): void {
    // Three lines of 10 cents at 7.5%: per line, 0.75 rounds to 1 three
    // times; once, on 30, it is 2.25 and rounds to 2.
    [$cart] = makeCart();
    $cart->add(new FakeBuyable('1', 10));
    $cart->add(new FakeBuyable('2', 10));
    $cart->add(new FakeBuyable('3', 10));

    expect(Coupon::percentage('7,5')->getReduction($cart))->toBe(2);
});

it(
    'takes a percentage of the lines that take a discount only',
    function (): void {
        expect(Coupon::percentage(10)->getReduction(cartWithDeposit()))->toBe(
            300
        );
    }
);

it(
    'takes a fixed amount off the lines and delivery, up to its balance',
    function (): void {
        $fulfillment = new FakeFulfillment('post', 500);
        [$cart] = makeCart([$fulfillment]);
        $cart->add(new FakeBuyable('1', 3000));
        $cart->add(new FakeUndiscountedBuyable('2', 5000));
        $cart->setFulfillment($fulfillment);

        expect(Coupon::fixed(2000)->getReduction($cart))->toBe(2000);
        expect(Coupon::fixed(9000)->getReduction($cart))->toBe(3500);
    }
);

it('applies when nothing refuses it', function (): void {
    $coupon = InMemoryCoupon::fixed(2000);

    expect($coupon->refusal(cartWithDeposit()))->toBeNull();
    expect($coupon->isRedeemable(cartWithDeposit()))->toBeTrue();
});

it('refuses a coupon before its first day', function (): void {
    $coupon = InMemoryCoupon::percentage(10);
    $coupon->starts = $coupon->clock + 1;

    expect($coupon->refusal(cartWithDeposit()))->toBe(
        CouponRefusal::NotStarted
    );

    $coupon->clock++;

    expect($coupon->refusal(cartWithDeposit()))->toBeNull();
});

it(
    'keeps a coupon to the end of its last day, then refuses it',
    function (): void {
        $coupon = InMemoryCoupon::percentage(10);
        $coupon->ends = $coupon->clock;
        $coupon->keptIn(new CouponStore())->save();

        $endOfDay = (int) strtotime(date('Y-m-d 23:59:59', $coupon->clock));

        expect($coupon->getEnds())->toBe($endOfDay);

        $coupon->clock = $endOfDay;
        expect($coupon->refusal(cartWithDeposit()))->toBeNull();

        $coupon->clock = $endOfDay + 1;
        expect($coupon->refusal(cartWithDeposit()))->toBe(
            CouponRefusal::Expired
        );
    }
);

it('refuses a coupon used as often as it may be', function (): void {
    $coupon = InMemoryCoupon::percentage(10);
    $coupon->uses = 1;

    expect($coupon->refusal(cartWithDeposit()))->toBe(CouponRefusal::UsedUp);

    $coupon->max_uses = null;

    expect($coupon->refusal(cartWithDeposit()))->toBeNull();
});

it('refuses a fixed coupon with nothing left on it', function (): void {
    $coupon = InMemoryCoupon::fixed(2000);
    $coupon->balance = 0;

    expect($coupon->refusal(cartWithDeposit()))->toBe(CouponRefusal::Empty);
});

it(
    'holds the minimum against the lines that take a discount',
    function (): void {
        // 3000 takes a discount, 5000 does not: a minimum of 4000 is not met,
        // although the cart holds 8000.
        $coupon = InMemoryCoupon::percentage(10);
        $coupon->minimum = 4000;

        expect($coupon->refusal(cartWithDeposit()))->toBe(
            CouponRefusal::BelowMinimum
        );

        $coupon->minimum = 3000;

        expect($coupon->refusal(cartWithDeposit()))->toBeNull();
    }
);

it('names the first reason in a fixed order', function (): void {
    $coupon = InMemoryCoupon::fixed(2000);
    $coupon->ends = $coupon->clock - 1;
    $coupon->max_uses = 1;
    $coupon->uses = 1;
    $coupon->balance = 0;
    $coupon->minimum = 999999;

    expect($coupon->refusal(cartWithDeposit()))->toBe(CouponRefusal::Expired);

    $coupon->ends = null;
    expect($coupon->refusal(cartWithDeposit()))->toBe(CouponRefusal::UsedUp);

    $coupon->max_uses = null;
    expect($coupon->refusal(cartWithDeposit()))->toBe(CouponRefusal::Empty);

    $coupon->balance = 100;
    expect($coupon->refusal(cartWithDeposit()))->toBe(
        CouponRefusal::BelowMinimum
    );
});

it(
    'takes nothing off, and refuses nothing, when nothing is reachable',
    function (): void {
        [$cart] = makeCart();
        $cart->add(new FakeUndiscountedBuyable('1', 5000));

        $coupon = InMemoryCoupon::percentage(10);

        expect($coupon->refusal($cart))->toBeNull();
        expect($coupon->getReduction($cart))->toBe(0);
    }
);

it('tells a controller why a code does not apply', function (): void {
    $cart = cartWithDeposit();
    $spent = InMemoryCoupon::fixed(2000);
    $spent->balance = 0;

    expect(CouponRefusal::of(null, $cart))->toBe(CouponRefusal::Unknown);
    expect(CouponRefusal::of(FakeDiscountCode::withoutCoupon(), $cart))->toBe(
        CouponRefusal::Unknown
    );
    expect(
        CouponRefusal::of(FakeDiscountCode::withCoupon($spent), $cart)
    )->toBe(CouponRefusal::Empty);
    expect(
        CouponRefusal::of(
            FakeDiscountCode::withCoupon(InMemoryCoupon::fixed(10)),
            $cart
        )
    )->toBeNull();
});

it('reads a coupon that cannot say why as unknown', function (): void {
    $cart = cartWithDeposit();

    expect(
        CouponRefusal::of(
            FakeDiscountCode::withCoupon(
                new FakeCoupon(100, redeemable: false)
            ),
            $cart
        )
    )->toBe(CouponRefusal::Unknown);
    expect(
        CouponRefusal::of(
            FakeDiscountCode::withCoupon(new FakeCoupon(100)),
            $cart
        )
    )->toBeNull();
});

it('honours on an order what the order was quoted', function (): void {
    // Whatever happened to the coupon since placement — spent, expired —
    // the order took this much off, and the Paid listener must reach
    // redeem() to account for it.
    $coupon = InMemoryCoupon::fixed(5000)->keptIn(new CouponStore());
    $coupon->balance = 0;
    $coupon->ends = $coupon->clock - 1;

    $order = new InMemoryOrder();
    $order->reduction = 3000;
    $order->discount = FakeDiscountCode::forCoupon($coupon);

    expect($coupon->getReduction($order))->toBe(3000);
    expect($coupon->isRedeemable($order))->toBeTrue();
});

it('claims nothing on an order placed with another coupon', function (): void {
    $store = new CouponStore();
    $coupon = InMemoryCoupon::fixed(5000)->keptIn($store);
    $other = InMemoryCoupon::fixed(5000)->keptIn($store);

    $order = new InMemoryOrder();
    $order->reduction = 3000;
    $order->discount = FakeDiscountCode::forCoupon($other);

    expect($coupon->getReduction($order))->toBe(0);
    expect($coupon->isRedeemable($order))->toBeFalse();

    $order->discount = FakeDiscountCode::forCoupon($coupon);
    $order->reduction = 0;

    expect($coupon->isRedeemable($order))->toBeFalse();
});

it(
    'generates an eight-character code from the Crockford alphabet',
    function (): void {
        $codes = [];

        for ($i = 0; $i < 200; $i++) {
            $code = CodeGenerator::generate();

            expect($code)->toMatch('/^[0-9ABCDEFGHJKMNPQRSTVWXYZ]{8}$/');
            $codes[$code] = true;
        }

        expect($codes)->toHaveCount(200);
    }
);

it(
    'gives a coupon saved without a code a free generated one',
    function (): void {
        $store = new CouponStore();
        $holder = InMemoryCoupon::fixed(100)->keptIn($store);
        $holder->code = 'TAKEN123';
        $holder->save();

        $coupon = InMemoryCoupon::percentage(10)->keptIn($store);
        $coupon->generated = ['taken123', 'FREE4567'];
        $coupon->save();

        expect($coupon->code)->toBe('FREE4567');
        expect($store->codes[(int) $coupon->id]->coupon_class)->toBe(
            Coupon::class
        );
    }
);

it(
    'refuses a code another save took in the meantime, and keeps no coupon without one',
    function (): void {
        // Both saves found SUMMER10 free; the other one wrote its code row
        // first, between this coupon's row and its code row.
        $store = new CouponStore();
        $coupon = InMemoryCoupon::percentage(10)->savingTo($store);
        $coupon->code = 'summer10';
        $coupon->meanwhile = static function () use ($store): void {
            $other = InMemoryCoupon::fixed(1000)->keptIn($store);
            $other->code = 'SUMMER10';
            $other->save();
        };

        expect(couponApiError(static fn() => $coupon->save()))->toBe([
            'ecommerce.coupon_code_taken',
            ['field' => 'code'],
        ]);
        expect($coupon->id)->toBeNull();
        expect($store->coupons)->toHaveCount(1);
        expect($store->codes)->toHaveCount(1);
    }
);

it(
    'keeps a coupon and its old code when another save takes its new one',
    function (): void {
        $store = new CouponStore();
        $coupon = InMemoryCoupon::percentage(10)->keptIn($store);
        $coupon->code = 'SPRING10';
        $coupon->save();

        $coupon->code = 'SUMMER10';
        $coupon->meanwhile = static function () use ($store): void {
            $other = InMemoryCoupon::fixed(1000)->keptIn($store);
            $other->code = 'SUMMER10';
            $other->save();
        };

        expect(couponApiError(static fn() => $coupon->save()))->toBe([
            'ecommerce.coupon_code_taken',
            ['field' => 'code'],
        ]);
        expect($coupon->id)->not->toBeNull();
        expect($store->codes[(int) $coupon->id]->code)->toBe('SPRING10');
    }
);

it('stores a typed code trimmed and uppercase', function (): void {
    $coupon = InMemoryCoupon::percentage(10)->keptIn(new CouponStore());
    $coupon->code = '  summer10 ';
    $coupon->save();

    expect($coupon->code)->toBe('SUMMER10');
});

it('refuses a code another coupon holds, in any case', function (): void {
    $store = new CouponStore();
    $holder = InMemoryCoupon::fixed(100)->keptIn($store);
    $holder->code = 'SUMMER10';
    $holder->save();

    $coupon = InMemoryCoupon::percentage(10)->keptIn($store);
    $coupon->code = 'Summer10';
    $coupon->save();
})->throws(CouponCodeRefused::class);

it('keeps the code of a coupon that has been used', function (): void {
    $coupon = InMemoryCoupon::percentage(10)->keptIn(new CouponStore());
    $coupon->code = 'SUMMER10';
    $coupon->save();
    $coupon->uses = 1;

    // The same code, in any case, is no change.
    $coupon->code = 'summer10';
    $coupon->save();

    $coupon->code = 'WINTER10';
    $coupon->save();
})->throws(CouponCodeRefused::class);

it(
    'never writes the balance or the uses on an existing row',
    function (): void {
        // dry's save rewrites every loaded column: an admin saving a note must
        // not put back a balance a payment took from in the meantime.
        $coupon = InMemoryCoupon::fixed(5000);
        $coupon->code = 'GIFT5000';
        $coupon->save();

        $coupon->note = 'For the raffle';
        $coupon->save();

        [$insert, $update] = $coupon->writes;

        expect($insert)->toContain('balance');
        expect($insert)->toContain('uses');
        expect($update)->not->toContain('balance');
        expect($update)->not->toContain('uses');
        expect($update)->toContain('note');

        // Kept on the object, only not written.
        expect($coupon->getBalance())->toBe(5000);
    }
);

it('reads the admin fields for the type the coupon is', function (): void {
    $fixed = new InMemoryCoupon();
    $fixed->type = 'fixed';
    $fixed->amount = '12,50';
    $fixed->percent = '50';
    $fixed->minimum_amount = '25';
    $fixed->max_uses = 0;
    $fixed->save();

    expect($fixed->getValue())->toBe(1250);
    expect($fixed->getBalance())->toBe(1250);
    expect($fixed->getMinimum())->toBe(2500);
    expect($fixed->getMaxUses())->toBeNull();
    expect($fixed->amount)->toBe('12.50');
    expect($fixed->percent)->toBe('');

    $percentage = new InMemoryCoupon();
    $percentage->type = 'percentage';
    $percentage->percent = '7,5';
    $percentage->amount = '12.50';
    $percentage->max_uses = 1;
    $percentage->save();

    expect($percentage->getValue())->toBe(750);
    expect($percentage->percent)->toBe('7.5');
    expect($percentage->getBalance())->toBe(0);
});

it('stores and finds codes uppercase', function (): void {
    expect(DiscountCode::normalise('  summer10 '))->toBe('SUMMER10');

    $repository = DiscountCodeRepository::create()->byCode(' summer10 ');

    $method = new ReflectionMethod(
        Tnt\Dbi\Repository::class,
        'createQueryBuilder'
    );
    $builder = $method->invoke($repository);
    assert($builder instanceof Tnt\Dbi\QueryBuilder);

    $builder->selectAll();
    $builder->build();

    expect($builder->getParameters())->toBe(['SUMMER10']);
});

/**
 * What dry's API router answers for the refusal a call raises: the
 * exception's to_api_exception(), as code and data.
 *
 * @param Closure(): mixed $call
 * @return array{mixed, mixed}
 */
function couponApiError(Closure $call): array
{
    try {
        $call();
    } catch (ConvertableToApiException $refusal) {
        $error = $refusal->to_api_exception();

        return [$error->code, $error->data];
    }

    throw new RuntimeException('Nothing was refused.');
}

it(
    'refuses a coupon that takes off nothing, by name and as before',
    function (): void {
        expect(static fn() => Coupon::fixed(0))->toThrow(
            CouponTakesNothing::class
        );
        expect(static fn() => Coupon::fixed(-5))->toThrow(
            InvalidArgumentException::class
        );
    }
);

it(
    'answers dry\'s API with a code and a field for each refusal on save',
    function (Closure $call, string $code, string $field): void {
        /** @var Closure(): mixed $call */
        expect(couponApiError($call))->toBe([$code, ['field' => $field]]);
    }
)->with([
    'a code another coupon holds' => [
        static function (): void {
            $store = new CouponStore();
            $holder = InMemoryCoupon::fixed(100)->keptIn($store);
            $holder->code = 'SUMMER10';
            $holder->save();

            $coupon = InMemoryCoupon::percentage(10)->keptIn($store);
            $coupon->code = 'summer10';
            $coupon->save();
        },
        'ecommerce.coupon_code_taken',
        'code',
    ],
    'a used coupon\'s new code' => [
        static function (): void {
            $coupon = InMemoryCoupon::percentage(10)->keptIn(new CouponStore());
            $coupon->code = 'SUMMER10';
            $coupon->save();
            $coupon->uses = 1;
            $coupon->code = 'WINTER10';
            $coupon->save();
        },
        'ecommerce.coupon_code_locked',
        'code',
    ],
    'a percentage it cannot store' => [
        static function (): void {
            $coupon = new InMemoryCoupon();
            $coupon->type = 'percentage';
            $coupon->percent = '7.555';
            $coupon->save();
        },
        'ecommerce.coupon_percentage_invalid',
        'percent',
    ],
    'a fixed coupon of nothing' => [
        static function (): void {
            $coupon = new InMemoryCoupon();
            $coupon->type = 'fixed';
            $coupon->amount = '0';
            $coupon->save();
        },
        'ecommerce.coupon_value_invalid',
        'amount',
    ],
    'a percentage left empty' => [
        static function (): void {
            $coupon = new InMemoryCoupon();
            $coupon->type = 'percentage';
            $coupon->percent = '';
            $coupon->save();
        },
        'ecommerce.coupon_value_invalid',
        'percent',
    ],
    'an amount it cannot read' => [
        static function (): void {
            $coupon = new InMemoryCoupon();
            $coupon->type = 'fixed';
            $coupon->amount = '€12';
            $coupon->save();
        },
        'ecommerce.coupon_amount_invalid',
        'amount',
    ],
    'a minimum it cannot read' => [
        static function (): void {
            $coupon = InMemoryCoupon::percentage(10);
            $coupon->minimum_amount = '1.250,00';
        },
        'ecommerce.coupon_amount_invalid',
        'minimum_amount',
    ],
    'Coupon::fixed(0)' => [
        static fn() => Coupon::fixed(0),
        'ecommerce.coupon_value_invalid',
        'amount',
    ],
]);
