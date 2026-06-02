<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use function sprintf;

/**
 * Thrown when a time-of-day value cannot satisfy the package's clock invariants.
 *
 * The named constructors describe which component violated the accepted range so
 * callers can surface precise validation feedback.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidTime extends TimeException
{
    /**
     * Create an exception for an hour outside the inclusive `0..23` range.
     */
    public static function dueToMalformedHour(int $hour): self
    {
        return new self(sprintf('Hour must be between 0 and 23, got %d.', $hour));
    }

    /**
     * Create an exception for a minute outside the inclusive `0..59` range.
     */
    public static function dueToMalformedMinute(int $minute): self
    {
        return new self(sprintf('Minute must be between 0 and 59, got %d.', $minute));
    }

    /**
     * Create an exception for a second outside the inclusive `0..59` range.
     */
    public static function dueToMalformedSecond(int $second): self
    {
        return new self(sprintf('Second must be between 0 and 59, got %d.', $second));
    }

    /**
     * Create an exception for a microsecond outside the inclusive `0..999999` range.
     */
    public static function dueToMalformedMicrosecond(int $microsecond): self
    {
        return new self(sprintf('Microsecond must be between 0 and 999999, got %d.', $microsecond));
    }
}
