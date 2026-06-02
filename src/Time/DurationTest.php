<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Date;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use const PHP_INT_MAX;

use function json_encode;
use function mb_ltrim;
use function mb_substr;
use function serialize;
use function str_starts_with;
use function unserialize;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
#[CoversClass(InvalidDuration::class)]
#[CoversClass(Duration::class)]
#[CoversClass(DurationNotation::class)]
#[CoversClass(Unit::class)]
final class DurationTest extends TestCase
{
    public function test_parse_microseconds(): void
    {
        $duration = Duration::of(weeks: 5, days: 6, hours: 2, minutes: 15, seconds: 42, microseconds: 123_456);

        $this->assertSame(5, $duration->weeksCount);
        $this->assertSame(41, $duration->daysCount);
        $this->assertSame(2 + (41 * 24), $duration->hours);
        $this->assertSame(15, $duration->minutes);
        $this->assertSame(42, $duration->seconds);
        $this->assertSame(123_456, $duration->microseconds);
        $this->assertSame(1, $duration->sign);
        $this->assertSame(3_550_542_123_456, $duration->total(Unit::Microsecond));
        $this->assertSame('5w6d2h15m42s123456µs', $duration->toNotation(DurationNotation::Compact));
    }

    public function test_parse_negative_microseconds(): void
    {
        $duration = Duration::of(microseconds: 1_500_000)->negated();

        $this->assertSame(0, $duration->hours);
        $this->assertSame(0, $duration->minutes);
        $this->assertSame(1, $duration->seconds);
        $this->assertSame(500_000, $duration->microseconds);
        $this->assertSame(-1, $duration->sign);
        $this->assertSame('-1s500000µs', $duration->toNotation(DurationNotation::Compact));
    }

    public function test_format_microseconds_without_fraction(): void
    {
        $this->assertSame('09:25:00', Duration::of(hours: 9, minutes: 25)->toNotation(DurationNotation::Chrono));
    }

    public function test_format_microseconds_with_fraction(): void
    {
        $this->assertSame('01:02:03.000045', Duration::of(hours: 1, minutes: 2, seconds: 3, microseconds: 45)->toNotation(DurationNotation::Chrono));
    }

    public function test_format_negative_microseconds(): void
    {
        $this->assertSame('-04:05:06', Duration::of(hours: 4, minutes: 5, seconds: 6)->negated()->toNotation(DurationNotation::Chrono));
    }

    public function test_microseconds_to_date_interval(): void
    {
        $interval = Duration::of(hours: 27, minutes: 12, seconds: 5, microseconds: 123_456)->toDateInterval();

        $this->assertSame(1, $interval->d);
        $this->assertSame(3, $interval->h);
        $this->assertSame(12, $interval->i);
        $this->assertSame(5, $interval->s);
        $this->assertSame(0, $interval->invert);
        $this->assertFalse($interval->days);

        $this->assertEqualsWithDelta(0.123_456, $interval->f, 0.000_001);
    }

    public function test_microseconds_to_date_interval_with_date_reference(): void
    {
        $interval = Duration::of(hours: 27, minutes: 12, seconds: 5, microseconds: 123_456)->toDateInterval(
            Date::now(),
        );

        $this->assertSame(1, $interval->d);
        $this->assertSame(3, $interval->h);
        $this->assertSame(12, $interval->i);
        $this->assertSame(5, $interval->s);
        $this->assertSame(0, $interval->invert);
        $this->assertSame(1, $interval->days);

        $this->assertEqualsWithDelta(0.123_456, $interval->f, 0.000_001);
    }

    public function test_negative_microseconds_to_date_interval(): void
    {
        $interval = Duration::of(microseconds: 5_000_000)->negated()->toDateInterval();

        $this->assertSame(1, $interval->invert);
        $this->assertSame(5, $interval->s);
    }

    public function test_to_date_interval_with_relative_date(): void
    {
        $duration = Duration::of(weeks: 5, minutes: 32, seconds: 23, microseconds: 456)->negated();
        $pureInterval = $duration->toDateInterval();
        $relativeInterval = $duration->toDateInterval(
            new DateTimeImmutable(datetime: '2024-01-27', timezone: new DateTimeZone('UTC')),
        );

        $this->assertFalse($pureInterval->days);
        $this->assertSame(35, $relativeInterval->days);
        $this->assertNotEquals($pureInterval->days, $relativeInterval->days);
    }

