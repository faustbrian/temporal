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
use PHPUnit\Framework\Attributes\DataProvider;

use function count;

/**
 * @internal
 */
final class PeriodRelationTest extends PeriodTestCase
{
    #[DataProvider('provideIsBeforeCases')]
    public function test_is_before(Period $interval, DateTimeInterface|Period|string $input, bool $expected): void
    {
        $this->assertSame($expected, $interval->isBefore($input));
    }

    /**
     * @return \Iterator<string, array{interval: Period, input: (Period | \DateTimeInterface | string), expected: bool}>
     */
    public static function provideIsBeforeCases(): \Iterator
    {
        yield 'range exclude end date success' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => '2015-01-01',
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
            'interval' => Period::after('2012-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'input' => CarbonImmutable::parse('2015-01-01'),
            'expected' => true,
        ];
        yield 'range exclude start date fails' => [
            'interval' => Period::after('2012-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'input' => Date::parse('2010-01-01'),
            'expected' => false,
        ];
        yield 'range exclude start date abuts date success' => [
            'interval' => Period::after('2012-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'input' => Date::parse('2012-02-01'),
            'expected' => false,
        ];
        yield 'exclude end date is before interval' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => Period::fromMonth(2_011, 1),
            'expected' => false,
        ];
        yield 'exclude end date is not before interval' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => Period::fromMonth(2_013, 1),
            'expected' => true,
        ];
        yield 'exclude end date abuts interval start date' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => Period::fromMonth(2_012, 2),
            'expected' => true,
        ];
        yield 'exclude start date is before interval' => [
            'interval' => Period::after('2012-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'input' => Period::fromMonth(2_012, 2),
            'expected' => false,
        ];
        yield 'exclude start date is not before interval' => [
            'interval' => Period::after('2012-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'input' => Period::fromMonth(2_012, 3),
            'expected' => true,
        ];
        yield 'exclude start date abuts interval start date' => [
            'interval' => Period::after('2011-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'input' => Period::after('2012-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'expected' => true,
        ];
    }

    #[DataProvider('provideIsAfterCases')]
    public function test_is_after(Period $interval, DateTimeInterface|Period|string $input, bool $expected): void
    {
        $this->assertSame($expected, $interval->isAfter($input));
    }

    /**
     * @return \Iterator<string, array{interval: Period, input: (Period | \DateTimeInterface | string), expected: bool}>
     */
    public static function provideIsAfterCases(): \Iterator
    {
        yield 'range exclude end date success' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => '2010-01-01',
            'expected' => true,
        ];
        yield 'range exclude end date fails' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => Date::parse('2015-01-01'),
            'expected' => false,
        ];
        yield 'range exclude end date abuts date fails' => [
            'interval' => Period::after('2012-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'input' => CarbonImmutable::parse('2012-02-01'),
            'expected' => false,
        ];
        yield 'range exclude start date success' => [
            'interval' => Period::after('2012-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'input' => Date::parse('2012-01-01'),
            'expected' => true,
        ];
        yield 'exclude end date is before interval' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => Period::fromMonth(2_011, 1),
            'expected' => true,
        ];
        yield 'exclude end date is not before interval' => [
            'interval' => Period::fromMonth(2_013, 1),
            'input' => Period::fromMonth(2_012, 1),
            'expected' => true,
        ];
        yield 'exclude end date abuts interval start date' => [
            'interval' => Period::fromMonth(2_012, 2),
            'input' => Period::fromMonth(2_012, 1),
            'expected' => true,
        ];
        yield 'exclude start date is before interval' => [
            'interval' => Period::fromMonth(2_012, 2),
            'input' => Period::after('2012-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'expected' => false,
        ];
        yield 'exclude start date is not before interval' => [
            'interval' => Period::fromMonth(2_012, 3),
            'input' => Period::after('2012-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'expected' => true,
        ];
        yield 'exclude start date abuts interval start date' => [
            'interval' => Period::after('2012-01-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'input' => Period::after('2011-12-01', '1 MONTH', Bounds::ExcludeStartIncludeEnd),
            'expected' => true,
        ];
        yield 'exclude start date abuts interval start date -2-' => [
            'interval' => Period::fromMonth(2_012, 1),
            'input' => Period::fromMonth(2_012, 2),
            'expected' => false,
        ];
    }

    #[DataProvider('provideAbutsCases')]
    public function test_abuts(Period $interval, Period $arg, bool $expected): void
    {
        $this->assertSame($expected, $interval->abuts($arg));
    }

    /**
     * @return \Iterator<string, array{Period, Period, bool}>
     */
    public static function provideAbutsCases(): \Iterator
    {
        yield 'test abuts returns true with equal datepoints by defaut' => [
            Period::fromDate('2012-01-01', '2012-02-01'),
            Period::fromDate('2012-02-01', '2012-05-01'),
            true,
        ];
        yield 'test abuts returns fase without equal datepoints' => [
            Period::fromDate('2012-01-01', '2012-02-01'),
            Period::fromDate('2012-01-01', '2012-03-01'),
            false,
        ];
        yield 'test abuts returns true with equal datepoints by if boundary is inclusif (1)' => [
            Period::fromDate('2012-01-01', '2012-02-01', Bounds::IncludeAll),
            Period::fromDate('2012-02-01', '2012-05-01', Bounds::IncludeAll),
            false,
        ];
        yield 'test abuts returns true with equal datepoints by if boundary is inclusif (2)' => [
            Period::fromDate('2012-02-01', '2012-05-01', Bounds::IncludeAll),
            Period::fromDate('2012-01-01', '2012-02-01', Bounds::IncludeAll),
            false,
        ];
    }

    #[DataProvider('provideOverlapsCases')]
    public function test_overlaps(Period $interval, Period $arg, bool $expected): void
    {
        $this->assertSame($expected, $interval->overlaps($arg));
    }

    /**
     * @return \Iterator<string, array{Period, Period, bool}>
     */
    public static function provideOverlapsCases(): \Iterator
    {
        yield 'overlaps returns false with gapped intervals' => [
            Period::fromDate('2014-03-01', '2014-04-01'),
            Period::fromDate('2013-04-01', '2013-05-01'),
            false,
        ];
        yield 'overlaps returns false with abuts intervals' => [
            Period::fromDate('2014-03-01', '2014-04-01'),
            Period::fromDate('2014-04-01', '2014-05-01'),
            false,
        ];
        yield 'overlaps returns' => [
            Period::fromDate('2014-03-01', '2014-04-01'),
            Period::fromDate('2014-03-15', '2014-04-07'),
            true,
        ];
        yield 'overlaps returns with equals intervals' => [
            Period::fromDate('2014-03-01', '2014-04-01'),
            Period::fromDate('2014-03-01', '2014-04-01'),
            true,
        ];
        yield 'overlaps returns with contained intervals' => [
            Period::fromDate('2014-03-01', '2014-04-01'),
            Period::fromDate('2014-03-13', '2014-03-15'),
            true,
        ];
        yield 'overlaps returns with contained intervals backwards' => [
            Period::fromDate('2014-03-13', '2014-03-15'),
            Period::fromDate('2014-03-01', '2014-04-01'),
            true,
        ];
    }

    #[DataProvider('provideContainsCases')]
    public function test_contains(Period $interval, DateTimeInterface|Period|string $arg, bool $expected): void
    {
        $this->assertSame($expected, $interval->contains($arg));

        if (!$arg instanceof Period) {
            return;
        }

        $this->assertSame($expected, $arg->isDuring($interval));
    }

    /**
     * @return \Iterator<string, array{Period, (Period | \DateTimeInterface | string), bool}>
     */
    public static function provideContainsCases(): \Iterator
    {
        yield 'contains returns true with a DateTimeInterface object' => [
            Period::fromDate('2014-03-10', '2014-03-15'),
            Date::parse('2014-03-12'),
            true,
        ];
        yield 'contains returns true with a Period object' => [
            Period::fromDate('2014-01-01', '2014-06-01'),
            Period::fromDate('2014-01-01', '2014-04-01'),
            true,
        ];
        yield 'contains returns true with a Period object (2)' => [
            Period::fromDate('2014-03-01', '2014-06-01', Bounds::ExcludeStartIncludeEnd),
            Period::fromDate('2014-05-01', '2014-06-01', Bounds::ExcludeStartIncludeEnd),
            true,
        ];
        yield 'contains returns true with a Period object (3)' => [
            Period::fromDate('2014-03-01', '2014-06-01', Bounds::ExcludeAll),
            Period::fromDate('2014-05-01', '2014-06-01', Bounds::ExcludeAll),
            true,
        ];
        yield 'contains returns true with a Period object (4)' => [
            Period::fromDate('2014-03-01', '2014-06-01', Bounds::IncludeAll),
            Period::fromDate('2014-05-01', '2014-06-01', Bounds::IncludeAll),
            true,
        ];
        yield 'contains returns false with a DateTimeInterface object' => [
            Period::fromDate('2014-03-13', '2014-03-15'),
            Date::parse('2015-03-12'),
            false,
        ];
        yield 'contains returns false with a DateTimeInterface object after the interval' => [
            Period::fromDate('2014-03-13', '2014-03-15'),
            Date::parse('2012-03-12'),
            false,
        ];
        yield 'contains returns false with a DateTimeInterface object before the interval' => [
            Period::fromDate('2014-03-13', '2014-03-15'),
            Date::parse('2014-04-01'),
            false,
        ];
        yield 'contains returns false with abuts interval' => [
            Period::fromDate(
                CarbonImmutable::parse('2014-01-01'), CarbonImmutable::parse('2014-04-01')
            ),
            Period::fromDate(
                CarbonImmutable::parse('2014-01-01'), CarbonImmutable::parse('2014-06-01')
            ),
            false,
        ];
        yield 'contains returns true with a Period objects sharing the same end date' => [
            Period::fromDate(
                CarbonImmutable::parse('2015-01-01'), CarbonImmutable::parse('2016-01-01')
            ),
            Period::fromDate(
                CarbonImmutable::parse('2015-12-01'), CarbonImmutable::parse('2016-01-01')
            ),
            true,
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
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY')
            ),
            Date::parse('2012-01-08'),
            true,
        ];
        yield 'contains datetime edge case datetime equals end date' => [
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY')
            ),
            Date::parse('2012-01-09'),
            false,
        ];
        yield 'contains datetime edge case datetime equals start date OLCR interval' => [
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::ExcludeStartIncludeEnd
            ),
            Date::parse('2012-01-08'),
            false,
        ];
        yield 'contains datetime edge case datetime equals end date CLCR interval' => [
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::ExcludeAll
            ),
            Date::parse('2012-01-09'),
            false,
        ];
        yield 'contains period same duration + boundary type CLCR vs CLCR' => [
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::ExcludeAll
            ),
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::ExcludeAll
            ),
            true,
        ];
        yield 'contains period same duration + boundary type OLOR vs OLOR' => [
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::IncludeAll
            ),
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::IncludeAll
            ),
            true,
        ];
        yield 'contains period same duration + boundary type CLOR vs CLOR' => [
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::ExcludeStartIncludeEnd
            ),
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::ExcludeStartIncludeEnd
            ),
            true,
        ];
        yield 'contains period same duration + boundary type CLOR vs OLCR' => [
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::ExcludeStartIncludeEnd
            ),
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::IncludeStartExcludeEnd
            ),
            false,
        ];
        yield 'contains period same duration + boundary type OLCR vs CLOR' => [
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::IncludeStartExcludeEnd
            ),
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::ExcludeStartIncludeEnd
            ),
            false,
        ];
        yield 'contains period same duration + boundary type CLCR vs OLOR' => [
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::ExcludeAll
            ),
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::IncludeAll
            ),
            false,
        ];
        yield 'contains period same duration + boundary type OLOR vs CLCR' => [
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::IncludeAll
            ),
            Period::after(
                CarbonImmutable::parse('2012-01-08'), DateInterval::createFromDateString('1 DAY'), Bounds::ExcludeAll
            ),
            true,
        ];
    }

    #[DataProvider('provideStartsCases')]
    public function test_starts(Period $interval, DateTimeInterface|Period $index, bool $expected): void
    {
        $this->assertSame($expected, $interval->isStartedBy($index));

        if (!$index instanceof DateTimeInterface) {
            return;
        }

        $this->assertSame($expected, DatePoint::fromDate($index)->isStarting($interval));
    }

    /**
     * @return \Iterator<(int | string), array{Period, (Period | \DateTimeInterface), bool}>
     */
    public static function provideStartsCases(): \Iterator
    {
        $startingDate = Date::parse('2012-01-01');
        $interval = Period::fromDate($startingDate, Date::parse('2012-01-15'));
        yield [
            $interval,
            $interval,
            true,
        ];
        yield [
            $interval,
            $interval->moveEndDate('+3 MINUTES'),
            true,
        ];
        yield [
            $interval,
            $interval->moveStartDate('+3 MINUTES'),
            false,
        ];
        yield [
            $interval->boundedBy(Bounds::IncludeAll),
            $interval,
            true,
        ];
        yield [
            $interval->boundedBy(Bounds::ExcludeAll),
            $interval->boundedBy(Bounds::IncludeAll),
            false,
        ];
        yield [
            $interval->boundedBy(Bounds::ExcludeAll),
            $startingDate,
            false,
        ];
        yield [
            $interval->boundedBy(Bounds::IncludeStartExcludeEnd),
            $startingDate,
            true,
        ];
    }

    #[DataProvider('provideFinishesCases')]
    public function test_finishes(Period $interval, DateTimeInterface|Period $index, bool $expected): void
    {
        $this->assertSame($expected, $interval->isEndedBy($index));
    }

    /**
     * @return \Iterator<(int | string), array{Period, (Period | \DateTimeInterface), bool}>
     */
    public static function provideFinishesCases(): \Iterator
    {
        $endingDate = Date::parse('2012-01-16');
        $interval = Period::fromDate('2012-01-01', $endingDate);
        yield [
            $interval,
            $interval,
            true,
        ];
        yield [
            $interval->moveEndDate('+ 3 MINUTES'),
            $interval,
            false,
        ];
        yield [
            $interval,
            $interval->boundedBy(Bounds::ExcludeAll),
            true,
        ];
        yield [
            $interval->boundedBy(Bounds::ExcludeAll),
            $interval->boundedBy(Bounds::IncludeAll),
            false,
        ];
        yield [
            $interval->boundedBy(Bounds::ExcludeAll),
            $endingDate,
            false,
        ];
        yield [
            $interval->boundedBy(Bounds::IncludeAll),
            $endingDate,
            true,
        ];
    }

    #[DataProvider('provideEqualsCases')]
    public function test_equals(Period $interval1, Period $interval2, bool $expected): void
    {
        $this->assertSame($expected, $interval1->equals($interval2));
    }

    /**
     * @return \Iterator<string, array{Period, Period, bool}>
     */
    public static function provideEqualsCases(): \Iterator
    {
        yield 'returns true' => [
            Period::fromDate('2012-01-01 00:00:00', '2012-01-03 00:00:00'),
            Period::fromDate('2012-01-01 00:00:00', '2012-01-03 00:00:00'),
            true,
        ];
        yield 'returns false' => [
            Period::fromDate('2012-01-01', '2012-01-15'),
            Period::fromDate('2012-01-01', '2012-01-07'),
            false,
        ];
        yield 'returns false is argument order independent' => [
            Period::fromDate('2012-01-01', '2012-01-07'),
            Period::fromDate('2012-01-01', '2012-01-15'),
            false,
        ];
        yield 'returns false with different range type' => [
            Period::fromDate('2012-01-01', '2012-01-15', Bounds::IncludeAll),
            Period::fromDate('2012-01-01', '2012-01-15', Bounds::ExcludeAll),
            false,
        ];
    }

    public function test_intersect(): void
    {
        $orig = Period::fromDate(
            Date::parse('2011-12-01'), Date::parse('2012-04-01')
        );
        $alt = Period::fromDate(
            Date::parse('2012-01-01'), Date::parse('2012-03-01')
        );
        $this->assertTrue($orig->intersect($alt)->equals(Period::fromDate(
            Date::parse('2012-01-01'), Date::parse('2012-03-01')
        )));
    }

    public function test_intersect_throws_exception_with_no_overlapping_time_range(): void
    {
        $this->expectException(UnprocessableInterval::class);
        $orig = Period::fromDate(
            Date::parse('2013-01-01'), Date::parse('2013-02-01')
        );
        $alt = Period::fromDate(
            Date::parse('2012-01-01'), Date::parse('2012-03-01')
        );
        $orig->intersect($alt);
    }

    #[DataProvider('provideIntersectBoundaryTypeResultCases')]
    public function test_intersect_boundary_type_result(Bounds $boundary1, Bounds $boundary2, Bounds $expected): void
    {
        $interval0 = Period::fromDate(
            Date::parse('2014-03-01'), Date::parse('2014-06-01'), $boundary1
        );
        $interval1 = Period::fromDate(
            Date::parse('2014-05-01'), Date::parse('2014-08-01'), $boundary2
        );

        $this->assertSame($interval0->intersect($interval1)->bounds, $expected);
    }

    /**
     * @return \Iterator<string, array{boundary1: Bounds, boundary2: Bounds, expected: Bounds}>
     */
    public static function provideIntersectBoundaryTypeResultCases(): \Iterator
    {
        yield '() + ()' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::ExcludeAll,
            'expected' => Bounds::ExcludeAll,
        ];
        yield '() + []' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::IncludeAll,
            'expected' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '() + [)' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '() + (]' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected' => Bounds::ExcludeAll,
        ];
        yield '[] + []' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::IncludeAll,
            'expected' => Bounds::IncludeAll,
        ];
        yield '[] + [)' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected' => Bounds::IncludeAll,
        ];
        yield '[] + (]' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '[] + ()' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::ExcludeAll,
            'expected' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '[) + ()' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::ExcludeAll,
            'expected' => Bounds::ExcludeAll,
        ];
        yield '[) + []' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::IncludeAll,
            'expected' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '[) + (]' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected' => Bounds::ExcludeAll,
        ];
        yield '[) + [)' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '(] + ()' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::ExcludeAll,
            'expected' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '(] + []' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::IncludeAll,
            'expected' => Bounds::IncludeAll,
        ];
        yield '(] + (]' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '(] + [)' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected' => Bounds::IncludeAll,
        ];
    }

    public function test_gap(): void
    {
        $orig = Period::fromDate(
            Date::parse('2011-12-01'), Date::parse('2012-02-01')
        );
        $alt = Period::fromDate(
            Date::parse('2012-06-01'), Date::parse('2012-09-01')
        );
        $gap = $orig->gap($alt);

        $this->assertEquals($orig->endDate, $gap->startDate);
        $this->assertEquals($alt->startDate, $gap->endDate);
        $this->assertTrue($gap->equals($alt->gap($orig)));
    }

    public function test_gap_throws_exception_with_overlaps_interval(): void
    {
        $this->expectException(UnprocessableInterval::class);
        $orig = Period::fromDate(
            Date::parse('2011-12-01'), Date::parse('2012-02-01')
        );
        $alt = Period::fromDate(
            Date::parse('2011-12-10'), Date::parse('2011-12-15')
        );
        $orig->gap($alt);
    }

    public function test_gap_with_same_starting_interval(): void
    {
        $this->expectException(UnprocessableInterval::class);
        $orig = Period::fromDate(
            Date::parse('2011-12-01'), Date::parse('2012-02-01')
        );
        $alt = Period::fromDate(
            Date::parse('2011-12-01'), Date::parse('2011-12-15')
        );
        $orig->gap($alt);
    }

    public function test_gap_with_same_ending_interval(): void
    {
        $this->expectException(UnprocessableInterval::class);
        $orig = Period::fromDate(
            Date::parse('2011-12-01'), Date::parse('2012-02-01')
        );
        $alt = Period::fromDate(
            Date::parse('2012-01-15'), Date::parse('2012-02-01')
        );
        $orig->gap($alt);
    }

    public function test_gap_with_adjacent_interval(): void
    {
        $orig = Period::fromDate(
            Date::parse('2011-12-01'), Date::parse('2012-02-01')
        );
        $alt = Period::fromDate(
            Date::parse('2012-02-01'), Date::parse('2012-02-02')
        );
        $this->assertSame(0, $orig->gap($alt)->timeDuration());
    }

    #[DataProvider('provideGapBoundaryTypeResultCases')]
    public function test_gap_boundary_type_result(Bounds $boundary1, Bounds $boundary2, Bounds $expected): void
    {
        $interval0 = Period::fromDate(
            Date::parse('2014-03-01'), Date::parse('2014-06-01'), $boundary1
        );
        $interval1 = Period::fromDate(
            Date::parse('2014-07-01'), Date::parse('2014-09-01'), $boundary2
        );
        $this->assertSame($expected, $interval0->gap($interval1)->bounds);
    }

    /**
     * @return \Iterator<string, array{boundary1: Bounds, boundary2: Bounds, expected: Bounds}>
     */
    public static function provideGapBoundaryTypeResultCases(): \Iterator
    {
        yield '() + ()' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::ExcludeAll,
            'expected' => Bounds::IncludeAll,
        ];
        yield '() + []' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::IncludeAll,
            'expected' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '() + [)' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '() + (]' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected' => Bounds::IncludeAll,
        ];
        yield '[] + []' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::IncludeAll,
            'expected' => Bounds::ExcludeAll,
        ];
        yield '[] + [)' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected' => Bounds::ExcludeAll,
        ];
        yield '[] + (]' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '[] + ()' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::ExcludeAll,
            'expected' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '[) + ()' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::ExcludeAll,
            'expected' => Bounds::IncludeAll,
        ];
        yield '[) + []' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::IncludeAll,
            'expected' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '[) + (]' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected' => Bounds::IncludeAll,
        ];
        yield '[) + [)' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '(] + ()' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::ExcludeAll,
            'expected' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '(] + []' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::IncludeAll,
            'expected' => Bounds::ExcludeAll,
        ];
        yield '(] + (]' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '(] + [)' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected' => Bounds::ExcludeAll,
        ];
    }

    public function test_union(): void
    {
        $interval1 = Period::fromYear(2_015);
        $interval2 = Period::fromYear(2_017);

        $this->assertEquals($interval1->union($interval2), new Sequence($interval1, $interval2));

        $interval1 = Period::fromMonth(2_015, 7);
        $interval2 = Period::fromQuarter(2_015, 3);

        $this->assertEquals($interval1->union($interval2), new Sequence($interval1->merge($interval2)));
    }

    public function test_diff_throws_exception(): void
    {
        $interval1 = Period::fromDate(
            CarbonImmutable::parse('2015-01-01'), CarbonImmutable::parse('2016-01-01')
        );
        $interval2 = Period::fromDate(
            CarbonImmutable::parse('2013-01-01'), CarbonImmutable::parse('2014-01-01')
        );

        $this->expectException(UnprocessableInterval::class);
        $interval1->diff($interval2);
    }

    public function test_diff_with_equals_period(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2013-01-01'), CarbonImmutable::parse('2014-01-01')
        );
        $alt = Period::fromDate(
            CarbonImmutable::parse('2013-01-01'), CarbonImmutable::parse('2014-01-01')
        );

        $this->assertTrue($alt->diff($period)->isEmpty());
        $this->assertEquals($alt->diff($period), $period->diff($alt));
    }

    public function test_diff_with_period_sharing_starting_datepoints(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2013-01-01'), CarbonImmutable::parse('2014-01-01')
        );
        $alt = Period::fromDate(
            CarbonImmutable::parse('2013-01-01'), CarbonImmutable::parse('2013-04-01')
        );
        $sequence = $alt->diff($period);

        $this->assertCount(1, $sequence);
        $this->assertEquals(
            CarbonImmutable::parse('2013-04-01'), $sequence[0]->startDate
        );
        $this->assertEquals(
            CarbonImmutable::parse('2014-01-01'), $sequence[0]->endDate
        );
        $this->assertEquals($alt->diff($period), $period->diff($alt));
    }

    public function test_diff_with_period_sharing_ending_datepoints(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2013-01-01'), CarbonImmutable::parse('2014-01-01')
        );
        $alt = Period::fromDate(
            CarbonImmutable::parse('2013-10-01'), CarbonImmutable::parse('2014-01-01')
        );
        $sequence = $alt->diff($period);

        $this->assertCount(1, $sequence);
        $this->assertEquals(
            CarbonImmutable::parse('2013-01-01'), $sequence[0]->startDate
        );
        $this->assertEquals(
            CarbonImmutable::parse('2013-10-01'), $sequence[0]->endDate
        );
        $this->assertEquals($alt->diff($period), $period->diff($alt));
    }

    public function test_diff_with_overlaps_period(): void
    {
        $period = Period::fromDate(
            CarbonImmutable::parse('2013-01-01 10:00:00'), CarbonImmutable::parse('2013-01-01 13:00:00')
        );
        $alt = Period::fromDate(
            CarbonImmutable::parse('2013-01-01 11:00:00'), CarbonImmutable::parse('2013-01-01 14:00:00')
        );
        $sequence = $alt->diff($period);

        $this->assertCount(2, $sequence);
        $this->assertSame(3_600, $sequence[0]->timeDuration());
        $this->assertSame(3_600, $sequence[1]->timeDuration());
        $this->assertEquals($alt->diff($period), $period->diff($alt));
    }

    #[DataProvider('provideDiffBoundaryTypeResultCases')]
    public function test_diff_boundary_type_result(
        Bounds $boundary1,
        Bounds $boundary2,
        Bounds $expected1,
        Bounds $expected2,
    ): void {
        $interval0 = Period::fromDate(
            CarbonImmutable::parse('2014-03-01'), CarbonImmutable::parse('2014-06-01'), $boundary1
        );
        $interval1 = Period::fromDate(
            CarbonImmutable::parse('2014-05-01'), CarbonImmutable::parse('2014-09-01'), $boundary2
        );
        $sequence = $interval0->diff($interval1);

        if (0 < count($sequence)) {
            $this->assertSame($expected1, $sequence[0]->bounds);
        }

        if (1 >= count($sequence)) {
            return;
        }

        $this->assertSame($expected2, $sequence[1]->bounds);
    }

    /**
     * @return \Iterator<string, array{boundary1: Bounds, boundary2: Bounds, expected1: Bounds, expected2: Bounds}>
     */
    public static function provideDiffBoundaryTypeResultCases(): \Iterator
    {
        yield '() + ()' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::ExcludeAll,
            'expected1' => Bounds::ExcludeStartIncludeEnd,
            'expected2' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '() + []' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::IncludeAll,
            'expected1' => Bounds::ExcludeAll,
            'expected2' => Bounds::IncludeAll,
        ];
        yield '() + [)' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected1' => Bounds::ExcludeAll,
            'expected2' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '() + (]' => [
            'boundary1' => Bounds::ExcludeAll,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected1' => Bounds::ExcludeStartIncludeEnd,
            'expected2' => Bounds::IncludeAll,
        ];
        yield '[] + []' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::IncludeAll,
            'expected1' => Bounds::IncludeStartExcludeEnd,
            'expected2' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '[] + [)' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected1' => Bounds::IncludeStartExcludeEnd,
            'expected2' => Bounds::ExcludeAll,
        ];
        yield '[] + (]' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected1' => Bounds::IncludeAll,
            'expected2' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '[] + ()' => [
            'boundary1' => Bounds::IncludeAll,
            'boundary2' => Bounds::ExcludeAll,
            'expected1' => Bounds::IncludeAll,
            'expected2' => Bounds::ExcludeAll,
        ];
        yield '[) + ()' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::ExcludeAll,
            'expected1' => Bounds::IncludeAll,
            'expected2' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '[) + []' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::IncludeAll,
            'expected1' => Bounds::IncludeStartExcludeEnd,
            'expected2' => Bounds::IncludeAll,
        ];
        yield '[) + (]' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected1' => Bounds::IncludeAll,
            'expected2' => Bounds::IncludeAll,
        ];
        yield '[) + [)' => [
            'boundary1' => Bounds::IncludeStartExcludeEnd,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected1' => Bounds::IncludeStartExcludeEnd,
            'expected2' => Bounds::IncludeStartExcludeEnd,
        ];
        yield '(] + ()' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::ExcludeAll,
            'expected1' => Bounds::ExcludeStartIncludeEnd,
            'expected2' => Bounds::ExcludeAll,
        ];
        yield '(] + []' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::IncludeAll,
            'expected1' => Bounds::ExcludeAll,
            'expected2' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '(] + (]' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::ExcludeStartIncludeEnd,
            'expected1' => Bounds::ExcludeStartIncludeEnd,
            'expected2' => Bounds::ExcludeStartIncludeEnd,
        ];
        yield '(] + [)' => [
            'boundary1' => Bounds::ExcludeStartIncludeEnd,
            'boundary2' => Bounds::IncludeStartExcludeEnd,
            'expected1' => Bounds::ExcludeAll,
            'expected2' => Bounds::ExcludeAll,
        ];
    }

    public function test_diff_and_intersect(): void
    {
        foreach (['[2014-03-01,2014-06-01]', '[2014-03-01,2014-06-01)', '(2014-03-01,2014-06-01)', '(2014-03-01,2014-06-01]'] as $bound1) {
            foreach (['[2014-05-01,2014-08-01]', '[2014-05-01,2014-08-01)', '(2014-05-01,2014-08-01)', '(2014-05-01,2014-08-01]'] as $bound2) {
                $interval0 = Period::fromIso80000('Y-m-d', $bound1);
                $interval1 = Period::fromIso80000('Y-m-d', $bound2);
                $sequence = $interval0->diff($interval1);
                $intersect = $interval0->intersect($interval1);

                if (0 < count($sequence)) {
                    $this->assertTrue($sequence[0]->bordersOnStart($intersect));
                }

                if (1 < count($sequence)) {
                    $this->assertTrue($sequence[1]->bordersOnEnd($intersect));
                }

                $sequence->push($intersect);
                $period = $sequence->length();

                if (!$period instanceof Period) {
                    continue;
                }

                $this->assertTrue($period->equals($interval0->merge($interval1)));
            }
        }
    }

    public function test_overlaps_all_can_returns_a_period(): void
    {
        $period = Period::fromDate(
            Date::parse('2000-02-01'), Date::parse('2000-02-28')
        );
        $sequence = new Sequence(
            Period::fromDate(
                Date::parse('2000-01-12'), Date::parse('2000-02-10')
            ),
            Period::fromDate(
                Date::parse('2000-01-14'), Date::parse('2000-02-03')
            ),
        );
        $overlaps = $period->intersect(...$sequence);

        foreach ($sequence as $item) {
            $this->assertTrue($item->overlaps($overlaps));
        }

        $this->assertTrue($period->overlaps($overlaps));
    }

    public function test_overlaps_all_can_return_null(): void
    {
        $period1 = Period::fromDate(
            Date::parse('2000-02-01'), Date::parse('2000-02-28')
        );
        $period2 = Period::fromDate(
            Date::parse('2000-01-14'), Date::parse('2000-01-23')
        );

        $this->expectException(UnprocessableInterval::class);

        $period1->intersect($period2);
    }

    public function test_subtract_with_overlapping_unequal_periods(): void
    {
        $periodA = Period::after(
            CarbonImmutable::parse('2000-01-01 10:00:00'), DateInterval::createFromDateString('8 HOURS')
        );
        $periodB = Period::after(
            CarbonImmutable::parse('2000-01-01 14:00:00'), DateInterval::createFromDateString('6 HOURS')
        );

        $diff1 = $periodA->subtract($periodB);

        $this->assertCount(1, $diff1);
        $this->assertEquals($periodA->startDate, $diff1[0]->startDate);
        $this->assertEquals($periodB->startDate, $diff1[0]->endDate);

        $diff2 = $periodB->subtract($periodA);

        $this->assertCount(1, $diff2);
        $this->assertEquals($periodA->endDate, $diff2[0]->startDate);
        $this->assertEquals($periodB->endDate, $diff2[0]->endDate);
    }

    public function test_subtract_with_separate_periods(): void
    {
        $periodA = Period::after(
            CarbonImmutable::parse('2000-01-01 10:00:00'), DateInterval::createFromDateString('4 HOURS')
        );
        $periodB = Period::after(
            CarbonImmutable::parse('2000-01-01 15:00:00'), DateInterval::createFromDateString('3 HOURS')
        );

        $diff1 = $periodA->subtract($periodB);

        $this->assertCount(1, $diff1);
        $this->assertTrue($diff1[0]->equals($periodA));

        $diff2 = $periodB->subtract($periodA);

        $this->assertCount(1, $diff2);
        $this->assertTrue($diff2[0]->equals($periodB));
    }

    public function test_subtract_with_one_period_contained_in_another(): void
    {
        $periodA = Period::after(
            CarbonImmutable::parse('2000-01-01 10:00:00'), DateInterval::createFromDateString('8 HOURS')
        );
        $periodB = Period::after(
            CarbonImmutable::parse('2000-01-01 15:00:00'), DateInterval::createFromDateString('1 HOUR')
        );

        $diff1 = $periodA->subtract($periodB);

        $this->assertCount(2, $diff1);
        $this->assertEquals($periodA->startDate, $diff1[0]->startDate);
        $this->assertEquals($periodB->startDate, $diff1[0]->endDate);
        $this->assertEquals($periodB->endDate, $diff1[1]->startDate);
        $this->assertEquals($periodA->endDate, $diff1[1]->endDate);

        $diff2 = $periodB->subtract($periodA);

        $this->assertCount(0, $diff2);
    }

    public function test_subtract_with_equal_period_objec(): void
    {
        $periodA = Period::after(
            CarbonImmutable::parse('2000-01-01 10:00:00'), DateInterval::createFromDateString('8 HOURS')
        );
        $diff = $periodA->subtract($periodA);

        $this->assertCount(0, $diff);
        $this->assertEquals($diff, $periodA->subtract($periodA));
    }

    #[DataProvider('provideMeetsCases')]
    public function test_meets(Period $period1, Period $period2, bool $meets, bool $meetsOnStart, bool $meetsOnEnd): void
    {
        $this->assertSame($meets, $period1->meets($period2));
        $this->assertSame($meetsOnStart, $period1->meetsOnStart($period2));
        $this->assertSame($meetsOnEnd, $period1->meetsOnEnd($period2));
    }

    /**
     * @return iterable<array{period1:Period, period2:Period, meets:bool, meetsOnStart:bool, meetsOnEnd:bool}>
     */
    public static function provideMeetsCases(): iterable
    {
        yield [
            'period1' => Period::fromDate('2022-01-01', '2022-02-01', Bounds::IncludeAll),
            'period2' => Period::fromDate('2022-02-01', '2022-03-01', Bounds::IncludeAll),
            'meets' => true,
            'meetsOnStart' => true,
            'meetsOnEnd' => false,
        ];

        yield [
            'period1' => Period::fromDate('2022-01-01', '2022-02-01', Bounds::ExcludeAll),
            'period2' => Period::fromDate('2022-02-01', '2022-03-01', Bounds::ExcludeAll),
            'meets' => false,
            'meetsOnStart' => false,
            'meetsOnEnd' => false,
        ];

        yield [
            'period1' => Period::fromDate('2022-01-01', '2022-02-01', Bounds::IncludeAll),
            'period2' => Period::fromDate('2022-02-01', '2022-03-01', Bounds::IncludeStartExcludeEnd),
            'meets' => true,
            'meetsOnStart' => true,
            'meetsOnEnd' => false,
        ];

        yield [
            'period1' => Period::fromDate('2022-01-01', '2022-02-01', Bounds::ExcludeStartIncludeEnd),
            'period2' => Period::fromDate('2022-02-01', '2022-03-01', Bounds::IncludeStartExcludeEnd),
            'meets' => true,
            'meetsOnStart' => true,
            'meetsOnEnd' => false,
        ];

        yield [
            'period1' => Period::fromDate('2022-02-01', '2022-03-01', Bounds::IncludeStartExcludeEnd),
            'period2' => Period::fromDate('2022-01-01', '2022-02-01', Bounds::ExcludeStartIncludeEnd),
            'meets' => true,
            'meetsOnStart' => false,
            'meetsOnEnd' => true,
        ];

        yield [
            'period1' => Period::fromDate('2022-01-01', '2022-02-01', Bounds::ExcludeStartIncludeEnd),
            'period2' => Period::fromDate('2022-02-01', '2022-03-01', Bounds::ExcludeStartIncludeEnd),
            'meets' => false,
            'meetsOnStart' => false,
            'meetsOnEnd' => false,
        ];
    }
}
