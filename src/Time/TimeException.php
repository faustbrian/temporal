<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use Exception;

/**
 * Base exception for failures raised by the time subsystem.
 *
 * Specialized exceptions refine this type for parse errors, invalid ranges, and
 * locale or timezone failures, but consumers can catch this class when they only
 * care that an operation in the time API failed.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class TimeException extends Exception {}
