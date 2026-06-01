<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Period;

use PHPUnit\Framework\TestCase;

use function date_default_timezone_get;
use function date_default_timezone_set;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class PeriodTestCase extends TestCase
{
    protected string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }
}
