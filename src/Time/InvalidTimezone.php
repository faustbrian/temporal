<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use DateTimeZone;
use Throwable;

/**
 * Thrown when a supplied timezone cannot be resolved to a supported IANA identifier.
 */
final class InvalidTimezone extends TimeException
{
    /**
     * Create an exception for an unsupported timezone identifier.
     */
    public static function unsupportedIdentifier(?Throwable $previous = null): self
    {
        return new self(
            'Timezone must be a valid IANA Timezone Name supported by '.DateTimeZone::class,
            previous: $previous,
        );
    }
}