    public function test_zero_microseconds(): void
    {
        $duration = Duration::of();

        $this->assertSame('00:00:00', $duration->toNotation(DurationNotation::Chrono));
        $this->assertSame(0, $duration->hours);
        $this->assertSame(0, $duration->minutes);
        $this->assertSame(0, $duration->seconds);
        $this->assertSame(0, $duration->microseconds);
        $this->assertSame(0, $duration->daysCount);
        $this->assertSame(0, $duration->weeksCount);
        $this->assertSame(0, $duration->sign);
        $this->assertSame('0s', $duration->toNotation(DurationNotation::Compact));
        $this->assertTrue($duration->isZero());
        $this->assertEquals($duration, Duration::zero());
    }

    public function test_add_returns_new_instance(): void
    {
        $a = Duration::of(hours: 1);
        $b = Duration::of(minutes: 30);

        $this->assertNotSame($a, $a->sum($b));
    }

    public function test_add_single_duration(): void
    {
        $a = Duration::of(hours: 1);
        $b = Duration::of(minutes: 30);

        $this->assertSame('01:30:00', $a->sum($b)->toNotation(DurationNotation::Chrono));
    }

    public function test_add_multiple_durations(): void
    {
        $base = Duration::of(hours: 1);
        $result = $base->sum(
            Duration::of(minutes: 30),
            Duration::of(seconds: 45),
            Duration::of(microseconds: 123_456),
        );

        $this->assertSame('01:30:45.123456', $result->toNotation(DurationNotation::Chrono));
    }

    public function test_add_negative_duration(): void
    {
        $a = Duration::of(hours: 5);
        $b = Duration::of(hours: 2)->negated();

        $this->assertSame('03:00:00', $a->sum($b)->toNotation(DurationNotation::Chrono));
    }

    public function test_add_result_can_be_negative(): void
    {
        $a = Duration::of(hours: 1);
        $b = Duration::of(hours: 3)->negated();

        $this->assertSame('-02:00:00', $a->sum($b)->toNotation(DurationNotation::Chrono));
    }

    public function test_add_without_arguments_returns_equal_duration(): void
    {
        $duration = Duration::of(hours: 2);

        $this->assertSame($duration, $duration->sum());
    }

    public function test_add_preserves_microseconds(): void
    {
        $a = Duration::of(microseconds: 500_000);
        $b = Duration::of(microseconds: 250_000);

        $this->assertSame('00:00:00.750000', $a->sum($b)->toNotation(DurationNotation::Chrono));
    }

    public function test_abs_negate(): void
    {
        $duration = Duration::of(microseconds: 500_000)->negated();

        $this->assertEquals($duration, $duration->abs()->negated());
    }

    #[DataProvider('provideTo_iso8601Cases')]
    public function test_to_iso8601(int $microseconds, string $expected): void
    {
        $duration = 0 > $microseconds
            ? Duration::of(microseconds: -$microseconds)->negated()
            : Duration::of(microseconds: $microseconds);

        $this->assertSame($expected, $duration->toNotation());
    }

    /**
     * @return iterable<non-empty-string, array{0:int, 1:string}>
     */
    public static function provideTo_iso8601Cases(): iterable
    {
        yield 'zero duration' => [0, 'PT0S'];

        yield 'one second' => [1_000_000, 'PT1S'];

        yield 'one minute' => [60_000_000, 'PT1M'];

        yield 'one hour' => [3_600_000_000, 'PT1H'];

        yield 'hours minutes seconds' => [3_661_000_000, 'PT1H1M1S'];

        yield 'fractional seconds' => [3_661_500_000, 'PT1H1M1.5S'];

        yield 'microseconds precision' => [3_661_000_123, 'PT1H1M1.000123S'];

        yield 'sub second only' => [123, 'PT0.000123S'];

        yield 'trim trailing zeros' => [1_500_000, 'PT1.5S'];

        yield 'negative fractional duration' => [-1_500_000, '-PT1.5S'];

        yield 'negative complex duration' => [-3_661_000_123, '-PT1H1M1.000123S'];

        yield '24 hours duration' => [86_400_000_000, 'P1D'];
    }

