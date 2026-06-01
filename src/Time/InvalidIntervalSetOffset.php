<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use function sprintf;

final class InvalidIntervalSetOffset extends TimeException
{
    public static function forOffset(int $offset): self
    {
        return new self(sprintf('Invalid offset (%d) given to %s.', $offset, IntervalSet::class));
    }
}
