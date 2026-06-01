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
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidInterval extends TimeException
{
    public static function dueToMalformedNotation(string $notation, IntervalNotation $source, ?Throwable $previous = null): self
    {
        return new self('"'.$notation.'" is an invalid or unsupported '.$source->name.' notation.', previous: $previous);
    }
}