    #[DataProvider('provideTruncate_to_precisionCases')]
    public function test_truncate_to_precision(
        int $microseconds,
        Unit $precision,
        int $expectedMicroseconds,
    ): void {
        $duration = 0 > $microseconds
            ? Duration::of(microseconds: -$microseconds)->negated()
            : Duration::of(microseconds: $microseconds);

        $this->assertSame(
            $expectedMicroseconds,
            $duration
                ->roundTo($precision, RoundingMode::Floor)
                ->total(Unit::Microsecond),
        );
    }

    /**
     * @return iterable<non-empty-string, array{0: int, 1: Unit, 2: int}>
     */
    public static function provideTruncate_to_precisionCases(): iterable
    {
        // 1h 1m 1s + 500ms = 3_661_500_000 µs
        yield 'truncate to seconds removes microseconds' => [
            3_661_500_000,
            Unit::Second,
            3_661_000_000,
        ];

        yield 'truncate to minutes removes seconds and microseconds' => [
            3_661_500_000,
            Unit::Minute,
            3_660_000_000,
        ];

        yield 'truncate to hours removes minutes seconds and microseconds' => [
            3_661_500_000,
            Unit::Hour,
            3_600_000_000,
        ];

        yield 'zero duration stays zero' => [
            0,
            Unit::Second,
            0,
        ];

        yield 'already clean seconds unchanged' => [
            1_000_000,
            Unit::Second,
            1_000_000,
        ];

        yield 'negative duration is preserved when inverted' => [
            -3_661_500_000,
            Unit::Minute,
            -3_660_000_000,
        ];
    }

    /**
     * @throws InvalidDuration
     */
    #[DataProvider('provideTruncate_is_immutableCases')]
    public function test_truncate_is_immutable(
        int $microseconds,
        Unit $precision,
    ): void {
        $duration = 0 > $microseconds
            ? Duration::of(microseconds: -$microseconds)->negated()
            : Duration::of(microseconds: $microseconds);

        $result = $duration->roundTo($precision, RoundingMode::Floor);

        $this->assertNotSame($duration, $result);
    }

    /**
     * @return iterable<array{0: non-negative-int, 1: Unit}>
     */
    public static function provideTruncate_is_immutableCases(): iterable
    {
        yield [3_661_500_000, Unit::Second];

        yield [3_661_500_000, Unit::Minute];
        // yield [-3_661_500_000, Unit::Hour];
    }

    public function test_truncate_preserves_sign_consistency(): void
    {
        $positive = Duration::of(microseconds: 3_661_500_000);
        $negative = Duration::of(microseconds: 3_661_500_000)->negated();

        $this->assertGreaterThan(0, $positive->roundTo(Unit::Minute, RoundingMode::Floor)->total(Unit::Microsecond));
        $this->assertLessThan(0, $negative->roundTo(Unit::Minute, RoundingMode::Floor)->total(Unit::Microsecond));
    }

    public function test_it_can_not_invert_php_int_max(): void
    {
        $this->expectException(InvalidDuration::class);
        $this->expectExceptionMessage('The duration exceeds the supported range.');

        Duration::of(microseconds: PHP_INT_MAX)->negated();
    }

    /*
     * -------------------------------------------------
     * compareTo
     * -------------------------------------------------
     */

    #[DataProvider('provideCompare_toCases')]
    public function test_compare_to(
        Duration $left,
        Duration $right,
        int $expected,
    ): void {
        $this->assertSame($expected, $left->compareTo($right));
    }

    /**
     * @throws InvalidDuration
     * @return iterable<non-empty-string, array{0: Duration, 1: Duration}>
     */
    public static function provideCompare_toCases(): iterable
    {
        yield 'equal durations' => [
            Duration::of(hours: 1),
            Duration::of(minutes: 60),
            0,
        ];

        yield 'lesser duration' => [
            Duration::of(minutes: 30),
            Duration::of(hours: 1),
            -1,
        ];

        yield 'greater duration' => [
            Duration::of(hours: 2),
            Duration::of(hours: 1),
            1,
        ];

        yield 'negative vs positive' => [
            Duration::of(hours: 1)->negated(),
            Duration::of(hours: 1),
            -1,
        ];
    }

