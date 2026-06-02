<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use function sprintf;

final class InvalidTime extends TimeException
{
    public static function dueToMalformedHour(int $hour): self
    {
        return new self(sprintf('Hour must be between 0 and 23, got %d.', $hour));
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
}
