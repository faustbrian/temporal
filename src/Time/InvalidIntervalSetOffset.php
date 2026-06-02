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
 * Thrown when interval-set access targets an offset that does not exist.
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidIntervalSetOffset extends TimeException
{
    /**
     * Create an exception naming the invalid offset and target collection type.
     */
    public static function forOffset(int $offset): self
    {
        return new self(sprintf('Invalid offset (%d) given to %s.', $offset, IntervalSet::class));
    }
}