    /*
     * -------------------------------------------------
     * equals
     * -------------------------------------------------
     */

    public function test_equals_returns_true_for_equal_duration(): void
    {
        $this->assertTrue(Duration::of(hours: 1)->equals(Duration::of(minutes: 60)));
    }

    public function test_equals_returns_false_for_different_duration(): void
    {
        $this->assertFalse(Duration::of(hours: 1)->equals(Duration::of(minutes: 59)));
    }

    /*
     * -------------------------------------------------
     * isGreaterThan
     * -------------------------------------------------
     */

    public function test_is_greater_than(): void
    {
        $this->assertTrue(Duration::of(hours: 2)->isLongerThan(Duration::of(hours: 1)));
        $this->assertFalse(Duration::of(hours: 1)->isLongerThan(Duration::of(hours: 2)));
    }

    /*
     * -------------------------------------------------
     * isGreaterThanOrEqual
     * -------------------------------------------------
     */

    public function test_is_greater_than_or_equal(): void
    {
        $this->assertTrue(Duration::of(hours: 2)->isLongerThanOrEqual(Duration::of(hours: 1)));
        $this->assertTrue(Duration::of(hours: 1)->isLongerThanOrEqual(Duration::of(minutes: 60)));
        $this->assertFalse(Duration::of(minutes: 30)->isLongerThanOrEqual(Duration::of(hours: 1)));
    }

    /*
     * -------------------------------------------------
     * isLesserThan
     * -------------------------------------------------
     */

    public function test_is_lesser_than(): void
    {
        $this->assertTrue(Duration::of(minutes: 30)->isShorterThan(Duration::of(hours: 1)));
        $this->assertFalse(Duration::of(hours: 2)->isShorterThan(Duration::of(hours: 1)));
    }

    /*
     * -------------------------------------------------
     * isLesserThanOrEqual
     * -------------------------------------------------
     */

    public function test_is_lesser_than_or_equal(): void
    {
        $this->assertTrue(Duration::of(minutes: 30)->isShorterThanOrEqual(Duration::of(hours: 1)));
        $this->assertTrue(Duration::of(hours: 1)->isShorterThanOrEqual(Duration::of(minutes: 60)));
        $this->assertFalse(Duration::of(hours: 2)->isShorterThanOrEqual(Duration::of(hours: 1)));
    }

    /**
     * @param non-negative-int $hours
     * @param non-negative-int $minutes
     * @param non-negative-int $seconds
     * @param non-negative-int $microseconds
     *
     * @throws InvalidDuration
     */
    #[DataProvider('provideIncrementCases')]
    public function test_increment(
        Duration $initial,
        int $hours,
        int $minutes,
        int $seconds,
        int $microseconds,
        string $expected,
    ): void {
        $result = $initial->increase(hours: $hours, minutes: $minutes, seconds: $seconds, microseconds: $microseconds);

        $this->assertSame($expected, $result->toNotation(DurationNotation::Chrono));
    }

    /**
     * @throws InvalidDuration
     * @return iterable<non-empty-string, array{0: Duration, 1: ?int, 2: ?int, 3: ?int, 4: ?int, 5: non-empty-string}>
     */
    public static function provideIncrementCases(): iterable
    {
        $base = Duration::of(
            hours: 12,
            minutes: 34,
            seconds: 56,
            microseconds: 123_456,
        );

        yield 'replace hours' => [
            $base,
            1,
            0,
            0,
            0,
            '13:34:56.123456',
        ];

        yield 'replace minutes' => [
            $base,
            0,
            10,
            0,
            0,
            '12:44:56.123456',
        ];

        yield 'replace seconds' => [
            $base,
            0,
            0,
            5,
            0,
            '12:35:01.123456',
        ];

        yield 'replace microseconds' => [
            $base,
            0,
            0,
            0,
            1,
            '12:34:56.123457',
        ];

        yield 'replace multiple values' => [
            $base,
            1,
            2,
            3,
            4,
            '13:36:59.123460',
        ];
    }

    public function test_increment_preserves_original_instance(): void
    {
        $duration = Duration::of(hours: 10);
        $modified = $duration->increase(hours: 5);

        $this->assertSame('10:00:00', $duration->toNotation(DurationNotation::Chrono));
        $this->assertSame('15:00:00', $modified->toNotation(DurationNotation::Chrono));
    }

