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
use DatePeriod;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Date;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;

use function defined;
use function is_string;
use function var_export;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class PeriodFactoryTest extends PeriodTestCase
{
    public function test_instantiation_from_date_point_instance(): void
    {
        $this->assertEquals(
            Period::fromDate(DatePoint::fromDateString('TODAY'), DatePoint::fromDateString('TOMORROW')),
            Period::fromDate(
                CarbonImmutable::parse('TODAY'),
                Date::parse('TOMORROW'),
            ),
        );
    }

    public function test_instantiation_from_date_time_interface_implementing_instance_result_in_equal_instance(): void
    {
        $this->assertEquals(
            Period::fromDate(
                Date::parse('TODAY'),
                CarbonImmutable::parse('TOMORROW'),
            ),
            Period::fromDate(
                CarbonImmutable::parse('TODAY'),
                Date::parse('TOMORROW'),
            ),
        );
    }

    public function test_instantiation_from_set_state(): void
    {
        $period = Period::fromDate(DatePoint::fromDateString('2014-05-01'), DatePoint::fromDateString('2014-05-08'));

        /** @var Period $generatedPeriod */
        $generatedPeriod = eval('return '.var_export($period, true).';');
        $this->assertTrue($generatedPeriod->equals($period));
    }

    public function test_instantiation_from_timestamp(): void
    {
        $dateStart = CarbonImmutable::parse('@1');
        $dateEnd = CarbonImmutable::parse('@2');

        $this->assertEquals(Period::fromDate($dateStart, $dateEnd), Period::fromTimestamp(1, 2));
    }

    public function test_instantiation_precision(): void
    {
        $date = CarbonImmutable::parse('2014-05-01 00:00:00');
        $this->assertEquals(
            new DateInterval('PT0S'),
            Period::fromDate($date, $date)->dateInterval(),
        );
    }

    public function test_instantiation_throw_exception_if_time_zone_is_wrongly_used(): void
    {
        $this->expectException(InvalidInterval::class);
        Period::fromDate(
            new DateTime('2014-05-01', new DateTimeZone('Europe/Paris')),
            new DateTime('2014-05-01', new DateTimeZone('Africa/Nairobi')),
        );
    }

    #[DataProvider('provideInterval_afterCases')]
    public function test_interval_after(string $startDate, string $endDate, Period|DateInterval|int|string $duration): void
    {
        $start = new DateTimeImmutable($startDate);
        $period = match (true) {
            $duration instanceof Period => Period::after($start, $duration->dateInterval()),
            is_string($duration) => Period::after($start, DateInterval::createFromDateString($duration)),
            !$duration instanceof DateInterval => Period::after($start, Duration::fromSeconds($duration)),
            default => Period::after($start, $duration),
        };

        $this->assertEquals($start, $period->startDate);
        $this->assertEquals(
            new DateTimeImmutable($endDate),
            $period->endDate,
        );
    }

    /**
     * @return Iterator<string, array{string, string, (DateInterval|int|Period|string)}>
     */
    public static function provideInterval_afterCases(): iterable
    {
        yield 'usingAString' => [
            '2015-01-01', '2015-01-02', '+1 DAY',
        ];

        yield 'usingAnInt' => [
            '2015-01-01 10:00:00', '2015-01-01 11:00:00', 3_600,
        ];

        yield 'usingADateInterval' => [
            '2015-01-01 10:00:00', '2015-01-01 11:00:00', new DateInterval('PT1H'),
        ];

        yield 'usingAnInterval' => [
            '2015-01-01 10:00:00', '2015-01-01 11:00:00', DatePoint::fromDateString('2012-01-03 12:00:00')->hour(),
        ];
    }

    public function test_interval_after_failed_with_out_of_range_interval(): void
    {
        $this->expectException(InvalidInterval::class);
        $duration = new DateInterval('PT1S');
        $duration->invert = 1;

        Period::after(
            Date::parse('2012-01-12'),
            $duration,
        );
    }

    #[DataProvider('provideInterval_beforeCases')]
    public function test_interval_before(string $startDate, string $endDate, int|DateInterval|string $duration): void
    {
        $end = new DateTimeImmutable($endDate);

        /** @var DateInterval $dateInterval */
        $dateInterval = match (true) {
            is_string($duration) => DateInterval::createFromDateString($duration),
            !$duration instanceof DateInterval => Duration::fromSeconds($duration),
            default => $duration,
        };

        $period = Period::before($end, $dateInterval);
        $this->assertEquals(
            new DateTimeImmutable($startDate),
            $period->startDate,
        );
        $this->assertEquals($end, $period->endDate);
    }

    /**
     * @return Iterator<string, array{string, string, (DateInterval|int|string)}>
     */
    public static function provideInterval_beforeCases(): iterable
    {
        yield 'usingAString' => [
            '2015-01-01', '2015-01-02', '+1 DAY',
        ];

        yield 'usingAnInt' => [
            '2015-01-01 10:00:00', '2015-01-01 11:00:00', 3_600,
        ];

        yield 'usingADateInterval' => [
            '2015-01-01 10:00:00', '2015-01-01 11:00:00', new DateInterval('PT1H'),
        ];
    }

    public function test_interval_before_failed_with_outof_range_interval(): void
    {
        $this->expectException(InvalidInterval::class);
        $duration = new DateInterval('PT1S');
        $duration->invert = 1;

        Period::before(
            Date::parse('2012-01-12'),
            $duration,
        );
    }

    public function test_interval_around(): void
    {
        $datepoint = CarbonImmutable::parse('2012-06-05');
        $interval = DateInterval::createFromDateString('1 WEEK');
        $period = Period::around($datepoint, $interval);

        $this->assertTrue($period->contains($datepoint));
        $this->assertEquals($datepoint->sub($interval), $period->startDate);
        $this->assertEquals($datepoint->add($interval), $period->endDate);
    }

    public function test_interval_around_throws_exception(): void
    {
        $this->expectException(InvalidInterval::class);

        $duration = new DateInterval('PT1S');
        $duration->invert = 1;
        Period::around(
            Date::parse('2012-06-05'),
            $duration,
        );
    }

    public function test_interval_from_date_period(): void
    {
        $datePeriod = new DatePeriod(
            Date::parse('2016-05-16T00:00:00Z'),
            new DateInterval('P1D'),
            Date::parse('2016-05-20T00:00:00Z'),
        );
        $period = Period::fromRange($datePeriod);
        $this->assertEquals($datePeriod->getStartDate(), $period->startDate);
        $this->assertEquals($datePeriod->getEndDate(), $period->endDate);
    }

    public function test_from_date_range_throws_exception(): void
    {
        $this->expectException(InvalidInterval::class);

        Period::fromRange(
            new DatePeriod('R4/2012-07-01T00:00:00Z/P7D'),
        );
    }

    public function test_from_range_throws_exception(): void
    {
        $this->expectException(InvalidInterval::class);

        Period::fromRange(
            new DatePeriod('R4/2012-07-01T00:00:00Z/P7D'),
        );
    }

    #[DataProvider('provideFrom_rangeCases')]
    public function test_from_range(int $options, Bounds $expectedBounds): void
    {
        $datePeriod = new DatePeriod(
            Date::parse('2016-05-16T00:00:00Z'),
            new DateInterval('P1D'),
            Date::parse('2016-05-20T00:00:00Z'),
            $options,
        );

        $period = Period::fromRange($datePeriod);
        $this->assertSame($expectedBounds, $period->bounds);
        $this->assertEquals($datePeriod->getStartDate(), $period->startDate);
        $this->assertEquals($datePeriod->getEndDate(), $period->endDate);
    }

    /**
     * @return iterable<string, array{option: int, expectedBounds: Bounds}>
     */
    public static function provideFrom_rangeCases(): iterable
    {
        yield 'include start date legacy' => [
            'options' => DatePeriod::EXCLUDE_START_DATE,
            'expectedBounds' => Bounds::ExcludeAll,
        ];

        yield 'exclude start date legacy' => [
            'options' => 0,
            'expectedBounds' => Bounds::IncludeStartExcludeEnd,
        ];

        if (!defined('DatePeriod::INCLUDE_END_DATE')) {
            return;
        }

        yield 'include all new' => [
            'options' => DatePeriod::INCLUDE_END_DATE,
            'expectedBounds' => Bounds::IncludeAll,
        ];

        yield 'exclude start date new' => [
            'options' => DatePeriod::INCLUDE_END_DATE | DatePeriod::EXCLUDE_START_DATE,
            'expectedBounds' => Bounds::ExcludeStartIncludeEnd,
        ];
    }

    public function test_iso_week(): void
    {
        $period = Period::fromIsoWeek(2_014, 3);
        $this->assertEquals(
            CarbonImmutable::parse('2014-01-13'),
            $period->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2014-01-20'),
            $period->endDate,
        );
    }

    public function test_month(): void
    {
        $period = Period::fromMonth(2_014, 3);
        $this->assertEquals(
            CarbonImmutable::parse('2014-03-01'),
            $period->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2014-04-01'),
            $period->endDate,
        );
    }

    public function test_quarter(): void
    {
        $period = Period::fromQuarter(2_014, 3);
        $this->assertEquals(
            CarbonImmutable::parse('2014-07-01'),
            $period->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2014-10-01'),
            $period->endDate,
        );
    }

    public function test_semester(): void
    {
        $period = Period::fromSemester(2_014, 2);
        $this->assertEquals(
            CarbonImmutable::parse('2014-07-01'),
            $period->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2015-01-01'),
            $period->endDate,
        );
    }

    public function test_year(): void
    {
        $period = Period::fromYear(2_014);
        $this->assertEquals(
            CarbonImmutable::parse('2014-01-01'),
            $period->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2015-01-01'),
            $period->endDate,
        );
    }

    public function test_iso_year(): void
    {
        $period = Period::fromIsoYear(2_014);
        $interval = DatePoint::fromDateString('2014-06-25')->isoYear();
        $this->assertEquals(
            CarbonImmutable::parse('2013-12-30'),
            $period->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2014-12-29'),
            $period->endDate,
        );
        $this->assertTrue($period->equals($interval));
    }

    public function test_day(): void
    {
        $extendedDate = new class('2008-07-01T22:35:17.123456+08:00') extends DateTimeImmutable {};

        $period = DatePoint::fromDate($extendedDate)->day();
        $this->assertEquals(
            CarbonImmutable::parse('2008-07-01T00:00:00+08:00'),
            $period->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2008-07-02T00:00:00+08:00'),
            $period->endDate,
        );
        $this->assertSame('+08:00', $period->startDate->format('P'));
        $this->assertSame('+08:00', $period->endDate->format('P'));
    }

    public function test_alternate_day(): void
    {
        $period = DatePoint::fromDateString('2008-07-01')->day();
        $alt_period = Period::fromDay(2_008, 7, 1);
        $this->assertEquals($period, $alt_period);
    }

    public function test_hour(): void
    {
        $today = new class('2008-07-01T22:35:17.123456+08:00') extends DateTimeImmutable {};
        $period = DatePoint::fromDate($today)->hour();
        $this->assertEquals(
            CarbonImmutable::parse('2008-07-01T22:00:00+08:00'),
            $period->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2008-07-01T23:00:00+08:00'),
            $period->endDate,
        );
        $this->assertSame('+08:00', $period->startDate->format('P'));
        $this->assertSame('+08:00', $period->endDate->format('P'));
    }

    public function test_create_from_with_date_time_interface(): void
    {
        $this->assertTrue(DatePoint::fromDateString('2008W27')->isoWeek()->equals(Period::fromIsoWeek(2_008, 27)));
        $this->assertTrue(DatePoint::fromDateString('2008-07')->month()->equals(Period::fromMonth(2_008, 7)));
        $this->assertTrue(DatePoint::fromDateString('2008-02')->quarter()->equals(Period::fromQuarter(2_008, 1)));
        $this->assertTrue(DatePoint::fromDateString('2008-10')->semester()->equals(Period::fromSemester(2_008, 2)));
        $this->assertTrue(DatePoint::fromDateString('2008-01')->year()->equals(Period::fromYear(2_008)));
    }

    public function test_month_with_date_time_interface(): void
    {
        $today = new class('2008-07-01T22:35:17.123456+08:00') extends DateTimeImmutable {};
        $period = DatePoint::fromDate($today)->month();
        $this->assertEquals(
            CarbonImmutable::parse('2008-07-01T00:00:00+08:00'),
            $period->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2008-08-01T00:00:00+08:00'),
            $period->endDate,
        );
        $this->assertSame('+08:00', $period->startDate->format('P'));
        $this->assertSame('+08:00', $period->endDate->format('P'));
    }

    public function test_year_with_date_time_interface(): void
    {
        $today = new class('2008-07-01T22:35:17.123456+08:00') extends DateTimeImmutable {};
        $period = DatePoint::fromDate($today)->year();
        $this->assertEquals(
            CarbonImmutable::parse('2008-01-01T00:00:00+08:00'),
            $period->startDate,
        );
        $this->assertEquals(
            CarbonImmutable::parse('2009-01-01T00:00:00+08:00'),
            $period->endDate,
        );
        $this->assertSame('+08:00', $period->startDate->format('P'));
        $this->assertSame('+08:00', $period->endDate->format('P'));
    }

    public function test_instantiate_with_time_stamp(): void
    {
        $period = Period::after(DatePoint::fromTimestamp(12_000_000), new DateInterval('P1D'));

        $this->assertSame('+00:00', $period->endDate->format('P'));
    }

    #[DataProvider('provideCreate_new_instance_from_notationCases')]
    public function test_create_new_instance_from_notation(string $notation, string $format, string $expected): void
    {
        $this->assertSame($expected, Period::fromIso80000($format, $notation)->toIso80000($format));
    }

    /**
     * @return iterable<string, array{notation:string, format:string, expected:string}>
     */
    public static function provideCreate_new_instance_from_notationCases(): iterable
    {
        yield 'date string' => [
            'notation' => '[2021-01-03,2021-01-04)',
            'format' => 'Y-m-d',
            'expected' => '[2021-01-03, 2021-01-04)',
        ];

        yield 'date string with spaces' => [
            'notation' => '(   2021-01-03  ,  2021-01-04  ]',
            'format' => 'Y-m-d',
            'expected' => '(2021-01-03, 2021-01-04]',
        ];

        $now = CarbonImmutable::now()->format(DateTimeInterface::ATOM);
        $tomorrow = CarbonImmutable::tomorrow()->format(DateTimeInterface::ATOM);

        yield 'date string with dynamic names' => [
            'notation' => '[    '.$now.'   , '.$tomorrow.'   ]',
            'format' => DateTimeInterface::ATOM,
            'expected' => '['.$now.', '.$tomorrow.']',
        ];
    }

    #[DataProvider('provideFails_to_create_new_instance_from_iso80000Cases')]
    public function test_fails_to_create_new_instance_from_iso80000(string $notation, string $format): void
    {
        $this->expectException(InvalidInterval::class);

        Period::fromIso80000($format, $notation);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideFails_to_create_new_instance_from_iso80000Cases(): iterable
    {
        yield 'empty string' => ['', 'Y-m-d'];

        yield 'missing separator' => ['[2021-01-02 2021-01-03]', 'Y-m-d'];

        yield 'missing bounds' => ['2021-01-02,2021-01-03', 'Y-m-d'];

        yield 'too many bounds' => ['[2021-01-02,2021-)01-03]', 'Y-m-d'];

        yield 'too many separator' => ['[2021-01-02,2021-,01-03]', 'Y-m-d'];

        yield 'missing dates' => ['[2021-01-02,  ]', 'Y-m-d'];

        yield 'wrong format' => ['[2021-01-02, 2021-01-03]', 'Ymd'];

        yield 'wrong bourbaki' => [']2021-01-02,2021-01-03)', 'Y-m-d'];
    }

    #[DataProvider('provideCreate_new_instance_from_bourbakiCases')]
    public function test_create_new_instance_from_bourbaki(string $notation, string $format, string $expected): void
    {
        $this->assertSame($expected, Period::fromBourbaki($format, $notation)->toBourbaki($format));
    }

    /**
     * @return iterable<string, array{notation:string, format:string, expected:string}>
     */
    public static function provideCreate_new_instance_from_bourbakiCases(): iterable
    {
        yield 'date string' => [
            'notation' => '[2021-01-03,2021-01-04[',
            'format' => 'Y-m-d',
            'expected' => '[2021-01-03, 2021-01-04[',
        ];

        yield 'date string with spaces' => [
            'notation' => ']   2021-01-03  ,  2021-01-04  ]',
            'format' => 'Y-m-d',
            'expected' => ']2021-01-03, 2021-01-04]',
        ];

        $now = CarbonImmutable::now()->format(DateTimeInterface::ATOM);
        $tomorrow = CarbonImmutable::tomorrow()->format(DateTimeInterface::ATOM);

        yield 'date string with dynamic names' => [
            'notation' => '[    '.$now.'   , '.$tomorrow.'   ]',
            'format' => DateTimeInterface::ATOM,
            'expected' => '['.$now.', '.$tomorrow.']',
        ];
    }

    #[DataProvider('provideFails_to_create_new_instance_from_bourbakiCases')]
    public function test_fails_to_create_new_instance_from_bourbaki(string $notation, string $format): void
    {
        $this->expectException(InvalidInterval::class);

        Period::fromBourbaki($format, $notation);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideFails_to_create_new_instance_from_bourbakiCases(): iterable
    {
        yield 'empty string' => ['', 'Y-m-d'];

        yield 'missing separator' => ['[2021-01-02 2021-01-03]', 'Y-m-d'];

        yield 'missing bounds' => ['2021-01-02,2021-01-03', 'Y-m-d'];

        yield 'too many bounds' => ['[2021-01-02,2021-[01-03]', 'Y-m-d'];

        yield 'too many separator' => ['[2021-01-02,2021-,01-03]', 'Y-m-d'];

        yield 'missing dates' => ['[2021-01-02,  ]', 'Y-m-d'];

        yield 'wrong format' => ['[2021-01-02, 2021-01-03]', 'Ymd'];

        yield 'wrong bourbaki' => ['[2021-01-02,2021-01-03)', 'Y-m-d'];
    }

    #[DataProvider('provideCreate_new_instance_from_iso_notationCases')]
    public function test_create_new_instance_from_iso_notation(
        string $inputFormat,
        string $notation,
        Bounds $bounds,
        string $outputFormat,
        string $expected,
    ): void {
        $period = Period::fromIso8601($inputFormat, $notation, $bounds);

        $this->assertSame($expected, $period->toIso8601($outputFormat));
        $this->assertSame($bounds, $period->bounds);
    }

    /**
     * @return Iterator<string, array{inputFormat: string, notation: string, bounds: Bounds, outputFormat: string, expected: string}>
     */
    public static function provideCreate_new_instance_from_iso_notationCases(): iterable
    {
        yield 'same input/output format' => [
            'inputFormat' => 'Y-m-d',
            'notation' => '2021-03-25/2021-03-26',
            'bounds' => Bounds::IncludeAll,
            'outputFormat' => 'Y-m-d',
            'expected' => '2021-03-25/2021-03-26',
        ];

        yield 'different input/output format' => [
            'inputFormat' => 'Y-m-d',
            'notation' => '2021-03-25/2021-03-26',
            'bounds' => Bounds::ExcludeAll,
            'outputFormat' => 'Y-n-d',
            'expected' => '2021-3-25/2021-3-26',
        ];

        yield 'same input/output format extended' => [
            'inputFormat' => 'Y-m-d',
            'notation' => '2021-03-25/26',
            'bounds' => Bounds::IncludeAll,
            'outputFormat' => 'Y-m-d',
            'expected' => '2021-03-25/2021-03-26',
        ];

        yield 'different input/output format extended' => [
            'inputFormat' => 'Y-m-d',
            'notation' => '2021-03-25/03-26',
            'bounds' => Bounds::ExcludeAll,
            'outputFormat' => 'Y-n-d',
            'expected' => '2021-3-25/2021-3-26',
        ];

        yield 'different input/output format with interval duration after start' => [
            'inputFormat' => 'Y-m-d',
            'notation' => '2021-03-25/P1D',
            'bounds' => Bounds::ExcludeAll,
            'outputFormat' => 'Y-n-d',
            'expected' => '2021-3-25/2021-3-26',
        ];

        yield 'different input/output format with interval duration before end' => [
            'inputFormat' => 'Y-m-d',
            'notation' => 'P1D/2021-03-26',
            'bounds' => Bounds::ExcludeAll,
            'outputFormat' => 'Y-n-d',
            'expected' => '2021-3-25/2021-3-26',
        ];
    }

    #[DataProvider('provideFails_to_create_new_instance_from_iso_notationCases')]
    public function test_fails_to_create_new_instance_from_iso_notation(string $notation, string $format, Bounds $bounds): void
    {
        $this->expectException(InvalidInterval::class);

        Period::fromIso8601($format, $notation, $bounds);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: Bounds}>
     */
    public static function provideFails_to_create_new_instance_from_iso_notationCases(): iterable
    {
        yield 'empty string' => ['', 'Y-m-d', Bounds::IncludeAll];

        yield 'missing separator' => ['2021-01-02 2021-01-03', 'Y-m-d', Bounds::IncludeAll];

        yield 'too many separator' => ['2021-01-02/2021-/01-03', 'Y-m-d', Bounds::IncludeAll];

        yield 'missing dates' => ['2021-01-02/', 'Y-m-d', Bounds::IncludeAll];

        yield 'wrong format' => ['2021-01-02/2021-01-03', 'Ymd', Bounds::IncludeAll];

        yield 'invalid extended format delimiters are different' => ['2021-01-02/01:03', 'Ymd', Bounds::IncludeAll];

        yield 'invalid extended format start date is shorter than end date' => ['01/2021-01-02', 'Ymd', Bounds::IncludeAll];

        yield 'invalid date with wrong period' => ['PMD/2021-01-02', 'Ymd', Bounds::IncludeAll];
    }
}
