<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum RoundingMode
{
    case Floor;
    case Round;
    case Ceil;
}
