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

use InvalidArgumentException;
use Throwable;

/**
 * Exception thrown by the Sequence class.
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since   4.1.0
 */
final class InaccessibleInterval extends InvalidArgumentException implements IntervalError
{
    private function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function dueToInvalidIndex(int $offset): self
    {
        return new self('`'.$offset.'` is an invalid offset in the '.Sequence::class.' object.');
    }
}
