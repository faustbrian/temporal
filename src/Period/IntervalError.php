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

namespace Cline\Temporal\Period;

use Throwable;

/**
 * Marker interface implemented by period-related failures.
 *
 * Catch this interface when callers want to handle all semantic period errors
 * without also catching unrelated runtime exceptions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface IntervalError extends Throwable {}
