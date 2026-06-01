<?php declare(strict_types=1);

/**
 * League.Period (https://period.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
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

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class PeriodBoundsTest extends PeriodTestCase
{
    #[DataProvider('providePeriodBoundsCases')]
    public function test_period_bounds(
        Period $interval,
        Bounds $rangeType,
        bool $startIncluded,
        bool $startExcluded,
        bool $endIncluded,
        bool $endExcluded,
    ): void {
        $this->assertSame($interval->bounds, $rangeType);
        $this->assertSame($startIncluded, $interval->bounds->isStartIncluded());
        $this->assertSame($startExcluded, !$interval->bounds->isStartIncluded());
        $this->assertSame($endIncluded, $interval->bounds->isEndIncluded());
        $this->assertSame($endExcluded, !$interval->bounds->isEndIncluded());
    }

    /**
     * @return \Iterator<string, array{interval: Period, rangeType: Bounds, startIncluded: bool, startExcluded: bool, endIncluded: bool, endExcluded: bool}>
     */
    public static function providePeriodBoundsCases(): \Iterator
    {
        yield 'left open right close' => [
            'interval' => Period::fromDay(2_012, 8, 12),
            'rangeType' => Bounds::IncludeStartExcludeEnd,
            'startIncluded' => true,
            'startExcluded' => false,
            'endIncluded' => false,
            'endExcluded' => true,
        ];
        yield 'left close right open' => [
            'interval' => Period::around('2012-08-12', '1 HOUR', Bounds::ExcludeStartIncludeEnd),
            'rangeType' => Bounds::ExcludeStartIncludeEnd,
            'startIncluded' => false,
            'startExcluded' => true,
            'endIncluded' => true,
            'endExcluded' => false,
        ];
        yield 'left open right open' => [
            'interval' => Period::after('2012-08-12', '1 DAY', Bounds::IncludeAll),
            'rangeType' => Bounds::IncludeAll,
            'startIncluded' => true,
            'startExcluded' => false,
            'endIncluded' => true,
            'endExcluded' => false,
        ];
        yield 'left close right close' => [
            'interval' => Period::before('2012-08-12', '1 WEEK', Bounds::ExcludeAll),
            'rangeType' => Bounds::ExcludeAll,
            'startIncluded' => false,
            'startExcluded' => true,
            'endIncluded' => false,
            'endExcluded' => true,
        ];
    }

    public function test_period_bounded_by(): void
    {
        $interval = Period::fromDate('2014-01-13', '2014-01-20');
        $altInterval = $interval->boundedBy(Bounds::ExcludeAll);

        $this->assertEquals($altInterval->dateInterval(), $interval->dateInterval());
        $this->assertNotSame($altInterval->bounds, $interval->bounds);
        $this->assertSame($interval, $interval->boundedBy(Bounds::IncludeStartExcludeEnd));
    }
}
