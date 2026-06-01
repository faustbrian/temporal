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
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidDuration extends TimeException
{
    public static function dueToOverflow(): self
    {
        return new self('The duration exceeds the supported range.');
    }

    public static function dueToMalformedIso8601(string $notation): self
    {
        $containsUnsupportedUnits = str_contains($notation, 'Y') || self::containsMonthComponent($notation);

        $message = $containsUnsupportedUnits
            ? sprintf('The submitted duration `%s` contains unsupported ISO 8601 duration components.', $notation)
            : sprintf('The submitted duration `%s` is not a valid ISO 8601 duration.', $notation);

        return new self($message);
    }

    public static function dueToMalformedMinute(int $minute): self
    {
        return new self(sprintf('Minute must be between 0 and 59, got %d.', $minute));
    }

    public static function dueToMalformedSecond(int $second): self
    {
        return new self(sprintf('Second must be between 0 and 59, got %d.', $second));
    }

    public static function dueToMalformedMicrosecond(int $microsecond): self
    {
        return new self(sprintf('Microsecond must be between 0 and 999999, got %d.', $microsecond));
    }

    private static function containsMonthComponent(string $value): bool
    {
        $monthPosition = mb_strpos($value, 'M');

        if (false === $monthPosition) {
            return false;
        }

        $timePosition = mb_strpos($value, 'T');

        return false === $timePosition || $monthPosition < $timePosition;
    }
}
