<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Period;

use RuntimeException;
use Throwable;

/**
 * Thrown when a range operation is valid in principle but impossible for the
 * supplied periods because their relationship does not satisfy the method's
 * preconditions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnprocessableInterval extends RuntimeException implements IntervalError
{
    private function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for operations that require overlapping periods.
     */
    public static function dueToMissingOverlaps(): self
    {
        return new self('Both '.Period::class.' objects must overlaps.');
    }

    /**
     * Create an exception for operations that require at least one gap between periods.
     */
    public static function dueToMissingGaps(): self
    {
        return new self('Both '.Period::class.' objects must have at least one gap.');
    }
}
