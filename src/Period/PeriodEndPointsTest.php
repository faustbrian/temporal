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
use Illuminate\Support\Facades\Date;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class PeriodEndPointsTest extends PeriodTestCase
{
    public function test_starting_on(): void
    {
        $expected = Date::parse('2012-03-02');
        $interval = Period::fromDate(
            Date::parse('2014-01-13'),
            Date::parse('2014-01-20'),
        );
        $newInterval = $interval->startingOn($expected);

        $this->assertSame($newInterval->startDate->getTimestamp(), $expected->getTimestamp());
        $this->assertEquals($interval->startDate, CarbonImmutable::parse('2014-01-13'));
        $this->assertSame($interval->startingOn($interval->startDate), $interval);
    }

    public function test_starting_on_failed_with_wrong_start_date(): void
    {
        $this->expectException(InvalidInterval::class);
        $interval = Period::fromDate(
            Date::parse('2014-01-13'),
            Date::parse('2014-01-20'),
        );
        $interval->startingOn(
            Date::parse('2015-03-02'),
        );
    }

    public function test_ending_on(): void
    {
        $expected = Date::parse('2015-03-02');
        $interval = Period::fromDate(
            Date::parse('2014-01-13'),
            Date::parse('2014-01-20'),
        );
        $newInterval = $interval->endingOn($expected);
        $this->assertSame($newInterval->endDate->getTimestamp(), $expected->getTimestamp());
        $this->assertEquals($interval->endDate, CarbonImmutable::parse('2014-01-20'));
        $this->assertSame($interval->endingOn($interval->endDate), $interval);
    }

    public function test_ending_on_failed_with_wrong_end_date(): void
    {
        $this->expectException(InvalidInterval::class);
        $interval = Period::fromDate(
            Date::parse('2014-01-13'),
            Date::parse('2014-01-20'),
        );
        $interval->endingOn(
            Date::parse('2012-03-02'),
        );
    }

    public function test_expand(): void
    {
        $interval = Period::fromDate(
            Date::parse('2012-02-02'),
            Date::parse('2012-02-03'),
        )->expand(
            new DateInterval('P1D'),
        );
        $this->assertEquals(
            CarbonImmutable::parse('2012-02-01'),
            $interval->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2012-02-04'),
            $interval->endDate,
        );
    }

    public function test_expand_retuns_same_instance(): void
    {
        $interval = Period::fromDate(
            Date::parse('2012-02-02'),
            Date::parse('2012-02-03'),
        );
        $this->assertSame($interval->expand(
            new DateInterval('PT0S'),
        ), $interval);
    }

    public function test_shrink(): void
    {
        $dateInterval = new DateInterval('PT12H');
        $dateInterval->invert = 1;

        $interval = Period::fromDate(
            Date::parse('2012-02-02'),
            Date::parse('2012-02-03'),
        )->expand($dateInterval);
        $this->assertEquals(
            CarbonImmutable::parse('2012-02-02 12:00:00'),
            $interval->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2012-02-02 12:00:00'),
            $interval->endDate,
        );
    }

    public function test_expand_throws_exception(): void
    {
        $this->expectException(InvalidInterval::class);
        $dateInterval = new DateInterval('P1D');
        $dateInterval->invert = 1;
        Period::fromDate(
            Date::parse('2012-02-02'),
            Date::parse('2012-02-03'),
        )->expand($dateInterval);
    }

    public function test_move(): void
    {
        $interval = Period::fromDate(
            Date::parse('2016-01-01 15:32:12'),
            Date::parse('2016-01-15 12:00:01'),
        );
        $moved = $interval->move(
            new DateInterval('P1D'),
        );
        $this->assertFalse($interval->equals($moved));
        $this->assertTrue($interval->move(
            new DateInterval('PT0S'),
        )->equals($interval));
    }

    public function test_move_support_string_intervals(): void
    {
        $interval = Period::fromDate(
            Date::parse('2016-01-01 15:32:12'),
            Date::parse('2016-01-15 12:00:01'),
        );
        $advanced = $interval->move(DateInterval::createFromDateString('1 DAY'));
        $alt = Period::fromDate(
            Date::parse('2016-01-02 15:32:12'),
            Date::parse('2016-01-16 12:00:01'),
        );
        $this->assertTrue($alt->equals($advanced));
    }

    public function test_move_with_inverted_interval(): void
    {
        $orig = Period::fromDate(
            Date::parse('2016-01-01 15:32:12'),
            Date::parse('2016-01-15 12:00:01'),
        );
        $alt = Period::fromDate(
            Date::parse('2016-01-02 15:32:12'),
            Date::parse('2016-01-16 12:00:01'),
        );
        $duration = new DateInterval('P1D');
        $duration->invert = 1;
        $this->assertTrue($orig->equals($alt->move($duration)));
    }

    public function test_move_with_inverted_string_interval(): void
    {
        $orig = Period::fromDate(
            Date::parse('2016-01-01 15:32:12'),
            Date::parse('2016-01-15 12:00:01'),
        );
        $alt = Period::fromDate(
            Date::parse('2016-01-02 15:32:12'),
            Date::parse('2016-01-16 12:00:01'),
        );
        $this->assertTrue($orig->equals($alt->move(DateInterval::createFromDateString('-1 DAY'))));
    }

    public function test_with_duration_after_start(): void
    {
        $expected = Period::fromDate(
            Date::parse('2014-03-01'),
            CarbonImmutable::parse('2014-04-01'),
        );
        $period = Period::fromDate(
            CarbonImmutable::parse('2014-03-01'),
            Date::parse('2014-03-15'),
        );
        $this->assertEquals($expected, $period->withDurationAfterStart(DateInterval::createFromDateString('1 MONTH')));
    }

    public function test_with_duration_after_start_throws_exception(): void
    {
        $this->expectException(InvalidInterval::class);
        $period = Period::fromDate(
            Date::parse('2014-03-01'),
            Date::parse('2014-03-15'),
        );
        $interval = new DateInterval('P1D');
        $interval->invert = 1;

        $period->withDurationAfterStart($interval);
    }

    public function test_with_duration_before_end(): void
    {
        $expected = Period::fromDate(
            CarbonImmutable::parse('2014-02-01'),
            Date::parse('2014-03-01'),
        );
        $period = Period::fromDate(
            CarbonImmutable::parse('2014-02-15'),
            Date::parse('2014-03-01'),
        );
        $this->assertEquals($expected, $period->withDurationBeforeEnd(DateInterval::createFromDateString('1 MONTH')));
    }

    public function test_with_duration_before_end_throws_exception(): void
    {
        $this->expectException(InvalidInterval::class);
        $period = Period::fromDate(
            CarbonImmutable::parse('2014-02-15'),
            CarbonImmutable::parse('2014-03-01'),
        );
        $interval = new DateInterval('P1D');
        $interval->invert = 1;

        $period->withDurationBeforeEnd($interval);
    }

    public function test_merge(): void
    {
        $period = Period::fromMonth(2_014, 3);
        $altPeriod = Period::fromMonth(2_014, 4);
        $expected = Period::after(
            CarbonImmutable::parse('2014-03-01'),
            DateInterval::createFromDateString('2 MONTHS'),
        );
        $this->assertEquals($expected, $period->merge($altPeriod));
        $this->assertEquals($expected, $altPeriod->merge($period));
        $this->assertEquals($expected, $expected->merge($period, $altPeriod));
    }

    public function test_merging_without_arguments(): void
    {
        $period = Period::fromMonth(2_014, 3);
        $this->assertSame($period, $period->merge());
    }

    public function test_move_end_date(): void
    {
        $orig = Period::after(
            CarbonImmutable::parse('2012-01-01'),
            DateInterval::createFromDateString('2 MONTH'),
        );
        $period = $orig->moveEndDate(DateInterval::createFromDateString('-1 MONTH'));
        $this->assertSame(1, $orig->durationCompare($period));
        $this->assertTrue($orig->durationGreaterThan($period));
        $this->assertEquals($orig->startDate, $period->startDate);
    }

    public function test_move_end_date_throws_exception(): void
    {
        $this->expectException(InvalidInterval::class);

        Period::after(
            CarbonImmutable::parse('2012-01-01'),
            DateInterval::createFromDateString('1 MONTH'),
        )->moveEndDate(DateInterval::createFromDateString('-3 MONTHS'));
    }

    public function test_move_start_date_backward(): void
    {
        $orig = Period::fromMonth(2_012, 1);
        $period = $orig->moveStartDate(DateInterval::createFromDateString('-1 MONTH'));
        $this->assertSame(-1, $orig->durationCompare($period));
        $this->assertTrue($orig->durationLessThan($period));
        $this->assertEquals($orig->endDate, $period->endDate);
        $this->assertNotEquals($orig->startDate, $period->startDate);
    }

    public function test_move_start_date_forward(): void
    {
        $orig = Period::fromMonth(2_012, 1);
        $period = $orig->moveStartDate(DateInterval::createFromDateString('2 WEEKS'));
        $this->assertSame(1, $orig->durationCompare($period));
        $this->assertTrue($orig->durationGreaterThan($period));
        $this->assertEquals($orig->endDate, $period->endDate);
        $this->assertNotEquals($orig->startDate, $period->startDate);
    }

    public function test_move_start_date_throws_exception(): void
    {
        $this->expectException(InvalidInterval::class);
        Period::after(
            CarbonImmutable::parse('2012-01-01'),
            DateInterval::createFromDateString('1 MONTH'),
        )->moveStartDate(DateInterval::createFromDateString('3 MONTHS'));
    }

    public function test_snap_to_second(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
            Bounds::IncludeAll,
        );

        $snapToSeconds = $period->snapToSecond();

        $this->assertSame('2021-07-18 12:12:12.000000', $snapToSeconds->startDate->format('Y-m-d H:i:s.u'));
        $this->assertSame('2021-07-23 12:12:13.000000', $snapToSeconds->endDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($period->bounds, $snapToSeconds->bounds);
        $this->assertEquals($snapToSeconds, $period->snapToSecond()->snapToSecond());
    }

    public function test_snap_to_minute(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
            Bounds::ExcludeAll,
        );

        $snapToSeconds = $period->snapToMinute();

        $this->assertSame('2021-07-18 12:12:00.000000', $snapToSeconds->startDate->format('Y-m-d H:i:s.u'));
        $this->assertSame('2021-07-23 12:13:00.000000', $snapToSeconds->endDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($period->bounds, $snapToSeconds->bounds);
        $this->assertEquals($snapToSeconds, $period->snapToMinute()->snapToMinute());
    }

    public function test_snap_to_hour(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
            Bounds::ExcludeStartIncludeEnd,
        );

        $snapToSeconds = $period->snapToHour();

        $this->assertSame('2021-07-18 12:00:00.000000', $snapToSeconds->startDate->format('Y-m-d H:i:s.u'));
        $this->assertSame('2021-07-23 13:00:00.000000', $snapToSeconds->endDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($period->bounds, $snapToSeconds->bounds);
        $this->assertEquals($snapToSeconds, $period->snapToHour()->snapToHour());
    }

    public function test_snap_to_day(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
            Bounds::IncludeStartExcludeEnd,
        );

        $snapToSeconds = $period->snapToDay();

        $this->assertSame('2021-07-18 00:00:00.000000', $snapToSeconds->startDate->format('Y-m-d H:i:s.u'));
        $this->assertSame('2021-07-24 00:00:00.000000', $snapToSeconds->endDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($period->bounds, $snapToSeconds->bounds);
        $this->assertEquals($snapToSeconds, $period->snapToDay()->snapToDay());
    }

    public function test_snap_to_month(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
            Bounds::IncludeStartExcludeEnd,
        );

        $snapToSeconds = $period->snapToMonth();

        $this->assertSame('2021-07-01 00:00:00.000000', $snapToSeconds->startDate->format('Y-m-d H:i:s.u'));
        $this->assertSame('2021-08-01 00:00:00.000000', $snapToSeconds->endDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($period->bounds, $snapToSeconds->bounds);
        $this->assertEquals($snapToSeconds, $period->snapToMonth()->snapToMonth());
    }

    public function test_snap_to_year(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
            Bounds::IncludeStartExcludeEnd,
        );

        $snapToSeconds = $period->snapToYear();

        $this->assertSame('2021-01-01 00:00:00.000000', $snapToSeconds->startDate->format('Y-m-d H:i:s.u'));
        $this->assertSame('2022-01-01 00:00:00.000000', $snapToSeconds->endDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($period->bounds, $snapToSeconds->bounds);
        $this->assertEquals($snapToSeconds, $period->snapToYear()->snapToYear());
    }

    public function test_snap_to_quarter(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
            Bounds::IncludeStartExcludeEnd,
        );

        $snapToSeconds = $period->snapToQuarter();
        $startDate = DatePoint::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
        )->quarter()->startDate->format('Y-m-d H:i:s.u');
        $endDate = DatePoint::fromDate(
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
        )->quarter()->endDate->format('Y-m-d H:i:s.u');

        $this->assertSame($startDate, $snapToSeconds->startDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($endDate, $snapToSeconds->endDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($period->bounds, $snapToSeconds->bounds);
        $this->assertEquals($snapToSeconds, $period->snapToQuarter()->snapToQuarter());
    }

    public function test_snap_to_semester(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
            Bounds::IncludeStartExcludeEnd,
        );

        $snapToSeconds = $period->snapToSemester();
        $startDate = DatePoint::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
        )->semester()->startDate->format('Y-m-d H:i:s.u');
        $endDate = DatePoint::fromDate(
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
        )->semester()->endDate->format('Y-m-d H:i:s.u');

        $this->assertSame($startDate, $snapToSeconds->startDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($endDate, $snapToSeconds->endDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($period->bounds, $snapToSeconds->bounds);
        $this->assertEquals($snapToSeconds, $period->snapToSemester()->snapToSemester());
    }

    public function test_snap_to_iso_week(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
            Bounds::IncludeStartExcludeEnd,
        );

        $snapToSeconds = $period->snapToIsoWeek();
        $startDate = DatePoint::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
        )->isoWeek()->startDate->format('Y-m-d H:i:s.u');
        $endDate = DatePoint::fromDate(
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
        )->isoWeek()->endDate->format('Y-m-d H:i:s.u');

        $this->assertSame($startDate, $snapToSeconds->startDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($endDate, $snapToSeconds->endDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($period->bounds, $snapToSeconds->bounds);
        $this->assertEquals($snapToSeconds, $period->snapToIsoWeek()->snapToIsoWeek());
    }

    public function test_snap_to_iso_year(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
            Bounds::IncludeStartExcludeEnd,
        );

        $snapToSeconds = $period->snapToIsoYear();
        $startDate = DatePoint::fromDate(
            CarbonImmutable::parse('2021-07-18 12:12:12.123456'),
        )->isoYear()->startDate->format('Y-m-d H:i:s.u');
        $endDate = DatePoint::fromDate(
            CarbonImmutable::parse('2021-07-23 12:12:12.435672'),
        )->isoYear()->endDate->format('Y-m-d H:i:s.u');

        $this->assertSame($startDate, $snapToSeconds->startDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($endDate, $snapToSeconds->endDate->format('Y-m-d H:i:s.u'));
        $this->assertSame($period->bounds, $snapToSeconds->bounds);
        $this->assertEquals($snapToSeconds, $period->snapToIsoYear()->snapToIsoYear());
    }
}
