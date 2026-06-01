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

namespace Cline\Temporal\Period\Chart;

use function mb_strtolower;
use function mb_strtoupper;

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum LetterCase
{
    case Upper;
    case Lower;

    public function isUpper(): bool
    {
        return self::Upper === $this;
    }

    public function convert(string $str): string
    {
        return match ($this) {
            self::Upper => mb_strtoupper($str),
            self::Lower => mb_strtolower($str),
        };
    }
}
