<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

final class UnsupportedLocaleFormatting extends TimeException
{
    public static function dueToMissingIntlSupport(): self
    {
        return new self('Support for time locale formatting requires the `intl` extension for best performance or run "composer require symfony/polyfill-intl-icu" to install a polyfill.');
    }
}
