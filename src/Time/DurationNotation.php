<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use const STR_PAD_LEFT;

use function implode;
use function mb_rtrim;
use function mb_str_pad;
use function mb_trim;
use function preg_match;
use function throw_if;
use function throw_unless;

/**
 * Supported serialized forms for exact {@see Duration} values.
 *
 * Each notation is deliberately restricted to deterministic units. Month and year
 * components are excluded so parsing never depends on an external calendar context.
 * @author Brian Faust <brian@cline.sh>
 */
enum DurationNotation
{
    case Iso8601;
    case Compact;
    case Chrono;

    private const string REGEXP_TIMER = '@^
        (?<sign>-)?\s*
        (?<hours>\d+):
        (?<minutes>\d{1,2}):
        (?<seconds>\d{1,2})
        (\.(?<microseconds>\d+))?
    $@x';

    private const string REGEXP_COMPACT = '@^
        (?<sign>-)?\s*
        (?:(?<weeks>\d+)\s*w\s*)?
        (?:(?<days>\d+)\s*d\s*)?
        (?:(?<hours>\d+)\s*h\s*)?
        (?:(?<minutes>\d+)\s*m\s*)?
        (?:(?<seconds>\d+)\s*s\s*)?
        (?:(?<microseconds>\d+)\s*(µs|us)\s*)?
    $@x';

    private const string REGEXP_ISO8601 = '@^
        (?<sign>[+-])?
        P
        (?=.*?(?:\d+W|\d+D|T\d+H|T\d+M|T\d+(?:\.\d+)?S)) # look-ahead to restrict support for ISO8601 formats
        (?:(?<weeks>\d+)W)?
        (?:(?<days>\d+)D)?
        (?:T
            (?:(?<hours>\d+)H)?
            (?:(?<minutes>\d+)M)?
            (?:(?<seconds>\d+(?:\.\d+)?)S)?
        )?
    $@x';

    /**
     * @throws InvalidDuration
     */
    public function decode(string $data): Duration
    {
        return match ($this) {
            self::Iso8601 => self::fromIso8601($data),
            self::Chrono => self::fromChrono($data),
            self::Compact => self::fromCompact($data),
        };
    }

    /**
     * @return non-empty-string
     */
    public function encode(Duration $duration): string
    {
        return match ($this) {
            self::Iso8601 => self::toIso8601($duration),
            self::Chrono => self::toChrono($duration),
            self::Compact => self::toCompact($duration),
        };
    }

    private static function toMicroseconds(
        int $days,
        int $hours,
        int $minutes,
        int|float $seconds,
        int $microseconds,
    ): int {
        return Unit::Day->toMicroseconds($days)
            + Unit::Hour->toMicroseconds($hours)
            + Unit::Minute->toMicroseconds($minutes)
            + Unit::Second->toMicroseconds($seconds)
            + $microseconds;
    }

    /**
     * Format a duration as `[-]HH:MM:SS[.mmmmmm]`.
     *
     * @return non-empty-string
     */
    private static function toChrono(Duration $duration): string
    {
        $pad = static fn (int $value, int $length): string => mb_str_pad((string) $value, $length, '0', STR_PAD_LEFT);
        $formatted = $pad($duration->hours, 2).':'.$pad($duration->minutes, 2).':'.$pad($duration->seconds, 2);

        if (0 !== $duration->microseconds) {
            $formatted .= '.'.$pad($duration->microseconds, 6);
        }

        return -1 === $duration->sign ? '-'.$formatted : $formatted;
    }

    /**
     * Returns the ISO8601 string representation of the duration.
     *
     * - fractional values are only allowed on seconds
     * - only D, H, M and S are allowed; M represents the minutes
     * - negative marker is allowed in front of the expression
     *
     * @return non-empty-string
     */
    private static function toIso8601(Duration $duration): string
    {
        $time = '';
        $hours = $duration->hours % 24;

        if (0 !== $hours) {
            $time .= $hours.'H';
        }

        if (0 !== $duration->minutes) {
            $time .= $duration->minutes.'M';
        }

        $seconds = (string) $duration->seconds;

        if (0 !== $duration->microseconds) {
            $seconds .= '.'.mb_rtrim(mb_str_pad((string) $duration->microseconds, 6, '0', STR_PAD_LEFT), '0');
        }

        if ('0' !== $seconds) {
            $time .= $seconds.'S';
        }

        return (0 === $duration->daysCount && '' === $time)
            ? 'PT0S'
            : (-1 === $duration->sign ? '-' : '').'P'.(0 !== $duration->daysCount ? $duration->daysCount.'D' : '').('' !== $time ? 'T'.$time : '');
    }

