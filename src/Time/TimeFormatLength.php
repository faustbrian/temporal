<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

/**
 * ICU formatting verbosity presets used by {@see Time::toLocaleString()}.
 *
 * The cases map directly to the native IntlDateFormatter constants and describe
 * how much localized detail should be emitted for a rendered time-of-day.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum TimeFormatLength
{
    case Short;
    case Medium;
    case Long;
    case Full;
}
