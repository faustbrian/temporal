<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

/**
 * Classification of how an {@see Interval} traverses the 24-hour cycle.
 *
 * The value is derived from the normalized endpoints plus the underlying duration,
 * and drives behaviors such as complement calculation and stepping.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum IntervalType
{
    case Linear;
    case Overflow;
    case Circular;
    case Collapsed;
}
