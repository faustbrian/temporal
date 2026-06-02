<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

/**
 * Rounding strategies shared by time and duration precision helpers.
 */
enum RoundingMode
{
    case Floor;
    case Nearest;
    case Ceil;
}
