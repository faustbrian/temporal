<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use function mb_strpos;
use function sprintf;
use function str_contains;

/**
 * Thrown when a duration cannot be represented or parsed deterministically.
 *
 * This includes scalar overflow and notation input that would require
 * calendar-relative units such as months or years.
 */
final class InvalidDuration extends TimeException
{
    /**
     * Create an exception for scalar values outside the supported microsecond range.
     */
    public static function dueToOverflow(): self
    {
        return new self('The duration exceeds the supported range.');
    }

    /**
     * Create an exception for malformed or unsupported ISO-8601 duration input.
     */
    public static function dueToMalformedIso8601(string $format): self
    {
        $containsUnsupportedUnits = str_contains($format, 'Y') || self::containsMonthComponent($format);

        $message = $containsUnsupportedUnits
            ? sprintf('The submitted duration `%s` contains unsupported ISO 8601 duration components.', $format)
            : sprintf('The submitted duration `%s` is not a valid ISO 8601 duration.', $format);

        return new self($message);
    }

    /**
     * Create an exception for minute fields outside the accepted `0..59` range.
     */
    public static function dueToMalformedMinute(int $minute): self
    {
        return new self(sprintf('Minute must be between 0 and 59, got %d.', $minute));
    }

    /**
     * Create an exception for second fields outside the accepted `0..59` range.
     */
    public static function dueToMalformedSecond(int $second): self
    {
        return new self(sprintf('Second must be between 0 and 59, got %d.', $second));
    }

    /**
     * Create an exception for fractional precision above six decimal digits.
     */
    public static function dueToMalformedMicrosecond(int $microsecond): self
    {
        return new self(sprintf('Microsecond must be between 0 and 999999, got %d.', $microsecond));
    }

    /**
     * Detect whether an ISO-8601 string uses the calendar month component.
     */
    private static function containsMonthComponent(string $data): bool
    {
        $monthPosition = mb_strpos($data, 'M');

        if (false === $monthPosition) {
            return false;
        }

        $timePosition = mb_strpos($data, 'T');

        return false === $timePosition || $monthPosition < $timePosition;
    }
}
