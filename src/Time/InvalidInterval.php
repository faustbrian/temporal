<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use Throwable;

/**
 * Thrown when an interval string cannot be parsed by the selected notation.
 */
final class InvalidInterval extends TimeException
{
    /**
     * Create an exception that preserves the notation family used during parsing.
     */
    public static function dueToMalformedNotation(string $format, IntervalNotation $source, ?Throwable $previous = null): self
    {
        return new self('"'.$format.'" is an invalid or unsupported '.$source->name.' format.', previous: $previous);
    }
}
