<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use Throwable;

final class InvalidInterval extends TimeException
{
    public static function dueToMalformedFormat(string $format, IntervalFormat $source, ?Throwable $previous = null): self
    {
        return new self('"'.$format.'" is an invalid or unsupported '.$source->name.' format.', previous: $previous);
    }
}
