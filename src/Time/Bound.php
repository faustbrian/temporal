<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

/**
 * Identifies which side of an {@see Interval} an operation should anchor to.
 *
 * Several interval helpers can either preserve or move the start or end bound,
 * and this enum keeps that choice explicit in the API.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum Bound
{
    case Start;
    case End;
}