    public function test_decrement_preserves_original_instance(): void
    {
        $duration = Duration::of(hours: 10);
        $modified = $duration->decrease(hours: 5);

        $this->assertSame('10:00:00', $duration->toNotation(DurationNotation::Chrono));
        $this->assertSame('05:00:00', $modified->toNotation(DurationNotation::Chrono));
    }

    public function test_increment_returns_same_instance_when_called_without_arguments(): void
    {
        $duration = Duration::of(hours: 1);

        $this->assertSame($duration, $duration->increase());
    }

    public function test_decrement_returns_same_instance_when_called_without_arguments(): void
    {
        $duration = Duration::of(hours: 1);

        $this->assertSame($duration, $duration->decrease());
    }

    public function test_it_parses_simple_minutes(): void
    {
        $duration = Duration::fromNotation('PT30M', DurationNotation::Iso8601);

        $this->assertSame('PT30M', $duration->toNotation());
    }

    public function test_it_parses_hours_minutes_seconds(): void
    {
        $duration = Duration::fromNotation('PT1H30M15S', DurationNotation::Iso8601);

        $this->assertSame('PT1H30M15S', $duration->toNotation());
    }

    public function test_it_parses_fractional_seconds(): void
    {
        $duration = Duration::fromNotation('PT0.5S', DurationNotation::Iso8601);

        $this->assertSame('PT0.5S', $duration->toNotation());
    }

    public function test_it_parses_days(): void
    {
        $duration = Duration::fromNotation('P2DT3H', DurationNotation::Iso8601);

        $this->assertSame('P2DT3H', $duration->toNotation());
    }

    public function test_it_parses_negative_duration(): void
    {
        $duration = Duration::fromNotation('-PT30S', DurationNotation::Iso8601);

        $this->assertSame('-PT30S', $duration->toNotation());
    }

    public function test_it_parse_and_normalize_duration(): void
    {
        $rawIso8601 = '-PT25H0.5S';
        $duration = Duration::fromNotation($rawIso8601, DurationNotation::Iso8601);

        $this->assertNotSame($rawIso8601, $duration->toNotation());
        $this->assertSame('-P1DT1H0.5S', $duration->toNotation());
        $this->assertSame(1, $duration->daysCount);
    }

    public function test_it_rejects_years(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromNotation('P1Y', DurationNotation::Iso8601);
    }

    public function test_it_rejects_months(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromNotation('P1M', DurationNotation::Iso8601);
    }

    public function test_it_rejects_empty_time_designator(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromNotation('PT', DurationNotation::Iso8601);
    }

    public function test_it_rejects_completely_invalid_string(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromNotation('invalid', DurationNotation::Iso8601);
    }

    public function test_it_parses_weeks(): void
    {
        $duration = Duration::fromNotation('P2W', DurationNotation::Iso8601);

        $this->assertSame('P14D', $duration->toNotation());
    }

    public function test_it_parses_weeks_and_days(): void
    {
        $duration = Duration::fromNotation('P1W2D', DurationNotation::Iso8601);

        $this->assertSame('P9D', $duration->toNotation());
        $this->assertSame(9, $duration->daysCount);
    }

    public function test_it_parses_negative_weeks(): void
    {
        $duration = Duration::fromNotation('-P3W', DurationNotation::Iso8601);

        $this->assertSame('-P21D', $duration->toNotation());
    }

    public function test_it_parses_weeks_with_time_components(): void
    {
        $duration = Duration::fromNotation('P1WT2H30M', DurationNotation::Iso8601);

        $this->assertSame('P7DT2H30M', $duration->toNotation());
    }

    public function test_it_rejects_empty_week_notation(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromNotation('PW', DurationNotation::Iso8601);
    }

    public function test_predefined_instances(): void
    {
        $max = Duration::max();
        $min = Duration::min();
        $zero = Duration::zero();

        $this->assertTrue($max->isLongerThan($min));
        $this->assertTrue($max->isLongerThan($zero));
        $this->assertTrue($zero->isLongerThanOrEqual($min));
        $this->assertTrue($min->isShorterThan($zero));
    }

    public function test_it_rejects_invalid_multiply(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::max()->multipliedBy(3);
    }

