<?php

declare(strict_types=1);

namespace Cline\Temporal\Time;

use Throwable;

class InvalidInterval extends TimeException
{
    public static function dueToMalformedNotation(string $format, IntervalNotation $source, ?Throwable $previous = null): self
    {
        return new self('"'.$format.'" is an invalid or unsupported '.$source->name.' format.', previous: $previous);
    }
}
