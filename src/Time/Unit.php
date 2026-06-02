<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use function intdiv;
use function round;

/**
 * Precision and conversion units used by the time subsystem.
 *
 * Every unit resolves to an exact microsecond scale factor so value objects can
 * centralize conversion, wrapping, rounding, and remainder logic in one place.
 */
enum Unit
{
    case Week;
    case Day;
    case Hour;
    case Minute;
    case Second;
    case Millisecond;
    case Microsecond;

    private const int MICRO_PER_MILLI = 1_000;

    private const int MICRO_PER_SECOND = 1_000 * self::MICRO_PER_MILLI;

    private const int MICRO_PER_MINUTE = 60 * self::MICRO_PER_SECOND;

    private const int MICRO_PER_HOUR = 60 * self::MICRO_PER_MINUTE;

    private const int MICRO_PER_DAY = 24 * self::MICRO_PER_HOUR;

    private const int MICRO_PER_WEEK = 7 * self::MICRO_PER_DAY;

    /**
     * Return the exact number of microseconds represented by one unit.
     */
    public function inMicroseconds(): int
    {
        return match ($this) {
            Unit::Week => self::MICRO_PER_WEEK,
            Unit::Day => self::MICRO_PER_DAY,
            Unit::Hour => self::MICRO_PER_HOUR,
            Unit::Minute => self::MICRO_PER_MINUTE,
            Unit::Second => self::MICRO_PER_SECOND,
            Unit::Millisecond => self::MICRO_PER_MILLI,
            Unit::Microsecond => 1,
        };
    }

    /**
     * Round a microsecond value to this unit's precision boundary.
     */
    public function round(int $valueInMicro, RoundingMode $mode = RoundingMode::Nearest): int
    {
        $unit = $this->inMicroseconds();

        $precision = match ($mode) {
            RoundingMode::Floor => 0,
            RoundingMode::Nearest => intdiv($unit, 2),
            RoundingMode::Ceil => $unit - 1,
        };

        return intdiv($valueInMicro + $precision, $unit) * $unit;
    }

    public function toMicroseconds(int|float $value): int
    {
        return (int) round($this->inMicroseconds() * $value);
    }

    public function whole(int $value): int
    {
        return intdiv($value, $this->inMicroseconds());
    }

    public function remainder(int $value): int
    {
        return $value % $this->inMicroseconds();
    }

    public function divide(int $value): int|float
    {
        return $value / $this->inMicroseconds();
    }

    /**
     * Wrap a scalar value into this unit's closed-open cycle.
     */
    public function wrap(int $valueInMicro): int
    {
        $unit = $this->inMicroseconds();

        return ($valueInMicro % $unit + $unit) % $unit;
    }
}
