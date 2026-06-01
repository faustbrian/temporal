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

use Illuminate\Support\Facades\Date;
use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function date_default_timezone_get;
use function date_default_timezone_set;
use function var_export;

/**
 * @internal
 */
final class DatePointTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    public function test_instantiation_from_set_state(): void
    {
        $datePoint = DatePoint::fromDateString('TOMORROW');

        /** @var DatePoint $generatedDatePoint */
        $generatedDatePoint = eval('return '.var_export($datePoint, true).';');

        $this->assertEquals($datePoint, $generatedDatePoint);
    }

    public function test_using_date_time_zone(): void
    {
        $datePointA = DatePoint::fromDateString('TOMORROW', 'Africa/Nairobi');
        $datePointB = DatePoint::fromDateString('TOMORROW', new DateTimeZone('Africa/Nairobi'));

        $this->assertEquals($datePointA, $datePointB);

        $timeZone = DatePoint::fromDateString('TOMORROW')->date->getTimezone();

        $this->assertEquals(
            new DateTimeZone($this->timezone), $timeZone
        );
    }

    public function test_instantiation_from_minute(): void
    {
        $datePoint = DatePoint::fromDateString('2021-07-08 13:23:58');
        $minutePeriod = $datePoint->minute();

        $this->assertSame('[2021-07-08 13:23:00, 2021-07-08 13:24:00)', $minutePeriod->toIso80000('Y-m-d H:i:s'));
    }

    public function test_instantiation_from_seconds(): void
    {
        $datePoint = DatePoint::fromDateString('2021-07-08 13:23:58');
        $secondPeriod = $datePoint->second();

        $this->assertSame('[2021-07-08 13:23:58, 2021-07-08 13:23:59)', $secondPeriod->toIso80000('Y-m-d H:i:s'));
    }

    #[DataProvider('provideIsAfterCases')]
    public function test_is_after(Period $interval, DateTimeInterface $input, bool $expected): void
    {
        $this->assertSame($expected, DatePoint::fromDate($input)->isAfter($interval));
    }

    /**
     * @return \Iterator<string, array{interval: Period, input: \DateTimeInterface, expected: bool}>
     */
    public static function provideIsAfterCases(): \Iterator
    {
        yield 'range exclude end date success' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => Date::parse('2015-01-01'),
            'expected' => true,
        ];
        yield 'range exclude end date fails' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => Date::parse('2010-01-01'),
            'expected' => false,
        ];
        yield 'range exclude end date abuts date fails' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => Date::parse('2012-01-01'),
            'expected' => false,
        ];
        yield 'range exclude start date success' => [
            'interval' => Period::after(
                Date::parse('2012-01-01'), DateInterval::createFromDateString('1 MONTH'), Bounds::ExcludeStartIncludeEnd
            ),
            'input' => Date::parse('2015-01-01'),
            'expected' => true,
        ];
        yield 'range exclude start date fails' => [
            'interval' => Period::after(
                Date::parse('2012-01-01'), DateInterval::createFromDateString('1 MONTH'), Bounds::ExcludeStartIncludeEnd
            ),
            'input' => Date::parse('2010-01-01'),
            'expected' => false,
        ];
        yield 'range exclude start date abuts date success' => [
            'interval' => Period::after(
                Date::parse('2012-01-01'), DateInterval::createFromDateString('1 MONTH'), Bounds::ExcludeStartIncludeEnd
            ),
            'input' => Date::parse('2012-02-01'),
            'expected' => false,
        ];
    }

    #[DataProvider('provideIsBeforeCases')]
    public function test_is_before(Period $interval, DateTimeInterface $input, bool $expected): void
    {
        $this->assertSame($expected, DatePoint::fromDate($input)->isBefore($interval));
    }

    /**
     * @return \Iterator<string, array{interval: Period, input: \DateTimeInterface, expected: bool}>
     */
    public static function provideIsBeforeCases(): \Iterator
    {
        yield 'range exclude end date success' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => Date::parse('2010-01-01'),
            'expected' => true,
        ];
        yield 'range exclude end date fails' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => Date::parse('2015-01-01'),
            'expected' => false,
        ];
        yield 'range exclude end date abuts date fails' => [
            'interval' => Period::after(
                Date::parse('2012-01-01'), DateInterval::createFromDateString('1 MONTH'), Bounds::ExcludeStartIncludeEnd
            ),
            'input' => Date::parse('2012-02-01'),
            'expected' => false,
        ];
        yield 'range exclude start date success' => [
            'interval' => Period::after(
                Date::parse('2012-01-01'), DateInterval::createFromDateString('1 MONTH'), Bounds::ExcludeStartIncludeEnd
            ),
            'input' => Date::parse('2012-01-01'),
            'expected' => true,
        ];
    }

    public function test_datepoint_bordering_on(): void
    {
        $datepoint = DatePoint::fromDateString('2018-01-18 10:00:00');
        $duration = Duration::fromDateString('3 minutes');

        $intervalBorderOnStartTrue = Period::after($datepoint, $duration, Bounds::ExcludeStartIncludeEnd);
        $this->assertTrue($datepoint->bordersOnStart($intervalBorderOnStartTrue));
        $this->assertTrue($datepoint->abuts($intervalBorderOnStartTrue));

        $intervalBorderOnStartFalse = Period::after($datepoint, $duration, Bounds::IncludeAll);
        $this->assertFalse($datepoint->bordersOnStart($intervalBorderOnStartFalse));
        $this->assertFalse($datepoint->abuts($intervalBorderOnStartFalse));

        $intervalBorderOnEndTrue = Period::before($datepoint, $duration, Bounds::IncludeStartExcludeEnd);
        $this->assertTrue($datepoint->bordersOnEnd($intervalBorderOnEndTrue));
        $this->assertTrue($datepoint->abuts($intervalBorderOnEndTrue));

        $intervalBorderOnEndFalse = Period::before($datepoint, $duration, Bounds::ExcludeStartIncludeEnd);
        $this->assertFalse($datepoint->bordersOnEnd($intervalBorderOnEndFalse));
        $this->assertFalse($datepoint->abuts($intervalBorderOnEndFalse));
    }

    #[DataProvider('provideIsDuringCases')]
    public function test_is_during(Period $interval, DateTimeInterface|string $input, bool $expected): void
    {
        $datepoint = $input instanceof DateTimeInterface ? DatePoint::fromDate($input) : DatePoint::fromDateString($input);

        $this->assertSame($expected, $datepoint->isDuring($interval));
    }

    /**
     * @return \Iterator<string, array{Period, (\DateTimeInterface | string), bool}>
     */
    public static function provideIsDuringCases(): \Iterator
    {
        yield 'contains returns true with a DateTimeInterface object' => [
            Period::fromDate(
                CarbonImmutable::parse('2014-03-10'), CarbonImmutable::parse('2014-03-15')
            ),
            Date::parse('2014-03-12'),
            true,
        ];
        yield 'contains returns false with a DateTimeInterface object' => [
            Period::fromDate(
                CarbonImmutable::parse('2014-03-13'), CarbonImmutable::parse('2014-03-15')
            ),
            Date::parse('2015-03-12'),
            false,
        ];
        yield 'contains returns false with a DateTimeInterface object after the interval' => [
            Period::fromDate(
                CarbonImmutable::parse('2014-03-13'), CarbonImmutable::parse('2014-03-15')
            ),
            '2012-03-12',
            false,
        ];
        yield 'contains returns false with a DateTimeInterface object before the interval' => [
            Period::fromDate(
                CarbonImmutable::parse('2014-03-13'), CarbonImmutable::parse('2014-03-15')
            ),
            '2014-04-01',
            false,
        ];
        yield 'contains returns false with O duration Period object' => [
            Period::fromDate(
                CarbonImmutable::parse('2012-03-12'), CarbonImmutable::parse('2012-03-12')
            ),
            Date::parse('2012-03-12'),
            false,
        ];
        yield 'contains datetime edge case datetime equals start date' => [
            Period::after(
                Date::parse('2012-01-08'), Duration::fromDateString('1 DAY')
            ),
            Date::parse('2012-01-08'),
            true,
        ];
        yield 'contains datetime edge case datetime equals end date' => [
            Period::after(
                Date::parse('2012-01-08'), Duration::fromDateString('1 DAY')
            ),
            Date::parse('2012-01-09'),
            false,
        ];
        yield 'contains datetime edge case datetime equals start date OLCR interval' => [
            Period::after(
                Date::parse('2012-01-08'), Duration::fromDateString('1 DAY'), Bounds::ExcludeStartIncludeEnd
            ),
            Date::parse('2012-01-08'),
            false,
        ];
        yield 'contains datetime edge case datetime equals end date CLCR interval' => [
            Period::after(
                Date::parse('2012-01-08'), Duration::fromDateString('1 DAY'), Bounds::ExcludeAll
            ),
            Date::parse('2012-01-09'),
            false,
        ];
    }

    #[DataProvider('provideStartsCases')]
    public function test_starts(Period $interval, DateTimeInterface $index, bool $expected): void
    {
        $this->assertSame($expected, DatePoint::fromDate($index)->isStarting($interval));
    }

    /**
     * @return \Iterator<(int | string), array{Period, \DateTimeInterface, bool}>
     */
    public static function provideStartsCases(): \Iterator
    {
        $datepoint = Date::parse('2012-01-01');
        yield [
            Period::fromDate($datepoint, Date::parse('2012-01-15'), Bounds::ExcludeAll),
            $datepoint,
            false,
        ];
        yield [
            Period::fromDate($datepoint, Date::parse('2012-01-15'), Bounds::IncludeStartExcludeEnd),
            $datepoint,
            true,
        ];
    }

    #[DataProvider('provideFinishesCases')]
    public function test_finishes(Period $interval, DateTimeInterface $index, bool $expected): void
    {
        $this->assertSame($expected, DatePoint::fromDate($index)->isEnding($interval));
    }

    /**
     * @return \Iterator<(int | string), array{Period, \DateTimeInterface, bool}>
     */
    public static function provideFinishesCases(): \Iterator
    {
        $datepoint = Date::parse('2012-01-16');
        yield [
            Period::fromDate(
                Date::parse('2012-01-01'), $datepoint, Bounds::ExcludeAll
            ),
            $datepoint,
            false,
        ];
        yield [
            Period::fromDate(
                Date::parse('2012-01-01'), $datepoint, Bounds::IncludeAll
            ),
            $datepoint,
            true,
        ];
    }
}