    public function test_it_rejects_divide_by_zero(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::max()->dividedBy(0);
    }

    public function test_it_multiply_the_duration(): void
    {
        $this->assertSame('PT4H', Duration::of(hours: 2)->multipliedBy(2)->toNotation());
        $this->assertSame('PT4M', Duration::of(minutes: 2)->multipliedBy(2)->toNotation());
    }

    public function test_duration_can_be_serialized_and_unserialized(): void
    {
        $duration = Duration::fromNotation('-PT23H30S', DurationNotation::Iso8601);
        $restored = unserialize(serialize($duration));

        $this->assertInstanceOf(Duration::class, $restored);
        $this->assertEquals($duration, $restored);
    }

    public function test_duration_can_be_json_serialized(): void
    {
        $duration = Duration::of(hours: 2, seconds: 35);

        $this->assertSame('"PT2H35S"', json_encode($duration));
    }

    #[DataProvider('provideRound_toCases')]
    public function test_round_to(int $input, Unit $precision, int $expected): void
    {
        $duration = 0 > $input
            ? Duration::of(microseconds: -$input)->negated()
            : Duration::of(microseconds: $input);

        $this->assertSame($expected, $duration->roundTo($precision)->total(Unit::Microsecond));
    }

    /**
     * @return Iterator<non-empty-string, array{int, Unit, int}>
     */
    public static function provideRound_toCases(): iterable
    {
        // [input microseconds, precision, expected microseconds]
        // seconds
        yield 'round down seconds' => [1_499_999, Unit::Second, 1_000_000];

        yield 'round up seconds' => [1_500_000, Unit::Second, 2_000_000];

        yield 'exact seconds' => [2_000_000, Unit::Second, 2_000_000];

        // minutes
        yield 'round down minutes' => [89_000_000, Unit::Minute, 60_000_000];

        yield 'round up minutes' => [91_000_000, Unit::Minute, 120_000_000];

        // hours
        yield 'round hours' => [3_500_000_000, Unit::Hour, 3_600_000_000];

        // days
        yield 'round days' => [86_000_000_000, Unit::Day, 86_400_000_000];

        // negative values
        yield 'negative round up' => [-1_500_000, Unit::Second, -2_000_000];

        yield 'negative round down' => [-1_499_999, Unit::Second, -1_000_000];

        // micro boundary (identity case)
        yield 'micro unchanged' => [999, Unit::Microsecond, 999];
    }

    /**
     * @param list<Duration> $durations
     *
     * @throws InvalidTime
     */
    #[DataProvider('provideMin_ofCases')]
    public function test_min_of(array $durations, Duration $expected): void
    {
        $this->assertTrue(Duration::minOf(...$durations)->equals($expected));
    }

    /**
     * @throws InvalidDuration
     * @return Iterator<non-empty-string, array{list<Duration>, Duration}>
     */
    public static function provideMin_ofCases(): iterable
    {
        yield 'simple case' => [
            [
                Duration::of(seconds: 10),
                Duration::of(seconds: 5),
                Duration::of(seconds: 8),
            ],
            Duration::of(seconds: 5),
        ];

        yield 'mixed units' => [
            [
                Duration::of(minutes: 1),
                Duration::of(seconds: 30),
                Duration::of(seconds: 90),
            ],
            Duration::of(seconds: 30),
        ];
    }

    /**
     * @param list<Duration> $durations
     *
     * @throws InvalidTime
     */
    #[DataProvider('provideMax_ofCases')]
    public function test_max_of(array $durations, Duration $expected): void
    {
        $this->assertTrue(Duration::maxOf(...$durations)->equals($expected));
    }

    /**
     * @throws InvalidDuration
     * @return Iterator<non-empty-string, array{list<Duration>, Duration}>
     */
    public static function provideMax_ofCases(): iterable
    {
        yield 'simple case' => [
            [
                Duration::of(seconds: 10),
                Duration::of(seconds: 5),
                Duration::of(seconds: 8),
            ],
            Duration::of(seconds: 10),
        ];
    }

    #[DataProvider('provideClampCases')]
    public function test_clamp(Duration $value, Duration $min, Duration $max, Duration $expected): void
    {
        $this->assertTrue($value->clamp($min, $max)->equals($expected));
    }

