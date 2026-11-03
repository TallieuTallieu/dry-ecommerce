<?php

declare(strict_types=1);

namespace Tnt\Ecommerce\Model;

use DateTimeImmutable;
use DateTimeZone;
use dry\db\Connection;
use dry\db\DuplicateEntryException;
use dry\orm\Model;
use InvalidArgumentException;
use Tnt\Ecommerce\Contracts\CouponInterface;
use Tnt\Ecommerce\Contracts\ReachesDeliveryInterface;
use Tnt\Ecommerce\Contracts\RefusesCouponInterface;
use Tnt\Ecommerce\Contracts\TotalingInterface;
use Tnt\Ecommerce\CouponCodeRefused;
use Tnt\Ecommerce\CouponTakesNothing;
use Tnt\Ecommerce\Discount\CodeGenerator;
use Tnt\Ecommerce\Discount\CouponRefusal;
use Tnt\Ecommerce\Discount\CouponType;
use Tnt\Ecommerce\Discount\Reach;
use Tnt\Ecommerce\Money;
use Tnt\Ecommerce\NotACouponAmount;
use Tnt\Ecommerce\NotAnAmount;
use Tnt\Ecommerce\NotAPercentage;
use Tnt\Ecommerce\Repository\DiscountCodeRepository;

/**
 * The package's own coupon, `ecommerce_coupon`: a percentage off the lines
 * that take a discount, or a fixed amount off those lines and delivery until
 * its balance runs out. Optional dates, minimum and maximum uses. Its code
 * lives in `ecommerce_discount_code` alone, behind the virtual `code`. See
 * docs/discounts.md#the-shipped-coupon.
 *
 * `value` is cents for a fixed coupon and hundredths of a percent for a
 * percentage one (750 is 7.5%). `balance` and `uses` belong to
 * {@see redeem()}: {@see save()} never writes them on an existing row.
 *
 * @property int|null $id
 * @property int $created
 * @property int $updated
 * @property string $type
 * @property int $value
 * @property int|null $balance
 * @property int|null $starts
 * @property int|null $ends
 * @property int|null $minimum
 * @property int|null $max_uses
 * @property int $uses
 * @property string|null $note
 * @property string $code Virtual: the code row's, or the one about to be saved.
 * @property string $percent Virtual: the admin's percentage field, `7.5`.
 * @property string $amount Virtual: the admin's euro field for a fixed value.
 * @property string $minimum_amount Virtual: the admin's euro field for the minimum.
 * @property-read string $balance_amount Virtual: the balance in euros, read-only.
 */