    /**
     * Format [-]xw xd xh xm xs xµs where x is a number.
     * @return non-empty-string
     */
    private static function toCompact(Duration $duration): string
    {
        $time = [];

        if (0 !== $duration->weeksCount) {
            $time[] = $duration->weeksCount.'w';
        }

        $days = $duration->daysCount % 7;

        if (0 !== $days) {
            $time[] = $days.'d';
        }

        $hours = $duration->hours % 24;

        if (0 !== $hours) {
            $time[] = $hours.'h';
        }

        if (0 !== $duration->minutes) {
            $time[] = $duration->minutes.'m';
        }

        if (0 !== $duration->seconds) {
            $time[] = $duration->seconds.'s';
        }

        if (0 !== $duration->microseconds) {
            $time[] = $duration->microseconds.'µs';
        }

        return [] === $time ? '0s' : (-1 === $duration->sign ? '-' : '').implode('', $time);
    }

    /**
     * Parse a clock-style timer representation such as `01:02:03.000004`.
     *
     * @throws InvalidDuration
     */
    private function fromChrono(string $duration): Duration
    {
        throw_if(1 !== preg_match(self::REGEXP_TIMER, $duration, $parts), InvalidDuration::class, 'Unknown or bad format `'.$duration.'`.');
        $parts += ['hours' => '0', 'minutes' => '0', 'seconds' => '0', 'microseconds' => '0', 'sign' => ''];

        $minutes = (int) $parts['minutes'];
        $seconds = (int) $parts['seconds'];
        $microseconds = (int) $parts['microseconds'];

        if (!($minutes >= 0 && $minutes < 60)) {
            throw InvalidDuration::dueToMalformedMinute($minutes);
        }

        if (!($seconds >= 0 && $seconds < 60)) {
            throw InvalidDuration::dueToMalformedSecond($seconds);
        }

        if (!($microseconds >= 0 && $microseconds < 1_000_000)) {
            throw InvalidDuration::dueToMalformedMicrosecond($microseconds);
        }

        /** @var non-negative-int $microseconds */
        $microseconds = self::toMicroseconds(
            days: 0,
            hours: (int) $parts['hours'],
            minutes: $minutes,
            seconds: $seconds,
            microseconds: $microseconds,
        );

        $duration = Duration::of(microseconds: $microseconds);

        return '-' === $parts['sign'] ? $duration->negated() : $duration;
    }

    /**
     * Parse a terse unit-suffixed duration such as `1w 2d 3h 4m`.
     *
     * @throws InvalidDuration
     */
    private function fromCompact(string $data): Duration
    {
        $data = mb_trim($data);

        throw_unless('' !== $data && 1 === preg_match(self::REGEXP_COMPACT, $data, $parts), InvalidDuration::class, 'Unknown or bad format `'.$data.'`.');
        $parts += ['weeks' => '0', 'days' => '0', 'hours' => '0', 'minutes' => '0', 'seconds' => '0', 'microseconds' => '0', 'sign' => ''];

        /** @var non-negative-int $microseconds */
        $microseconds = self::toMicroseconds(
            days: (((int) $parts['weeks'] * 7) + (int) $parts['days']),
            hours: (int) $parts['hours'],
            minutes: (int) $parts['minutes'],
            seconds: (int) $parts['seconds'],
            microseconds: (int) $parts['microseconds'],
        );

        $duration = Duration::of(microseconds: $microseconds);

        return '-' === $parts['sign'] ? $duration->negated() : $duration;
    }

    /**
     * Parse a constrained ISO-8601 duration into an exact scalar duration.
     *
     * Because this package models deterministic elapsed time rather than
     * calendar-relative periods, the following restrictions apply:
     *
     * - only W, D, H, S are allowed
     * - Y is rejected
     * - M is only allowed in the time section (PT30M) to represents minutes
     * - fractional values are only allowed on seconds
     * - at least one unit must exist
     * - negative marker is allowed in front of the expression
     *
     * @throws InvalidDuration
     */
    private function fromIso8601(string $data): Duration
    {
        if (1 !== preg_match(self::REGEXP_ISO8601, $data, $parts)) {
            throw InvalidDuration::dueToMalformedIso8601($data);
        }

        $parts += ['weeks' => '0', 'days' => '0', 'hours' => '0', 'minutes' => '0', 'seconds' => '0', 'sign' => ''];

        /** @var non-negative-int $microseconds */
        $microseconds = self::toMicroseconds(
            days: (((int) $parts['weeks'] * 7) + (int) $parts['days']),
            hours: (int) $parts['hours'],
            minutes: (int) $parts['minutes'],
            seconds: (float) $parts['seconds'],
            microseconds: 0,
        );

        $duration = Duration::of(microseconds: $microseconds);

        return '-' === $parts['sign'] ? $duration->negated() : $duration;
    }
}
