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

use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeImmutable;
use Generator;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;

use function array_map;
use function array_reverse;
use function date_default_timezone_set;
use function is_int;
use function is_string;
use function iterator_count;
use function iterator_to_array;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class PeriodDurationTest extends PeriodTestCase
{
    public function test_get_date_interval(): void
    {
        $this->assertSame(1, Period::fromDate('2012-02-01', '2012-02-02')->dateInterval()->days);
    }

    public function test_get_timestamp_interval(): void
    {
        $this->assertSame(86_400, Period::fromDate('2012-02-01', '2012-02-02')->timeDuration());
    }

    #[DataProvider('provideGet_date_periodCases')]
    public function test_get_date_period(DateInterval|int|string $duration, Bounds $bounds, int $count): void
    {
        if (is_string($duration)) {
            $duration = DateInterval::createFromDateString($duration);
        } elseif (!$duration instanceof DateInterval) {
            $duration = Duration::fromSeconds($duration);
        }

        $period = Period::fromDate('2012-01-12', '2012-01-13', $bounds);
        $this->assertCount($count, iterator_to_array($period->rangeForward($duration)));
    }

    /**
     * @return Iterator<string, array{(DateInterval|int|string), Bounds, int}>
     */
    public static function provideGet_date_periodCases(): iterable
    {
        yield 'useDateInterval' => [new DateInterval('PT1H'), Bounds::IncludeStartExcludeEnd, 24];

        yield 'useString' => ['2 HOUR', Bounds::IncludeStartExcludeEnd, 12];

        yield 'useInt' => [9_600, Bounds::IncludeStartExcludeEnd, 9];

        yield 'exclude start date use DateInterval' => [new DateInterval('PT1H'), Bounds::ExcludeAll, 23];

        yield 'exclude start date use String' => ['2 HOUR', Bounds::ExcludeAll, 11];

        yield 'exclude start date use Int' => [9_600, Bounds::ExcludeAll, 8];

        yield 'exclude start date use Float' => [14_400, Bounds::ExcludeAll, 5];
    }

    #[DataProvider('provideGet_date_period_backwardsCases')]
    public function test_get_date_period_backwards(DateInterval|int|string $duration, Bounds $bounds, int $count): void
    {
        if (is_int($duration)) {
            $duration = Duration::fromSeconds($duration);
        }

        $period = Period::fromDate('2012-01-12', '2012-01-13', $bounds);

        $this->assertCount($count, iterator_to_array($period->rangeBackwards($duration)));
    }

    /**
     * @return Iterator<string, array{(DateInterval|int|string), Bounds, int}>
     */
    public static function provideGet_date_period_backwardsCases(): iterable
    {
        yield 'useDateInterval' => [new DateInterval('PT1H'), Bounds::ExcludeStartIncludeEnd, 24];

        yield 'useString' => ['2 HOUR', Bounds::ExcludeStartIncludeEnd, 12];

        yield 'useInt' => [9_600, Bounds::ExcludeStartIncludeEnd, 9];

        yield 'exclude start date useDateInterval' => [new DateInterval('PT1H'), Bounds::ExcludeAll, 23];

        yield 'exclude start date useString' => ['2 HOUR', Bounds::ExcludeAll, 11];

        yield 'exclude start date useInt' => [9_600, Bounds::ExcludeAll, 8];

        yield 'exclude start date useFloat' => [14_400, Bounds::ExcludeAll, 5];
    }

    /**
     * @param array<DateTimeImmutable> $range
     */
    #[DataProvider('provideRangedData')]
    public function test_range_forwards(Period $period, DateInterval $dateInterval, int $count, array $range): void
    {
        $result = iterator_to_array($period->rangeForward($dateInterval));

        $this->assertEquals($range, $result);
        $this->assertCount($count, $result);
    }

    /**
     * @param array<DateTimeImmutable> $range
     */
    #[DataProvider('provideRangedData')]
    public function test_range_backwards(Period $period, DateInterval $dateInterval, int $count, array $range): void
    {
        $result = iterator_to_array($period->rangeBackwards($dateInterval));

        $this->assertEquals($range, array_reverse($result));
        $this->assertCount($count, $result);
    }

    /**
     * @return iterable<string, array{period:Period, dateInterval:DateInterval, count:int}>
     */
    public static function provideRangedData(): iterable
    {
        $period = Period::fromDate('2012-01-12 00:00:00', '2012-01-12 01:00:00');
        $dateInterval = new DateInterval('PT10M');

        yield 'bounds include start exclude end' => [
            'period' => $period->boundedBy(Bounds::IncludeStartExcludeEnd),
            'dateInterval' => $dateInterval,
            'count' => 6,
            'range' => [
                CarbonImmutable::parse('2012-01-12 00:00:00'),
                CarbonImmutable::parse('2012-01-12 00:10:00'),
                CarbonImmutable::parse('2012-01-12 00:20:00'),
                CarbonImmutable::parse('2012-01-12 00:30:00'),
                CarbonImmutable::parse('2012-01-12 00:40:00'),
                CarbonImmutable::parse('2012-01-12 00:50:00'),
            ],
        ];

        yield 'bounds exclude start include end' => [
            'period' => $period->boundedBy(Bounds::ExcludeStartIncludeEnd),
            'dateInterval' => $dateInterval,
            'count' => 6,
            'range' => [
                CarbonImmutable::parse('2012-01-12 00:10:00'),
                CarbonImmutable::parse('2012-01-12 00:20:00'),
                CarbonImmutable::parse('2012-01-12 00:30:00'),
                CarbonImmutable::parse('2012-01-12 00:40:00'),
                CarbonImmutable::parse('2012-01-12 00:50:00'),
                CarbonImmutable::parse('2012-01-12 01:00:00'),
            ],
        ];

        yield 'bounds include all' => [
            'period' => $period->boundedBy(Bounds::IncludeAll),
            'dateInterval' => $dateInterval,
            'count' => 7,
            'range' => [
                CarbonImmutable::parse('2012-01-12 00:00:00'),
                CarbonImmutable::parse('2012-01-12 00:10:00'),
                CarbonImmutable::parse('2012-01-12 00:20:00'),
                CarbonImmutable::parse('2012-01-12 00:30:00'),
                CarbonImmutable::parse('2012-01-12 00:40:00'),
                CarbonImmutable::parse('2012-01-12 00:50:00'),
                CarbonImmutable::parse('2012-01-12 01:00:00'),
            ],
        ];

        yield 'bounds exclude all' => [
            'period' => $period->boundedBy(Bounds::ExcludeAll),
            'dateInterval' => $dateInterval,
            'count' => 5,
            'range' => [
                CarbonImmutable::parse('2012-01-12 00:10:00'),
                CarbonImmutable::parse('2012-01-12 00:20:00'),
                CarbonImmutable::parse('2012-01-12 00:30:00'),
                CarbonImmutable::parse('2012-01-12 00:40:00'),
                CarbonImmutable::parse('2012-01-12 00:50:00'),
            ],
        ];
    }

    #[DataProvider('provideDuration_compareCases')]
    public function test_duration_compare(Period $interval1, Period $interval2, int $expected): void
    {
        $this->assertSame($expected, $interval1->durationCompare($interval2));
    }

    /**
     * @return Iterator<string, array{Period, Period, int}>
     */
    public static function provideDuration_compareCases(): iterable
    {
        yield 'duration less than' => [
            Period::fromDate('2012-01-01', '2012-01-15'),
            Period::fromDate('2013-01-01', '2013-01-16'),
            -1,
        ];

        yield 'duration greater than' => [
            Period::fromDate('2012-01-01', '2012-01-15'),
            Period::fromDate('2012-01-01', '2012-01-07'),
            1,
        ];

        yield 'duration equals with microsecond' => [
            Period::fromDate('2012-01-01 00:00:00', '2012-01-03 00:00:00.123456'),
            Period::fromDate('2012-02-02 00:00:00', '2012-02-04 00:00:00.123456'),
            0,
        ];

        yield 'duration with DST' => [
            Period::fromDate('2014-03-01', '2014-04-01'),
            Period::fromDate('2014-03-01', '2014-04-01'),
            0,
        ];
    }

    public function test_duration_compare_inner_methods(): void
    {
        $period1 = Period::fromDate('2012-01-01', '2012-01-07');
        $period2 = Period::fromDate('2013-01-01', '2013-02-01');

        $this->assertTrue($period1->durationLessThan($period2));
        $this->assertTrue($period1->durationLessThanOrEquals($period2));

        $period3 = Period::fromDate('2012-01-01', '2012-02-01');
        $period4 = Period::fromDate('2012-01-01', '2012-01-07');

        $this->assertTrue($period3->durationGreaterThan($period4));

        $period5 = Period::fromDate('2012-01-01 00:00:00', '2012-01-03 00:00:00');
        $period6 = Period::fromDate('2012-02-02 00:00:00', '2012-02-04 00:00:00');

        $this->assertTrue($period5->durationEquals($period6));
        $this->assertTrue($period5->durationGreaterThanOrEquals($period6));
        $this->assertTrue($period5->durationLessThanOrEquals($period6));
    }

    public function test_date_interval_diff(): void
    {
        $orig = Period::after('2012-01-01', '1 HOUR');
        $alt = Period::after('2012-01-01', '2 HOUR');

        $this->assertSame(1, $orig->dateIntervalDiff($alt)->h);
        $this->assertSame(0, $orig->dateIntervalDiff($alt)->days);
    }

    public function test_timestamp_interval_diff(): void
    {
        $orig = Period::after('2012-01-01', '1 HOUR');
        $alt = Period::after('2012-01-01', '2 HOUR');

        $this->assertSame(-3_600, $orig->timeDurationDiff($alt));
    }

    public function test_date_interval_diff_position_irrelevant(): void
    {
        $orig = Period::after('2012-01-01', '1 HOUR');
        $alt = Period::after('2012-01-01', '2 HOUR');
        $fromOrig = $orig->dateIntervalDiff($alt);
        $fromOrig->invert = 1;

        $this->assertEquals($fromOrig, $alt->dateIntervalDiff($orig));
    }

    public function test_split(): void
    {
        $period = Period::fromDate('2012-01-12', '2012-01-13');

        /** @var Generator<Period> $range */
        $range = $period->splitForward(
            new DateInterval('PT1H'),
        );

        $this->assertSame(24, iterator_count($range));
    }

    public function test_split_must_recreate_parent_object(): void
    {
        $period = Period::fromDate('2012-01-12', '2012-01-13');
        $range = $period->splitForward(
            new DateInterval('PT1H'),
        );

        /** @var null|Period $total */
        $total = null;

        foreach ($range as $part) {
            if (null === $total) {
                $total = $part;

                continue;
            }

            $total = $total->endingOn($part->endDate);
        }

        $this->assertInstanceOf(Period::class, $total);
        $this->assertTrue($total->equals($period));
    }

    public function test_split_with_large_interval(): void
    {
        $period = Period::fromDate('2012-01-12', '2012-01-13');
        $range = [];

        foreach ($period->splitForward(
            new DateInterval('P1Y'),
        ) as $innerPeriod) {
            $range[] = $innerPeriod;
        }

        $this->assertCount(1, $range);
        $this->assertTrue($range[0]->equals($period));
    }

    public function test_split_with_inconsistent_interval(): void
    {
        $last = null;
        $period = Period::fromDate('2012-01-12', '2012-01-13');

        foreach ($period->splitForward('10 HOURS') as $innerPeriod) {
            $last = $innerPeriod;
        }

        $this->assertInstanceOf(Period::class, $last);
        $this->assertSame(14_400, $last->timeDuration());
    }

    public function test_split_backwards(): void
    {
        $period = Period::fromDate('2015-01-01', '2015-01-04');
        $range = $period->splitBackwards('1 DAY');
        $list = [];

        foreach ($range as $innerPeriod) {
            $list[] = $innerPeriod;
        }

        $result = array_map(fn (Period $range): array => [
            'start' => $range->startDate->format('Y-m-d H:i:s'),
            'end' => $range->endDate->format('Y-m-d H:i:s'),
        ], $list);

        $expected = [
            [
                'start' => '2015-01-03 00:00:00',
                'end' => '2015-01-04 00:00:00',
            ],
            [
                'start' => '2015-01-02 00:00:00',
                'end' => '2015-01-03 00:00:00',
            ],
            [
                'start' => '2015-01-01 00:00:00',
                'end' => '2015-01-02 00:00:00',
            ],
        ];
        $this->assertSame($expected, $result);
    }

    public function test_split_backwards_with_inconsistent_interval(): void
    {
        $period = Period::fromDate('2010-01-01', '2010-01-02');
        $last = null;

        foreach ($period->splitBackwards(
            new DateInterval('PT10H'),
        ) as $innerPeriod) {
            $last = $innerPeriod;
        }

        $this->assertInstanceOf(Period::class, $last);
        $this->assertSame(14_400, $last->timeDuration());
    }

    public function test_split_daylight_savings_day_into_hours_end_interval(): void
    {
        date_default_timezone_set('Canada/Central');
        $period = Period::fromDate('2018-11-04 00:00:00.000000', '2018-11-04 05:00:00.000000');

        /** @var Generator<Period> $splits */
        $splits = $period->splitForward('30 MINUTES');

        $this->assertSame(10, iterator_count($splits));
    }

    public function test_split_backwards_daylight_savings_day_into_hours_start_interval(): void
    {
        date_default_timezone_set('Canada/Central');
        $period = Period::fromDate('2018-04-11 00:00:00.000000', '2018-04-11 05:00:00.000000');

        /** @var Generator<Period> $splits */
        $splits = $period->splitBackwards(
            new DateInterval('PT30M'),
        );

        $this->assertSame(10, iterator_count($splits));
    }
}