    /**
     * @throws InvalidDuration
     * @return Iterator<non-empty-string, list<Duration>>
     */
    public static function provideClampCases(): iterable
    {
        yield 'below range' => [
            Duration::of(seconds: 2),
            Duration::of(seconds: 5),
            Duration::of(seconds: 10),
            Duration::of(seconds: 5),
        ];

        yield 'above range' => [
            Duration::of(seconds: 20),
            Duration::of(seconds: 5),
            Duration::of(seconds: 10),
            Duration::of(seconds: 10),
        ];

        yield 'inside range' => [
            Duration::of(seconds: 7),
            Duration::of(seconds: 5),
            Duration::of(seconds: 10),
            Duration::of(seconds: 7),
        ];

        yield 'edge boundaries' => [
            Duration::of(seconds: 5),
            Duration::of(seconds: 5),
            Duration::of(seconds: 10),
            Duration::of(seconds: 5),
        ];
    }

    #[DataProvider('provideFrom_date_interval_converts_correctlyCases')]
    public function test_from_date_interval_converts_correctly(DateInterval $interval, int $expectedMicroseconds): void
    {
        $this->assertSame($expectedMicroseconds, Duration::fromDateInterval($interval)->total(Unit::Microsecond));
    }

    /**
     * @return Iterator<non-empty-string, array{interval: DateInterval, expectedMicroseconds: int}>
     */
    public static function provideFrom_date_interval_converts_correctlyCases(): iterable
    {
        yield 'simple positive' => [
            'interval' => new DateInterval('P1DT2H3M4S'),
            'expectedMicroseconds' => (86_400 + (2 * 3_600) + (3 * 60) + 4) * 1_000_000,
        ];

        yield 'negative interval' => [
            'interval' => self::diff('-PT1H30M'),
            'expectedMicroseconds' => -(3_600 + (30 * 60)) * 1_000_000,
        ];

        yield 'with microseconds' => [
            'interval' => self::fromSpec('PT0S', 500_000),
            'expectedMicroseconds' => 500_000,
        ];

        yield 'days from diff (days populated)' => [
            'interval' => self::diff('P2D'),
            'expectedMicroseconds' => -2 * 86_400 * 1_000_000,
        ];
    }