class Coupon extends Model implements
    CouponInterface,
    RefusesCouponInterface,
    ReachesDeliveryInterface
{
    const TABLE = 'ecommerce_coupon';

    /**
     * Written by {@see redeem()} in one statement each, never by
     * {@see save()} on an existing row: dry's save rewrites every loaded
     * column, and an admin saving a note would put back a balance a payment
     * had just taken from.
     */
    private const REDEMPTION_COLUMNS = ['balance', 'uses'];

    /**
     * How many generated codes to try before giving up — at 32^8 codes a
     * second collision means something other than chance.
     */
    private const GENERATE_ATTEMPTS = 5;

    private ?string $pendingCode = null;

    private ?DiscountCode $codeRow = null;

    private bool $codeLoaded = false;

    private ?string $percentInput = null;

    private ?string $amountInput = null;

    /**
     * A percentage coupon, used once unless told otherwise.
     *
     * @param int|float|string $percent `7.5`, `'7,5'` or `'7.5'` — at most
     *                                  two decimals.
     * @return static
     *
     * @throws NotAPercentage
     */
    public static function percentage(int|float|string $percent): static
    {
        /** @phpstan-ignore new.static */
        $coupon = new static();
        $coupon->type = CouponType::Percentage->value;
        $coupon->value = self::hundredths($percent);
        $coupon->max_uses = 1;
        $coupon->uses = 0;

        return $coupon;
    }

    /**
     * A fixed coupon of this many cents, the whole of it its balance, with
     * no limit on uses: it is spent when its balance is.
     *
     * @param int $cents
     * @return static
     *
     * @throws CouponTakesNothing
     */
    public static function fixed(int $cents): static
    {
        if ($cents <= 0) {
            throw CouponTakesNothing::of(CouponType::Fixed);
        }

        /** @phpstan-ignore new.static */
        $coupon = new static();
        $coupon->type = CouponType::Fixed->value;
        $coupon->value = $cents;
        $coupon->balance = $cents;
        $coupon->max_uses = null;
        $coupon->uses = 0;

        return $coupon;
    }

    /**
     * A percentage as the hundredths `value` stores.
     *
     * @param int|float|string $percent
     * @return int
     *
     * @throws NotAPercentage
     */
    public static function hundredths(int|float|string $percent): int
    {
        $text = is_string($percent)
            ? $percent
            : rtrim(rtrim(sprintf('%.10F', $percent), '0'), '.');

        $normalised = str_replace(',', '.', trim($text));

        if (
            preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalised, $parts) !== 1
        ) {
            throw NotAPercentage::unreadable($text);
        }

        $whole = $parts[1];

        // More digits than any percentage up to 100 has: refuse before the
        // cast, which would overflow.
        if (strlen(ltrim($whole, '0')) > 3) {
            throw NotAPercentage::outOfRange($text);
        }

        $hundredths =
            (int) $whole * 100 + (int) str_pad($parts[2] ?? '', 2, '0');

        if ($hundredths < 1 || $hundredths > 10000) {
            throw NotAPercentage::outOfRange($text);
        }

        return $hundredths;
    }

    /**
     * @return CouponType
     */
    public function getType(): CouponType
    {
        return CouponType::tryFrom((string) $this->type) ??
            throw new InvalidArgumentException(
                'A coupon takes off a percentage or a fixed amount.'
            );
    }

    /**
     * Cents for a fixed coupon, hundredths of a percent for a percentage.
     *
     * @return int
     */
    public function getValue(): int
    {
        return (int) $this->value;
    }

    /**
     * The rate a percentage coupon takes off: 7.5 for a value of 750.
     *
     * @return int|float
     */
    public function getPercentage(): int|float
    {
        // A rate, not money: hundredths of a percent back to a percent.
        return $this->getValue() / 100;
    }

    /**
     * The percentage as a person writes it: `7.5`, `10`, `12.25`.
     *
     * @return string
     */
    public function getPercentageLabel(): string
    {
        $value = $this->getValue();
        $fraction = rtrim(sprintf('%02d', $value % 100), '0');

        return intdiv($value, 100) . ($fraction === '' ? '' : '.' . $fraction);
    }

    /**
     * What is left on a fixed coupon, in cents; 0 for a percentage.
     *
     * @return int
     */
    public function getBalance(): int
    {
        return (int) $this->balance;
    }

    /**
     * @return int
     */
    public function getUses(): int
    {
        return (int) $this->uses;
    }

    /**
     * Null: unlimited.
     *
     * @return int|null
     */
    public function getMaxUses(): ?int
    {
        return self::positiveOrNull($this->max_uses);
    }

    /**
     * What the lines that take a discount must come to, in cents, or null.
     *
     * @return int|null
     */
    public function getMinimum(): ?int
    {
        return self::positiveOrNull($this->minimum);
    }

    /**
     * Unix time the coupon starts applying, or null: it always has.
     *
     * @return int|null
     */
    public function getStarts(): ?int
    {
        return self::positiveOrNull($this->starts);
    }

    /**
     * Unix time the coupon stops applying — 23:59:59 of the day chosen — or
     * null: it never expires.
     *
     * @return int|null
     */
    public function getEnds(): ?int
    {
        return self::positiveOrNull($this->ends);
    }

    /**
     * @return string
     */
    public function getNote(): string
    {
        return (string) $this->note;
    }

    /**
     * Why the coupon does not apply, in the order a customer can do
     * something about it last: not yet, no longer, used up, empty, then the
     * minimum — held against the lines that take a discount, never against
     * delivery.
     *
     * @param TotalingInterface $totalingItem
     * @return CouponRefusal|null
     */
    public function refusal(TotalingInterface $totalingItem): ?CouponRefusal
    {
        $now = $this->now();
        $starts = $this->getStarts();
        $ends = $this->getEnds();
        $maxUses = $this->getMaxUses();
        $minimum = $this->getMinimum();

        if ($starts !== null && $now < $starts) {
            return CouponRefusal::NotStarted;
        }

        if ($ends !== null && $now > $ends) {
            return CouponRefusal::Expired;
        }

        if ($maxUses !== null && $this->getUses() >= $maxUses) {
            return CouponRefusal::UsedUp;
        }

        if (
            $this->getType() === CouponType::Fixed &&
            $this->getBalance() <= 0
        ) {
            return CouponRefusal::Empty;
        }

        if ($minimum !== null && Reach::of($totalingItem)->lines() < $minimum) {
            return CouponRefusal::BelowMinimum;
        }

        return null;
    }

    /**
     * For a cart: whether nothing refuses it. For an order: whether the
     * order was placed with this coupon and took something off — the quote
     * stands whatever happened to the coupon since, so the Paid listener
     * always reaches {@see redeem()}.
     *
     * @param TotalingInterface $totalingItem
     * @return bool
     */
    public function isRedeemable(TotalingInterface $totalingItem): bool
    {
        if ($totalingItem instanceof Order) {
            return $this->quotedOn($totalingItem) &&
                $totalingItem->getReduction() > 0;
        }

        return $this->refusal($totalingItem) === null;
    }

    /**
     * For a cart: a percentage of the lines that take a discount, rounded
     * once; or a fixed amount, up to its balance, off those lines and
     * delivery. For an order placed with this coupon: what the order froze.
     *
     * @param TotalingInterface $totalingItem
     * @return int Cents.
     */
    public function getReduction(TotalingInterface $totalingItem): int
    {
        if ($totalingItem instanceof Order) {
            return $this->quotedOn($totalingItem)
                ? max(0, $totalingItem->getReduction())
                : 0;
        }

        $reach = Reach::of($totalingItem);

        if ($this->getType() === CouponType::Percentage) {
            return Money::percentageOf($reach->lines(), $this->getPercentage());
        }

        return max(0, min($this->getBalance(), $reach->limit(true)));
    }

    /**
     * A fixed coupon reaches delivery; a percentage never does.
     *
     * @return bool
     */
    public function reachesDelivery(): bool
    {
        return $this->getType() === CouponType::Fixed;
    }

    /**
     * Spend the coupon on a paid order, honouring the quote: the amount is
     * what the order took off. Claim (one row per order, so a second Paid
     * does nothing), take (one statement — the balance never below 0, the
     * use counted), settle (what the coupon could not cover). Never in a
     * transaction of its own: the project's may be open. See
     * docs/discounts.md#redemption.
     *
     * @param Order $order
     * @return void
     */
    public function redeem(Order $order)
    {
        if ($this->id === null || !$this->quotedOn($order)) {
            return;
        }

        $amount = $order->getReduction();

        if ($amount <= 0) {
            return;
        }

        $redemption = $this->claim($order, $amount);

        if ($redemption === null) {
            return;
        }

        // The row is the truth from here on: this instance's balance and
        // uses are what it loaded, and another payment may have moved both.
        if ($this->getType() === CouponType::Fixed) {
            $covered = $this->takeBalance($amount);
            $shortfall = $amount - ($covered ?? 0);
        } else {
            $uses = $this->countUse();
            $maxUses = $this->getMaxUses();
            $shortfall =
                $uses === null || ($maxUses !== null && $uses > $maxUses)
                    ? $amount
                    : 0;
        }

        $this->settle($redemption, $shortfall);
    }

    /**
     * Whether the order was placed with this coupon's code.
     *
     * @param Order $order
     * @return bool
     */
    protected function quotedOn(Order $order): bool
    {
        $code = $order->discount;

        return $code !== null &&
            $this->id !== null &&
            (int) $code->coupon_id === (int) $this->id &&
            is_a((string) $code->coupon_class, self::class, true);
    }

    /**
     * Saves the coupon and its code. An empty code gets a generated one; a
     * typed one must be free, and cannot change once the coupon has been
     * used. Fills what the admin form leaves to it: the value from the field
     * for its type, a new fixed coupon's balance, `ends` at the end of its
     * day, empty limits as none.
     *
     * @return void
     *
     * @throws CouponCodeRefused
     * @throws CouponTakesNothing
     * @throws NotAPercentage
     * @throws NotACouponAmount
     */
    public function save()
    {
        $this->resolveFields();

        $current = $this->codeRow()?->code;
        $wanted =
            $this->pendingCode === null
                ? null
                : DiscountCode::normalise($this->pendingCode);

        if ($wanted === '') {
            $wanted = null;
        }

        if (
            $wanted !== null &&
            $current !== null &&
            $wanted !== $current &&
            $this->getUses() > 0
        ) {
            throw CouponCodeRefused::locked($current);
        }

        $code = $wanted ?? $current;

        if ($code === null) {
            $code = $this->freeCode();
        } elseif ($code !== $current && $this->codeTaken($code)) {
            throw CouponCodeRefused::taken($code);
        }

        $this->writeRow();

        $this->codeRow = $this->writeCode($code);
        $this->codeLoaded = true;

        $this->pendingCode = null;
    }

    /**
     * The code a customer types, or '' before there is one.
     *
     * @return string
     */
    public function get_code(): string
    {
        if ($this->pendingCode !== null) {
            return DiscountCode::normalise($this->pendingCode);
        }

        return (string) $this->codeRow()?->code;
    }

    /**
     * @param mixed $code Empty: generate one on save.
     * @return void
     */
    public function set_code(mixed $code): void
    {
        $this->pendingCode = is_scalar($code) ? (string) $code : '';
    }

    /**
     * @return string
     */
    public function get_percent(): string
    {
        return $this->type === CouponType::Percentage->value
            ? $this->getPercentageLabel()
            : '';
    }

    /**
     * Read on save, and only for a percentage coupon.
     *
     * @param mixed $percent
     * @return void
     */
    public function set_percent(mixed $percent): void
    {
        $this->percentInput = is_scalar($percent) ? (string) $percent : null;
    }

    /**
     * @return string
     */
    public function get_amount(): string
    {
        return $this->type === CouponType::Fixed->value
            ? Money::toDecimal($this->getValue())
            : '';
    }

    /**
     * Read on save, and only for a fixed coupon.
     *
     * @param mixed $amount Euros, `12.50` or `12,50`.
     * @return void
     */
    public function set_amount(mixed $amount): void
    {
        $this->amountInput = is_scalar($amount) ? (string) $amount : null;
    }

    /**
     * The balance in euros, for the admin to read.
     *
     * @return string
     */
    public function get_balance_amount(): string
    {
        return Money::toDecimal($this->getBalance());
    }

    /**
     * Ignored: a disabled admin field still submits what it showed, and the
     * balance is {@see redeem()}'s to write.
     *
     * @param mixed $amount
     * @return void
     */
    public function set_balance_amount(mixed $amount): void {}

    /**
     * @return string
     */
    public function get_minimum_amount(): string
    {
        $minimum = $this->getMinimum();

        return $minimum === null ? '' : Money::toDecimal($minimum);
    }

    /**
     * @param mixed $amount Euros; empty for no minimum.
     * @return void
     *
     * @throws NotACouponAmount
     */
    public function set_minimum_amount(mixed $amount): void
    {
        $text = is_scalar($amount) ? trim((string) $amount) : '';

        $this->minimum =
            $text === '' ? null : self::cents($text, 'minimum_amount');
    }

    /**
     * The clock refusal() reads — a seam for tests.
     *
     * @return int
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function now(): int
    {
        return time();
    }

    /**
     * Claim the redemption: the row, before any money moves. UNIQUE(order)
     * makes a second claim for the same order fail, which is how a replayed
     * Paid does nothing.
     *
     * @param Order $order
     * @param int $amount
     * @return int|null The redemption's id, or null when the order already
     *                  has one.
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function claim(Order $order, int $amount): ?int
    {
        try {
            return $this->insert(
                'INSERT INTO `ecommerce_coupon_redemption` ' .
                    '(`created`, `coupon`, `order`, `amount`, `shortfall`) ' .
                    'VALUES (?, ?, ?, ?, NULL)',
                $this->now(),
                (int) $this->id,
                (int) $order->id,
                $amount
            );
        } catch (DuplicateEntryException) {
            return null;
        }
    }

    /**
     * Take up to $amount off the balance and count the use, in one
     * statement: LEAST() keeps the balance at 0 or above, and
     * LAST_INSERT_ID(expr) hands what was taken back to this connection
     * alone — two payments at once each see their own figure.
     *
     * @param int $amount
     * @return int|null What the balance covered, or null when the coupon row
     *                  is gone.
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function takeBalance(int $amount): ?int
    {
        // Checked first: the claim's INSERT set LAST_INSERT_ID too, so an
        // UPDATE that matched nothing would read the claim's id back.
        $affected = $this->affected(
            'UPDATE `ecommerce_coupon` ' .
                'SET `balance` = `balance` - LAST_INSERT_ID(LEAST(`balance`, ?)), ' .
                '`uses` = `uses` + 1 ' .
                'WHERE `id` = ?',
            $amount,
            (int) $this->id
        );

        return $affected === 1 ? $this->lastInsertId() : null;
    }

    /**
     * Count one use, in one statement, and read the new count back.
     *
     * @return int|null The uses after this one, or null when the coupon row
     *                  is gone.
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function countUse(): ?int
    {
        $affected = $this->affected(
            'UPDATE `ecommerce_coupon` ' .
                'SET `uses` = LAST_INSERT_ID(`uses` + 1) ' .
                'WHERE `id` = ?',
            (int) $this->id
        );

        return $affected === 1 ? $this->lastInsertId() : null;
    }

    /**
     * Settle the claim: what the coupon could not cover. NULL until this
     * runs — a claimed redemption left unsettled says so.
     *
     * @param int $redemption
     * @param int $shortfall
     * @return void
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function settle(int $redemption, int $shortfall): void
    {
        $this->affected(
            'UPDATE `ecommerce_coupon_redemption` ' .
                'SET `shortfall` = ? WHERE `id` = ?',
            $shortfall,
            $redemption
        );
    }

    /**
     * @param string $sql
     * @param int ...$params
     * @return int The new row's id.
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function insert(string $sql, int ...$params): int
    {
        return Connection::get()->insert_query($sql, ...$params);
    }

    /**
     * One statement, bound as integers, answering the rows it changed. Not
     * dry's update_query(): that answers the insert id when a statement sets
     * one, which LAST_INSERT_ID(expr) does.
     *
     * @param string $sql
     * @param int ...$params
     * @return int
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function affected(string $sql, int ...$params): int
    {
        $statement = Connection::get()->prepare($sql);
        $statement->bind_param(str_repeat('i', count($params)), ...$params);

        if (!$statement->execute()) {
            throw new \RuntimeException($statement->error);
        }

        return (int) $statement->affected_rows;
    }

    /**
     * @return int
     */
    protected function lastInsertId(): int
    {
        $id = $this->field('SELECT LAST_INSERT_ID()');

        return is_numeric($id) ? (int) $id : 0;
    }

    /**
     * @param string $sql
     * @return mixed
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function field(string $sql): mixed
    {
        return Connection::get()->query_field($sql);
    }

    /**
     * This coupon's code row, or null.
     *
     * @return DiscountCode|null
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function findCode(): ?DiscountCode
    {
        if ($this->id === null) {
            return null;
        }

        return DiscountCodeRepository::create()
            ->forCoupon(self::class, (int) $this->id)
            ->firstOrNull();
    }

    /**
     * Whether a code row with this code exists.
     *
     * @param string $code Normalised.
     * @return bool
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function codeTaken(string $code): bool
    {
        return DiscountCodeRepository::create()
            ->byCode($code)
            ->firstOrNull() !== null;
    }

    /**
     * A code, before {@see freeCode()} checks it is free.
     *
     * @return string
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function generateCode(): string
    {
        return CodeGenerator::generate();
    }

    /**
     * Write the coupon row — on an existing row, everything but the columns
     * {@see redeem()} owns.
     *
     * @return void
     */
    protected function writeRow(): void
    {
        if ($this->id === null) {
            $this->persist();

            return;
        }

        /** @var array<string, mixed> $data */
        $data = $this->__data;
        $kept = [];

        foreach (self::REDEMPTION_COLUMNS as $column) {
            $key = self::TABLE . '.' . $column;

            if (array_key_exists($key, $data)) {
                $kept[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        $this->__data = $data;

        try {
            $this->persist();
        } finally {
            /** @var array<string, mixed> $written */
            $written = $this->__data;
            $this->__data = array_merge($written, $kept);
        }
    }

    /**
     * dry's save of whatever columns are loaded. A seam for tests.
     *
     * @return void
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function persist(): void
    {
        parent::save();
    }

    /**
     * Create or update this coupon's code row.
     *
     * @param string $code Normalised.
     * @return DiscountCode The row, as written.
     *
     * @internal A seam for tests, not an extension point.
     */
    protected function writeCode(string $code): DiscountCode
    {
        $row = $this->codeRow();

        if ($row !== null && $row->code === $code) {
            return $row;
        }

        if ($row === null) {
            $row = new DiscountCode();
            $row->created = time();
            $row->coupon_class = self::class;
            $row->coupon_id = (int) $this->id;
        }

        $row->code = $code;
        $row->updated = time();
        $row->save();

        return $row;
    }

    /**
     * @return DiscountCode|null
     */
    private function codeRow(): ?DiscountCode
    {
        if (!$this->codeLoaded) {
            $this->codeRow = $this->findCode();
            $this->codeLoaded = $this->id !== null;
        }

        return $this->codeRow;
    }

    /**
     * @return string
     *
     * @throws CouponCodeRefused
     */
    private function freeCode(): string
    {
        $code = '';

        for ($attempt = 0; $attempt < self::GENERATE_ATTEMPTS; $attempt++) {
            $code = DiscountCode::normalise($this->generateCode());

            if (!$this->codeTaken($code)) {
                return $code;
            }
        }

        throw CouponCodeRefused::taken($code);
    }

    /**
     * @return void
     *
     * @throws CouponTakesNothing
     * @throws NotAPercentage
     * @throws NotACouponAmount
     */
    private function resolveFields(): void
    {
        $type = $this->getType();
        $isNew = $this->id === null;

        $percent = trim((string) $this->percentInput);
        $amount = trim((string) $this->amountInput);

        if ($type === CouponType::Percentage && $percent !== '') {
            $this->value = self::hundredths($percent);
        }

        if ($type === CouponType::Fixed && $amount !== '') {
            $this->value = self::cents($amount, 'amount');
        }

        $this->percentInput = null;
        $this->amountInput = null;

        if ($this->getValue() <= 0) {
            throw CouponTakesNothing::of($type);
        }

        if ($isNew) {
            $this->uses = $this->getUses();

            if ($type === CouponType::Fixed && $this->balance === null) {
                $this->balance = $this->getValue();
            }

            if ((int) $this->created === 0) {
                $this->created = time();
            }
        }

        $this->updated = time();
        $this->starts = $this->getStarts();
        $this->max_uses = $this->getMaxUses();
        $this->minimum = $this->getMinimum();

        $ends = $this->getEnds();
        $this->ends = $ends === null ? null : self::endOfDay($ends);
    }

    /**
     * @param mixed $value
     * @return int|null
     */
    private static function positiveOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * 23:59:59 of the day a timestamp falls on, in the shop's timezone.
     *
     * @param int $timestamp
     * @return int
     */
    private static function endOfDay(int $timestamp): int
    {
        return (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->setTime(23, 59, 59)
            ->getTimestamp();
    }

    /**
     * An admin's euro amount in cents. A decimal comma reads as the full
     * stop; anything else {@see Money::fromDecimal()} refuses is refused on
     * the field it was typed in.
     *
     * @param string $text
     * @param string $field The admin field, for the API error.
     * @return int
     *
     * @throws NotACouponAmount
     */
    private static function cents(string $text, string $field): int
    {
        try {
            return Money::fromDecimal(str_replace(',', '.', trim($text)));
        } catch (NotAnAmount $refusal) {
            throw NotACouponAmount::in($field, $refusal);
        }
    }
}
