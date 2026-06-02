<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use const FILTER_VALIDATE_FLOAT;
use const FILTER_VALIDATE_INT;

use function filter_var;
use function get_debug_type;
use function is_int;
use function is_string;
use function mb_trim;
use function number_format;
use function preg_match;
use function str_starts_with;

enum IntervalFormat
{
    case Bourbaki;
    case Iso80000;
    case Iso8601StartDuration;
    case Iso8601DurationEnd;
    case Iso8601StartEnd;
    case Iso8601;

    private const string REGEXP_ISO80000 = '/^\[(?<start>[^,)]*),(?<end>[^,)]*)\)$/';

    private const string REGEXP_BOURBAKI = '/^\[(?<start>[^,\[]*),(?<end>[^,\[]*)\[$/';

    private const string REGEXP_ISO8601 = '/^(?<start>[^\/]+)\/(?<end>[^\/]+)$/';

    /**
     * @see https://en.wikipedia.org/wiki/Interval_(mathematics)#Notations_for_intervals
     * @see https://en.wikipedia.org/wiki/ISO_31-11
     *
     * @throws InvalidTime
     *
     * @return non-empty-string
     */
    public function encode(Interval $interval, ?Unit $unit = null): string
    {
        $start = $this->formatTime($interval->start, $unit);
        $end = $this->formatTime($interval->end, $unit);

        return match ($this) {
            self::Iso8601,
            self::Iso8601StartDuration => $start.'/'.$interval->duration->format(),
            self::Iso8601DurationEnd => $interval->duration->format().'/'.$end,
            self::Iso8601StartEnd => $start.'/'.$end,
            self::Iso80000 => '['.$start.','.$end.')',
            self::Bourbaki => '['.$start.','.$end.'[',
        };
    }

    /**
     * @throws InvalidDuration|InvalidInterval|InvalidTime
     */
    public function decode(string $data, ?Unit $unit = null): Interval
    {
        $trimmedData = mb_trim($data);
        $pattern = match ($this) {
            self::Bourbaki => self::REGEXP_BOURBAKI,
            self::Iso80000 => self::REGEXP_ISO80000,
            default => self::REGEXP_ISO8601,
        };

        if (1 !== preg_match($pattern, $trimmedData, $found)) {
            throw InvalidInterval::dueToMalformedFormat($data, $this);
        }

        $start = mb_trim($found['start']);
        $end = mb_trim($found['end']);

        if ('' === $start && '' === $end) {
            throw InvalidInterval::dueToMalformedFormat($data, $this);
        }

        return match ($this) {
            self::Bourbaki,
            self::Iso80000 => $this->parseMathInterval($start, $end, $data, $unit),
            default => $this->parseIso8601Interval($start, $end, $data),
        };
    }

    private function formatTime(Time $time, ?Unit $unit): string
    {
        if (!$unit instanceof Unit || !$this->supportsUnit()) {
            return $time->format();
        }

        $value = $time->toOffset($unit);

        return is_int($value)
            ? (string) $value
            : number_format(num: $value, decimals: 6, decimal_separator: '.', thousands_separator: '');
    }

    /**
     * @throws InvalidDuration|InvalidInterval|InvalidTime
     */
    private function parseMathInterval(string $start, string $end, string $data, ?Unit $unit): Interval
    {
        $start = $this->normalizeMathIntervalValue($start);
        $end = $this->normalizeMathIntervalValue($end);

        $start ??= is_string($end) ? '00:00' : 0;
        $end ??= is_string($start) ? '00:00' : 0;

        if (!(get_debug_type($start) === get_debug_type($end) || is_string($start) || $unit instanceof Unit)) {
            throw InvalidInterval::dueToMalformedFormat($data, $this);
        }

        return Interval::between(
            $this->createTime($start, $unit, $data),
            $this->createTime($end, $unit, $data),
        );
    }

    private function normalizeMathIntervalValue(string $value): int|float|string|null
    {
        if ('' === $value) {
            return null;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT);

        if (false !== $intValue) {
            return $intValue;
        }

        $floatValue = filter_var($value, FILTER_VALIDATE_FLOAT);

        if (false !== $floatValue) {
            return $floatValue;
        }

        return $value;
    }

    /**
     * @throws InvalidInterval|InvalidTime
     */
    private function createTime(int|string|float $value, ?Unit $unit, string $data): Time
    {
        return match (true) {
            $unit instanceof Unit && !is_string($value) => Time::fromOffset($value, $unit),
            is_string($value) => TimeFormat::Iso8601->decode($value),
            default => throw InvalidInterval::dueToMalformedFormat($data, $this),
        };
    }

    /**
     * @throws InvalidDuration|InvalidInterval|InvalidTime
     */
    private function parseIso8601Interval(string $start, string $end, string $format): Interval
    {
        $isDurationFormat = static fn (string $format): bool => str_starts_with($format, 'P') || str_starts_with($format, '-P');

        return match (true) {
            $this->supportsDurationEnd() && $isDurationFormat($start) => Interval::until(
                end: TimeFormat::Iso8601->decode($end),
                duration: DurationFormat::Iso8601->decode($start),
            ),
            $this->supportsStartDuration() && $isDurationFormat($end) => Interval::since(
                start: TimeFormat::Iso8601->decode($start),
                duration: DurationFormat::Iso8601->decode($end),
            ),
            $this->supportsStartEnd() => Interval::between(
                start: TimeFormat::Iso8601->decode($start),
                end: TimeFormat::Iso8601->decode($end),
            ),
            default => throw InvalidInterval::dueToMalformedFormat($format, $this),
        };
    }

    private function supportsStartDuration(): bool
    {
        return match ($this) {
            self::Iso8601,
            self::Iso8601StartDuration => true,
            default => false,
        };
    }

    private function supportsDurationEnd(): bool
    {
        return match ($this) {
            self::Iso8601,
            self::Iso8601DurationEnd => true,
            default => false,
        };
    }

    private function supportsStartEnd(): bool
    {
        return match ($this) {
            self::Iso8601,
            self::Iso8601StartEnd => true,
            default => false,
        };
    }

    private function supportsUnit(): bool
    {
        return match ($this) {
            self::Bourbaki,
            self::Iso80000 => true,
            default => false,
        };
    }
}