    #[DataProvider('provideFrom_date_interval_throws_for_invalid_intervalsCases')]
    public function test_from_date_interval_throws_for_invalid_intervals(DateInterval $interval): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromDateInterval($interval);
    }

    /**
     * @return Iterator<non-empty-string, array{DateInterval}>
     */
    public static function provideFrom_date_interval_throws_for_invalid_intervalsCases(): iterable
    {
        yield 'has years' => [
            new DateInterval('P1Y'),
        ];

        yield 'has months' => [
            new DateInterval('P2M'),
        ];

        yield 'years and days mixed' => [
            new DateInterval('P1Y2DT3H'),
        ];
    }

    public function test_diffrent_date_intervals(): void
    {
        $a = self::diff('P3DT4H');
        $b = new DateInterval('P3DT4H');

        $this->assertNotEquals(Duration::fromDateInterval($a), Duration::fromDateInterval($b));
    }

    public function test_diff_different_date_intervals_when_deterministic(): void
    {
        $nonDeterministic = new DateInterval('P1M1D');
        $a = CarbonImmutable::parse('2025-05-03 12:34:56');
        $b = $a->add($nonDeterministic);

        $duration = Duration::fromDateInterval($a->diff($b));
        $this->assertTrue($duration->isLongerThan(Duration::of(days: 30)));

        $this->expectException(InvalidDuration::class);
        Duration::fromDateInterval($nonDeterministic);
    }

    /**
     * @param null|non-negative-int $milliseconds
     *
     * @throws InvalidDuration
     */
    #[DataProvider('provideClock_factoryCases')]
    public function test_clock_factory(string $data, int $seconds, ?int $milliseconds = 0): void
    {
        $duration = 0 > $seconds
            ? Duration::of(seconds: -$seconds, milliseconds: $milliseconds ?? 0)->negated()
            : Duration::of(seconds: $seconds, milliseconds: $milliseconds ?? 0);

        $this->assertTrue(Duration::fromNotation($data, DurationNotation::Chrono)->equals($duration));
    }

    /**
     * @return Iterator<non-empty-string, array{0: non-empty-string, 1: int, 2?: int}>
     */
    public static function provideClock_factoryCases(): iterable
    {
        yield 'zero' => ['00:00:00', 0];

        yield 'simple' => ['01:02:03', 3_723];

        yield 'midnight edge' => ['00:00:01', 1];

        yield 'large hours' => ['100:00:00', 360_000];

        yield 'microseconds' => ['01:02:03.500000', 3_723, 500];
    }

    #[DataProvider('provideInvalid_clock_factoryCases')]
    public function test_invalid_clock_factory(string $value): void
    {
        $this->expectException(InvalidDuration::class);
        Duration::fromNotation($value, DurationNotation::Chrono);
    }

    /**
     * @return Iterator<non-empty-string, array{string}>
     */
    public static function provideInvalid_clock_factoryCases(): iterable
    {
        yield 'mm:ss format' => ['12:34'];

        yield 'too many parts' => ['01:02:03:04'];

        yield 'missing seconds' => ['01:02'];

        yield 'invalid seconds' => ['01:02:60'];

        yield 'invalid minutes' => ['01:60:59'];

        yield 'invalid microseconds' => ['01:59:59.10000000'];

        yield 'letters' => ['aa:bb:cc'];

        yield 'empty' => [''];

        yield 'wrong separator' => ['01-02-03'];
    }

    /**
     * @param null|non-negative-int $microseconds
     *
     * @throws InvalidDuration
     */
    #[DataProvider('provideCompact_factoryCases')]
    public function test_compact_factory(string $value, int $seconds, ?int $microseconds = 0): void
    {
        $duration = 0 > $seconds
            ? Duration::of(seconds: -$seconds, microseconds: $microseconds ?? 0)->negated()
            : Duration::of(seconds: $seconds, microseconds: $microseconds ?? 0);

        $this->assertTrue(Duration::fromNotation($value, DurationNotation::Compact)->equals($duration));
    }

    /**
     * @return Iterator<non-empty-string, array{0: non-empty-string, 1: int, 2?: int}>
     */
    public static function provideCompact_factoryCases(): iterable
    {
        yield 'seconds only' => ['5s', 5];

        yield 'minutes seconds' => ['1m 30s', 90];

        yield 'hours' => ['2h', 7_200];

        yield 'full' => ['1w 2d 3h 4m 5s', 788_645];

        yield 'whitespace flexible' => ['1w   3h    5s', 604_800 + 3 * 3_600 + 5];

        yield 'microseconds' => ['1s 250µs', 1, 250];

        yield 'microseconds with u instead of micron' => ['1s 250us', 1,  250];

        yield 'negative' => ['-1h 30m', -5_400];

        yield 'zero' => ['0s', 0];
    }

    #[DataProvider('provideInvalid_compact_factoryCases')]
    public function test_invalid_compact_factory(string $value): void
    {
        $this->expectException(InvalidDuration::class);
        Duration::fromNotation($value, DurationNotation::Compact);
    }

    /**
     * @return Iterator<non-empty-string, array{string}>
     */
    public static function provideInvalid_compact_factoryCases(): iterable
    {
        yield 'empty string' => [''];

        yield 'wrong order' => ['3h 1w'];

        yield 'duplicate unit' => ['1w 2w'];

        yield 'clock format forbidden' => ['12:34:56'];

        yield 'partial clock forbidden' => ['12:34'];

        yield 'unknown unit' => ['10x'];

        yield 'letters only' => ['abc'];

        yield 'missing number' => ['h 10m'];

        yield 'bad spacing unit' => ['10 ms'];
    }

    private static function diff(string $spec): CarbonInterval
    {
        $now = CarbonImmutable::now();

        return $now->add(
            new DateInterval(mb_ltrim($spec, '-')),
        )->diff($now);
    }

    private static function fromSpec(string $spec, int $microseconds): DateInterval
    {
        $sign = 0;

        if (str_starts_with($spec, '-')) {
            $spec = mb_substr($spec, 1);
            $sign = 1;
        }

        $interval = new DateInterval($spec);

        if (1 === $sign) {
            $interval->invert = 1;
        }

        if (0 !== $microseconds) {
            $interval->f = $microseconds / 1_000_000;
        }

        return $interval;
    }
}
