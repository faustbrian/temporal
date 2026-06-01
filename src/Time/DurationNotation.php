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
 * @author Brian Faust <brian@cline.sh>
 */
enum DurationNotation
{
    case Iso8601;
    case Compact;
    case Chrono;

    private const string REGEXP_CHRONO = '@^
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
    public function decode(string $notation): Duration
    {
        return match ($this) {
            self::Iso8601 => self::fromIso8601($notation),
            self::Chrono => self::fromChrono($notation),
            self::Compact => self::fromCompact($notation),
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
     * Returns the string representation of the Duration.
     *
     * The following format is used [-]HH:MM:SS[.mmmmmm]
     * the fraction and the signed are only display if
     * they duration is negative and/or the sub seconds
     * fraction is different from 0
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

        return [] === $time ? '0s' : (-1 === $duration->sign ? '-' : '').implode(' ', $time);
    }

    /**
     * Creates a new instance from a timer string representation.
     *
     * @throws InvalidDuration
     */
    private function fromChrono(string $duration): Duration
    {
        throw_if(1 !== preg_match(self::REGEXP_CHRONO, $duration, $parts), InvalidDuration::class, 'Unknown or bad format `'.$duration.'`.');
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

        $microseconds = self::toMicroseconds(
            days: 0,
            hours: (int) $parts['hours'],
            minutes: $minutes,
            seconds: $seconds,
            microseconds: $microseconds,
        );

        return Duration::of(microseconds: '-' === $parts['sign'] ? -$microseconds : $microseconds);
    }

    /**
     * Creates a new instance from a timer string representation.
     *
     * @throws InvalidDuration
     */
    private function fromCompact(string $notation): Duration
    {
        $notation = mb_trim($notation);

        throw_unless('' !== $notation && 1 === preg_match(self::REGEXP_COMPACT, $notation, $parts), InvalidDuration::class, 'Unknown or bad format `'.$notation.'`.');
        $parts += ['weeks' => '0', 'days' => '0', 'hours' => '0', 'minutes' => '0', 'seconds' => '0', 'microseconds' => '0', 'sign' => ''];

        $microseconds = self::toMicroseconds(
            days: (((int) $parts['weeks'] * 7) + (int) $parts['days']),
            hours: (int) $parts['hours'],
            minutes: (int) $parts['minutes'],
            seconds: (int) $parts['seconds'],
            microseconds: (int) $parts['microseconds'],
        );

        return Duration::of(microseconds: '-' === $parts['sign'] ? -$microseconds : $microseconds);
    }

    /**
     * Parses and returns a new instance from ISO8601 string representation.
     *  Because the duration does not handle in a deterministic way month and year components
     * the following restrictions apply:
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
    private function fromIso8601(string $notation): Duration
    {
        if (1 !== preg_match(self::REGEXP_ISO8601, $notation, $parts)) {
            throw InvalidDuration::dueToMalformedIso8601($notation);
        }

        $parts += ['weeks' => '0', 'days' => '0', 'hours' => '0', 'minutes' => '0', 'seconds' => '0', 'sign' => ''];

        $microseconds = self::toMicroseconds(
            days: (((int) $parts['weeks'] * 7) + (int) $parts['days']),
            hours: (int) $parts['hours'],
            minutes: (int) $parts['minutes'],
            seconds: (float) $parts['seconds'],
            microseconds: 0,
        );

        return Duration::of(microseconds: '-' === $parts['sign'] ? -$microseconds : $microseconds);
    }
}
