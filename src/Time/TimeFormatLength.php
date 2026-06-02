<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

enum TimeFormatLength
{
    case Short;
    case Medium;
    case Long;
    case Full;
}
