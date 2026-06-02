<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use JsonSerializable;
use Throwable;

use function array_reduce;
use function array_shift;
use function class_exists;
use function is_int;
use function throw_if;
use function throw_unless;

/**
 * Immutable time-of-day value object stored as microseconds from midnight.
 *
 * The type models clock time without a date component and normalizes every
 * internal value to a single 24-hour cycle. Offset-based factories and arithmetic
 * therefore wrap across midnight instead of overflowing into calendar semantics.
 *
 * Public hour, minute, second, and microsecond fields expose normalized parts for
 * formatting and comparisons, while `$value` remains the canonical scalar used
 * for ordering and arithmetic.
 * @psalm-immutable
 */
final readonly class Time implements JsonSerializable
{
    public int $hour;

    public int $minute;

    public int $second;

    public int $microsecond;

    private int $value;

    /**
     * Build a normalized time from a raw microsecond offset.
     *
     * Negative or oversized offsets are wrapped into the current day before the
     * individual clock components are derived.
     *
     * @param int $value Raw microseconds offset from midnight.
     */
    private function __construct(int $value)
    {
        $this->value = Unit::Day->wrap($value);
        $microseconds = 0 > $this->value ? -$this->value : $this->value;
        $this->hour = Unit::Hour->whole($microseconds);
        $microseconds = Unit::Hour->remainder($microseconds);
        $this->minute = Unit::Minute->whole($microseconds);
        $microseconds = Unit::Minute->remainder($microseconds);
        $this->second = Unit::Second->whole($microseconds);
        $this->microsecond = Unit::Second->remainder($microseconds);
    }

    /**
     * @return array{0: array{microseconds: int}, 1:array{}}
     */
    public function __serialize(): array
    {
        return [['microseconds' => (int) $this->toOffset(Unit::Microsecond)], []];
    }

    /**
     * @param array{0: array{microseconds: int}, 1:array{}} $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $time = new self($properties['microseconds']);
        $this->value = $time->value;
        $this->hour = $time->hour;
        $this->minute = $time->minute;
        $this->second = $time->second;
        $this->microsecond = $time->microsecond;
    }

    /**
     * @throws InvalidTime
     */
    public static function at(
        int $hour = 0,
        int $minute = 0,
        int $second = 0,
        int $microsecond = 0,
    ): self {
        if (!($hour >= 0 && $hour < 24)) {
            throw InvalidTime::dueToMalformedHour($hour);
        }

        if (!($minute >= 0 && $minute < 60)) {
            throw InvalidTime::dueToMalformedMinute($minute);
        }

        if (!($second >= 0 && $second < 60)) {
            throw InvalidTime::dueToMalformedSecond($second);
        }

        if (!($microsecond >= 0 && $microsecond < 1_000_000)) {
            throw InvalidTime::dueToMalformedMicrosecond($microsecond);
        }

        return new self(
            Unit::Hour->toMicroseconds($hour)
            + Unit::Minute->toMicroseconds($minute)
            + Unit::Second->toMicroseconds($second)
            + $microsecond,
        );
    }

    /**
     * Create a time-of-day from a native date or datetime object.
     *
     * Only the clock fields are preserved. Date and calendar semantics are
     * discarded once the hour/minute/second/microsecond tuple is extracted.
     *
     * @throws InvalidTime
     */
    public static function fromDate(DateTimeInterface $datetime): self
    {
        return self::at(
            (int) $datetime->format('H'),
            (int) $datetime->format('i'),
            (int) $datetime->format('s'),
            (int) $datetime->format('u'),
        );
    }

    /**
     * @throws InvalidTime
     */
    public static function fromNotation(string $value, TimeFormat $format = TimeFormat::Iso8601): self
    {
        return $format->decode($value);
    }

    /**
     * Create a time by interpreting an offset in an arbitrary unit.
     */
    public static function fromOffset(int|float $value, Unit $unit): self
    {
        return new self($unit->toMicroseconds($value));
    }

    public static function midnight(): self
    {
        return new self(0);
    }

    public static function noon(): self
    {
        return new self(Unit::Hour->toMicroseconds(12));
    }

    /**
     * Return the last representable microsecond before the next midnight.
     */
    public static function endOfDay(): self
    {
        return new self(-1);
    }

    /**
     * @throws InvalidTime|TimeException
     */
    public static function now(DateTimeZone|string|null $timezone = null): self
    {
        return self::fromDate(
            new DateTimeImmutable(timezone: self::filterTimezone($timezone)),
        );
    }

    /**
     * @throws InvalidTime
     */
    public static function minOf(self ...$times): self
    {
        throw_if([] === $times, InvalidTime::class, 'minOf() expects at least one time');

        $min = array_shift($times);

        return array_reduce($times, fn (self $min, self $item): self => $item->isBefore($min) ? $item : $min, $min);
    }

    /**
     * @throws InvalidTime
     */
    public static function maxOf(self ...$times): self
    {
        throw_if([] === $times, InvalidTime::class, 'maxOf() expects at least one time');
        $max = array_shift($times);

        return array_reduce($times, fn (self $max, self $item): self => $item->isAfter($max) ? $item : $max, $max);
    }

    /**
     * Convert the normalized internal value into another unit.
     */
    public function toOffset(Unit $unit): int|float
    {
        return $unit->divide($this->value);
    }

    /**
     * @return non-empty-string
     */
    public function toNotation(TimeFormat $format = TimeFormat::Iso8601): string
    {
        return $format->encode($this);
    }

    /**
     * Format the time using ICU locale rules.
     *
     * The optional timezone affects only the formatting context used for the
     * temporary native datetime created during rendering.
     *
     * @throws TimeException
     */
    public function toLocaleString(
        string $locale,
        DateTimeZone|string|null $timezone = null,
        TimeFormatLength $length = TimeFormatLength::Medium,
    ): string {
        static $isSupported = null;
        $isSupported ??= class_exists(IntlDateFormatter::class);
        $isSupported || throw new TimeException('Support for time locale formatting requires the `intl` extension for best performance or run "composer require symfony/polyfill-intl-icu" to install a polyfill.');

        $timeType = match ($length) {
            TimeFormatLength::Full => IntlDateFormatter::FULL,
            TimeFormatLength::Long => IntlDateFormatter::LONG,
            TimeFormatLength::Medium => IntlDateFormatter::MEDIUM,
            TimeFormatLength::Short => IntlDateFormatter::SHORT,
        };

        $timezone = self::filterTimezone($timezone);

        try {
            $formatted = new IntlDateFormatter(
                locale: $locale,
                dateType: IntlDateFormatter::NONE,
                timeType: $timeType,
                timezone: $timezone,
            )->format($this->applyTo(
                new DateTimeImmutable(timezone: $timezone),
            ));
        } catch (Throwable $throwable) {
            throw new TimeException('Unable to convert to locale "'.$locale.'" the current time; Please verify your locale.', $throwable->getCode(), previous: $throwable);
        }

        return false !== $formatted ? $formatted : throw new TimeException('Unable to convert to locale "'.$locale.'" the current time.');
    }

    /**
     * @throws InvalidTime
     *
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->toNotation();
    }

    /**
     * @return int<-1, 1>
     */
    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }

    public function isBefore(self $other): bool
    {
        return 0 > $this->compareTo($other);
    }

    public function isBeforeOrEqual(self $other): bool
    {
        return 0 >= $this->compareTo($other);
    }

    public function isAfter(self $other): bool
    {
        return 0 < $this->compareTo($other);
    }

    public function isAfterOrEqual(self $other): bool
    {
        return 0 <= $this->compareTo($other);
    }

    public function equals(self $other): bool
    {
        return 0 === $this->compareTo($other);
    }

    /**
     * @throws InvalidTime
     */
    public function clamp(self $min, self $max): self
    {
        throw_unless($max->isAfterOrEqual($min), InvalidTime::class, 'The maximum time must be after or equal to the minimum time.');

        return match (true) {
            $this->isBefore($min) => $min,
            $this->isAfter($max) => $max,
            default => $this,
        };
    }

    /**
     * @throws InvalidDuration
     */
    public function shift(Duration $duration): self
    {
        if ($duration->isZero()) {
            return $this;
        }

        $value = $this->value + $duration->total(Unit::Microsecond);

        if (!is_int($value)) {
            throw InvalidDuration::dueToOverflow();
        }

        return new self($value);
    }

    /**
     * @throws InvalidTime
     */
    public function with(
        ?int $hour = null,
        ?int $minute = null,
        ?int $second = null,
        ?int $microsecond = null,
    ): self {
        $hour ??= $this->hour;
        $minute ??= $this->minute;
        $second ??= $this->second;
        $microsecond ??= $this->microsecond;

        return $hour === $this->hour
        && $minute === $this->minute
        && $second === $this->second
        && $microsecond === $this->microsecond
            ? $this : self::at($hour, $minute, $second, $microsecond);
    }

    /**
     * Round the current time to the requested unit using the provided strategy.
     */
    public function roundTo(Unit $unit, RoundingMode $roundingMode = RoundingMode::Nearest): self
    {
        $rounded = $unit->round($this->value, $roundingMode);

        return $this->value === $rounded ? $this : new self($rounded);
    }

    /**
     * Copy this time-of-day onto an existing native date or datetime value.
     */
    public function applyTo(DateTimeInterface $datetime): DateTimeImmutable
    {
        if (!$datetime instanceof DateTimeImmutable) {
            $datetime = DateTimeImmutable::createFromInterface($datetime);
        }

        return $datetime->setTime($this->hour, $this->minute, $this->second, $this->microsecond);
    }

    /**
     * Return the signed linear difference to another time.
     *
     * Unlike {@see distance()}, this method does not wrap across midnight.
     *
     * @throws InvalidDuration
     */
    public function diff(self $other): Duration
    {
        $duration = $other->value - $this->value;

        return 0 > $duration
            ? Duration::of(microseconds: -$duration)->negated()
            : Duration::of(microseconds: $duration);
    }

    /**
     * Return the forward circular distance to another time on the same day cycle.
     *
     * @throws InvalidDuration
     */
    public function distance(self $other): Duration
    {
        /** @var non-negative-int $duration */
        $duration = Unit::Day->wrap($other->value - $this->value);

        return Duration::of(microseconds: $duration);
    }

    /**
     * Normalize a timezone argument into a concrete timezone instance.
     *
     * @throws TimeException
     */
    private static function filterTimezone(DateTimeZone|string|null $timezone): ?DateTimeZone
    {
        try {
            return match (true) {
                null === $timezone => null,
                $timezone instanceof DateTimeZone => $timezone,
                default => new DateTimeZone($timezone),
            };
        } catch (Throwable $throwable) {
            throw new TimeException('Timezone must be a valid IANA Timezone Name supported by '.DateTimeZone::class, $throwable->getCode(), previous: $throwable);
        }
    }
}
