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

use LogicException;

/**
 * Thrown when a requested period capability is intentionally unavailable.
 *
 * This is typically used as a guard around optional framework or runtime features
 * that the package cannot emulate safely.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MissingFeature extends LogicException implements IntervalError {}
