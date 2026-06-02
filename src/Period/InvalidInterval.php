<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Period;

use DatePeriod;
use InvalidArgumentException;
use Throwable;

use function sprintf;

/**
 * Thrown when a {@see Period} cannot be created from the supplied endpoints or notation.
 *
 * The named constructors preserve the specific validation rule that failed so
 * callers can distinguish format problems from endpoint ordering or runtime
 * feature constraints.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidInterval extends InvalidArgumentException implements IntervalError
{
    private function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for an end date that precedes the start date.
     */
    public static function dueToEndPointMismatch(): self
    {
        return new self('The ending endpoint must be greater or equal to the starting endpoint.');
    }

    /**
     * Create an exception for a date string that does not satisfy the expected format.
     */
    public static function dueToInvalidDateFormat(string $format, string $date, ?Throwable $throwable = null): self
    {
        return new self('The date notation `'.$date.'` is incompatible with the date format `'.$format.'`.', 0, $throwable);
    }

    /**
     * Create an exception for an ISO-8601 relative endpoint that cannot be resolved.
     */
    public static function dueToInvalidRelativeDateFormat(string $endDate, string $startDate): self
    {
        return new self('The end date notation `'.$endDate.'` is incompatible with the start date notation `'.$startDate.'`.');
    }

    /**
     * Create an exception for a native {@see DatePeriod} that lacks an end date.
     */
    public static function dueToInvalidDatePeriod(): self
    {
        return new self('The '.DatePeriod::class.' should contain an end date to instantiate a '.Period::class.' class.');
    }

    /**
     * Create an exception for a method that depends on a newer PHP runtime feature.
     */
    public static function dueToUnsupportedVersion(string $method, string $phpVersion): self
    {
        return new self(sprintf('The `%s` is available starting with `%s`.', $method, $phpVersion));
    }

    /**
     * Create an exception for notation strings outside the supported grammar.
     */
    public static function dueToUnknownNotation(string $expectedFormat, string $notation): self
    {
        return new self('Unknown or unsupported interval notation `'.$notation.'` for `'.$expectedFormat.'`.');
    }
}
