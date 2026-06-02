<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use Throwable;

final class UnableToFormatLocaleTime extends TimeException
{
    public static function forLocale(string $locale, ?Throwable $previous = null): self
    {
        return new self(
            'Unable to convert to locale "'.$locale.'" the current time; Please verify your locale.',
            previous: $previous,
        );
    }
}
