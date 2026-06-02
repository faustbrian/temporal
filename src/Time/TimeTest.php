<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use Carbon\CarbonImmutable;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function serialize;
use function unserialize;

/**
 * @internal
 */
#[CoversClass(InvalidTime::class)]
#[CoversClass(Time::class)]
#[CoversClass(TimeFormat::class)]
#[CoversClass(Unit::class)]
final class TimeTest extends TestCase
{
    /*
     * -------------------------------------------------
     * Creation
     * -------------------------------------------------
     */

    public function test_from_parts_creates_correct_time(): void
    {
        $time = Time::at(10, 15, 30, 123_456);

        $this->assertSame(10, $time->hour);
        $this->assertSame(15, $time->minute);
        $this->assertSame(30, $time->second);
        $this->assertSame(123_456, $time->microsecond);
    }

    #[TestWith(['part' => 25], 'the hour component is too high')]
    #[TestWith(['part' => -1], 'the hour component is too low')]
    public function test_domain_rejects_invalid_hours(int $part): void
    {
        $this->expectExceptionObject(InvalidTime::dueToMalformedHour($part));

        Time::at($part);
    }

    #[TestWith(['part' => 60], 'the minute component is too high')]
    #[TestWith(['part' => -1], 'the minute component is too low')]
    public function test_domain_rejects_invalid_minutes(int $part): void
    {
        $this->expectExceptionObject(InvalidTime::dueToMalformedMinute($part));

        Time::at(minute: $part);
    }

    #[TestWith(['part' => 60], 'the second component is too high')]
    #[TestWith(['part' => -1], 'the second component is too low')]
    public function test_domain_rejects_invalid_seconds(int $part): void
    {
        $this->expectExceptionObject(InvalidTime::dueToMalformedSecond($part));

        Time::at(second: $part);
    }

    #[TestWith(['part' => 1_000_001], 'the microsecond component is too high')]
    #[TestWith(['part' => -1], 'the microsecond component is too low')]
    public function test_domain_rejects_invalid_microseconds(int $part): void
    {
        $this->expectExceptionObject(InvalidTime::dueToMalformedMicrosecond($part));

        Time::at(microsecond: $part);
    }

    public function test_midnight_and_noon(): void
    {
        $this->assertSame(0, Time::midnight()->hour);
        $this->assertSame(12, Time::noon()->hour);
        $this->assertSame(23, Time::endOfDay()->hour);
    }

    /*
     * -------------------------------------------------
     * From microseconds
     * -------------------------------------------------
     */

    public function test_from_microseconds_wraps_correctly(): void
    {
        $time = Time::fromOffset(25 * 3_600_500_000, Unit::Microsecond);
        $this->assertSame('01:00:12.500000', $time->toNotation());

        $time = Time::fromOffset(25 * 3_600_500, Unit::Millisecond);
        $this->assertSame('01:00:12.500000', $time->toNotation());

        $time = Time::fromOffset(25 * 3_600, Unit::Second);
        $this->assertSame('01:00:00', $time->toNotation());

        $time = Time::fromOffset(25 * 60, Unit::Minute);
        $this->assertSame('01:00:00', $time->toNotation());
    }

    /*
     * -------------------------------------------------
     * Parsing
     * -------------------------------------------------
     */

    public function test_parse_string(): void
    {
        $time = Time::fromNotation('12:34:56.123456');

        $this->assertSame(12, $time->hour);
        $this->assertSame(34, $time->minute);
        $this->assertSame(56, $time->second);
        $this->assertSame(123_456, $time->microsecond);
    }

    public function test_parse_without_seconds(): void
    {
        $time = Time::fromNotation('08:15');

        $this->assertSame(8, $time->hour);
        $this->assertSame(15, $time->minute);
        $this->assertSame(0, $time->second);
    }

    public function test_parse_invalid_throws(): void
    {
        $this->expectException(InvalidTime::class);

        Time::fromNotation('99:99:99');
    }

    public function test_parse_date_time(): void
    {
        $dt = CarbonImmutable::parse('2024-01-01 10:20:30.123456');
        $time = Time::fromDate($dt);

        $this->assertSame(10, $time->hour);
        $this->assertSame(20, $time->minute);
        $this->assertSame(30, $time->second);
        $this->assertSame(123_456, $time->microsecond);
    }

    /*
     * -------------------------------------------------
     * Formatting
     * -------------------------------------------------
     */

    public function test_format_default(): void
    {
        $time = Time::at(9, 5, 3);

        $this->assertSame('09:05:03', $time->toNotation());
    }

    public function test_format_padded(): void
    {
        $time = Time::at(9, 5, 3);

        $this->assertSame('09:05:03', $time->toNotation());
    }

    public function test_format_with_microseconds(): void
    {
        $time = Time::at(1, 2, 3, 45);

        $this->assertSame('01:02:03.000045', $time->toNotation());
    }

    public function test_format_auto_microseconds(): void
    {
        $time = Time::at(1, 2, 3);

        $this->assertSame('01:02:03', $time->toNotation());
    }

    /*
     * -------------------------------------------------
     * Arithmetic
     * -------------------------------------------------
     */

    public function test_add_time(): void
    {
        $time = Time::at(10)
            ->shift(Duration::of(hours: 2, minutes: 30, seconds: 15, microseconds: 500));

        $this->assertSame(12, $time->hour);
        $this->assertSame(30, $time->minute);
        $this->assertSame(15, $time->second);
        $this->assertSame(500, $time->microsecond);
    }

    public function test_add_time_wraps_day(): void
    {
        $time = Time::at(23)->shift(Duration::of(hours: 2));

        $this->assertSame(1, $time->hour);
    }

    public function test_add_without_argument_changes_nothing(): void
    {
        $time = Time::at(23);

        $this->assertSame($time, $time->shift(Duration::of()));
    }

    /*
     * -------------------------------------------------
     * Comparison
     * -------------------------------------------------
     */

    public function test_comparison(): void
    {
        $a = Time::at(10);
        $b = Time::at(12);

        $this->assertTrue($a->isBefore($b));
        $this->assertTrue($a->isBeforeOrEqual($b));
        $this->assertTrue($b->isAfter($a));
        $this->assertTrue($b->isAfterOrEqual($a));
        $this->assertTrue($a->equals($a));
        $this->assertTrue($a->isBeforeOrEqual($a));
        $this->assertTrue($a->isAfterOrEqual($a));
    }

    /*
     * -------------------------------------------------
     * Diff
     * -------------------------------------------------
     */

    public function test_diff_signed(): void
    {
        $a = Time::at(8);
        $b = Time::at(10);

        $this->assertSame(7_200_000_000, $a->diff($b)->total(Unit::Microsecond));
        $this->assertSame(-7_200_000_000, $b->diff($a)->total(Unit::Microsecond));
    }

    public function test_diff_forward_wraps(): void
    {
        $a = Time::at(23);
        $b = Time::at(1);

        $this->assertSame(7_200_000_000, $a->distance($b)->total(Unit::Microsecond));
    }

    /*
     * -------------------------------------------------
     * Edge cases
     * -------------------------------------------------
     */

    public function test_zero_add_returns_same_instance(): void
    {
        $time = Time::at(10);

        $this->assertSame($time, $time->shift(Duration::zero()));
    }

    public function test_microseconds(): void
    {
        $this->assertSame(36_000_000_000, Time::at(10)->toOffset(Unit::Microsecond));
    }

    public function test_apply_to_datetime_immutable(): void
    {
        $date = new DateTimeImmutable('2026-05-11 08:15:30', new DateTimeZone('Africa/Luanda'));
        $time = Time::at(14, 45, 12, 123_456);
        $result = $time->applyTo($date);

        $this->assertSame('2026-05-11 14:45:12.123456', $result->format('Y-m-d H:i:s.u'));
        $this->assertSame('Africa/Luanda', $result->getTimezone()->getName());

        // Original instance remains unchanged
        $this->assertSame('2026-05-11 08:15:30.000000', $date->format('Y-m-d H:i:s.u'));
    }

    public function test_apply_to_mutable_datetime_returns_immutable(): void
    {
        $date = new DateTime('2026-05-11 08:15:30', new DateTimeZone('UTC'));
        $time = Time::at(22, 1, 2, 999_999);

        $this->assertSame('2026-05-11 22:01:02.999999', $time->applyTo($date)->format('Y-m-d H:i:s.u'));

        // Original mutable DateTime is NOT modified
        $this->assertSame('2026-05-11 08:15:30.000000', $date->format('Y-m-d H:i:s.u'));
    }

    public function test_apply_to_preserves_date(): void
    {
        $date = CarbonImmutable::parse('2030-12-25 00:00:00');
        $time = Time::at(9, 30);

        $this->assertSame('2030-12-25 09:30:00', $time->applyTo($date)->format('Y-m-d H:i:s'));
    }

    public function test_apply_to_preserves_timezone(): void
    {
        $timezone = new DateTimeZone('Asia/Tokyo');
        $date = new DateTimeImmutable('2026-01-01 00:00:00', $timezone);

        $this->assertSame('Asia/Tokyo', Time::at(12)->applyTo($date)->getTimezone()->getName());
    }

    /**
     * @param array<non-empty-string, int> $arguments
     */
    #[DataProvider('provideWith_updates_selected_componentsCases')]
    public function test_with_updates_selected_components(
        Time $original,
        array $arguments,
        string $expected,
    ): void {
        $this->assertSame($expected, $original->with(...$arguments)->toNotation());
    }

    /**
     * @throws InvalidTime
     *
     * @return iterable<non-empty-string, array{
     *     0:Time,
     *     1: array<non-empty-string, int>,
     *     2: non-empty-string
     * }>
     */
    public static function provideWith_updates_selected_componentsCases(): iterable
    {
        $base = Time::at(23, 54, 23, 123_456);

        yield 'replace hour' => [
            $base,
            ['hour' => 8],
            '08:54:23.123456',
        ];

        yield 'replace minute' => [
            $base,
            ['minute' => 12],
            '23:12:23.123456',
        ];

        yield 'replace second' => [
            $base,
            ['second' => 5],
            '23:54:05.123456',
        ];

        yield 'replace microsecond' => [
            $base,
            ['microsecond' => 999],
            '23:54:23.000999',
        ];

        yield 'replace multiple components' => [
            $base,
            [
                'hour' => 8,
                'minute' => 15,
            ],
            '08:15:23.123456',
        ];

        yield 'replace all components' => [
            $base,
            [
                'hour' => 1,
                'minute' => 2,
                'second' => 3,
                'microsecond' => 4,
            ],
            '01:02:03.000004',
        ];
    }

    public function test_with_preserves_original_instance(): void
    {
        $original = Time::at(23, 54, 23);

        $updated = $original->with(hour: 8);

        $this->assertSame('23:54:23', $original->toNotation());
        $this->assertSame('08:54:23', $updated->toNotation());
        $this->assertNotSame($updated->toLocaleString(locale: 'tr-CY', length: TimeFormatLength::Short), $updated->toLocaleString(locale: 'tr-CY', length: TimeFormatLength::Long));
        $this->assertSame($updated->toLocaleString('en_US', 'Africa/Nairobi'), $updated->toLocaleString('en_US'));

        $this->expectException(TimeException::class);
        $updated->toLocaleString('foo-bar');
    }

    public function test_with_returns_same_instance_when_no_change(): void
    {
        $time = Time::at(23, 54, 23, 123_456);

        $this->assertSame($time, $time->with());
    }

    public function test_with_throws_on_invalid_hour(): void
    {
        $time = Time::at(23, 54, 23);

        $this->expectException(InvalidTime::class);

        $time->with(hour: 24);
    }

    public function test_with_throws_on_invalid_minute(): void
    {
        $time = Time::at(23, 54, 23);

        $this->expectException(InvalidTime::class);

        $time->with(minute: 60);
    }

    public function test_with_throws_on_invalid_second(): void
    {
        $time = Time::at(23, 54, 23);

        $this->expectException(InvalidTime::class);

        $time->with(second: 60);
    }

    public function test_with_throws_on_invalid_microsecond(): void
    {
        $time = Time::at(23, 54, 23);

        $this->expectException(InvalidTime::class);

        $time->with(microsecond: 1_000_000);
    }

    public function test_add_throws_on_overflow_duration(): void
    {
        $time = Time::noon();

        $this->expectExceptionObject(InvalidDuration::dueToOverflow());

        $time->shift(Duration::max());
    }

    public function test_time_can_be_serialized_and_unserialized(): void
    {
        $time = Time::fromNotation('12:34:56.123456');
        $restored = unserialize(serialize($time));

        $this->assertInstanceOf(Time::class, $restored);
        $this->assertEquals($time, $restored);
    }

    public function test_time_can_be_json_serialized(): void
    {
        $time = Time::fromNotation('12:34:56');

        $this->assertSame('"12:34:56"', json_encode($time));
    }

    #[DataProvider('provideTruncate_and_roundCases')]
    public function test_truncate_and_round(
        int $input,
        Unit $precision,
        int $expectedTruncate,
        int $expectedRound,
        int $expectedCeil,
    ): void {
        $time = Time::fromOffset($input, Unit::Microsecond);

        $this->assertSame($expectedTruncate, $time->roundTo($precision, RoundingMode::Floor)->toOffset(Unit::Microsecond));
        $this->assertSame($expectedRound, $time->roundTo($precision)->toOffset(Unit::Microsecond));
        $this->assertSame($expectedCeil, $time->roundTo($precision, RoundingMode::Ceil)->toOffset(Unit::Microsecond));
    }

    /**
     * @return Iterator<non-empty-string, array{int, Unit, int<1, max>, int<1, max>}>
     */
    public static function provideTruncate_and_roundCases(): iterable
    {
        // [input microseconds, precision, expected truncate, expected round]
        yield 'seconds exact' => [
            10_000_000, // 10s
            Unit::Second,
            10_000_000,
            10_000_000,
            10_000_000,
        ];

        yield 'seconds truncate down' => [
            10_499_999,
            Unit::Second,
            10_000_000,
            10_000_000,
            11_000_000,
        ];

        yield 'seconds round up' => [
            10_500_000,
            Unit::Second,
            10_000_000,
            11_000_000,
            11_000_000,
        ];

        yield 'minutes round up' => [
            3_150_000_000, // 52m30s
            Unit::Minute,
            3_120_000_000, // 52m truncate
            3_180_000_000, // 53m round
            3_180_000_000, // 53m round
        ];

        yield 'milliseconds' => [
            1_499,
            Unit::Millisecond,
            1_000,
            1_000,
            2_000,
        ];
    }

    /**
     * @param list<Time> $times
     *
     * @throws InvalidTime
     */
    #[DataProvider('provideMin_ofCases')]
    public function test_min_of(array $times, Time $expected): void
    {
        $this->assertTrue(Time::minOf(...$times)->equals($expected));
    }

    /**
     * @throws InvalidTime
     *
     * @return Iterator<non-empty-string, array{list<Time>, Time}>
     */
    public static function provideMin_ofCases(): iterable
    {
        yield 'simple order' => [
            [
                Time::at(hour: 10),
                Time::at(hour: 5),
                Time::at(hour: 8),
            ],
            Time::at(hour: 5),
        ];

        yield 'already sorted' => [
            [
                Time::at(hour: 1),
                Time::at(hour: 2),
                Time::at(hour: 3),
            ],
            Time::at(hour: 1),
        ];

        yield 'single value' => [
            [
                Time::at(hour: 7),
            ],
            Time::at(hour: 7),
        ];
    }

    /**
     * @param list<Time> $times
     *
     * @throws InvalidTime
     */
    #[DataProvider('provideMax_ofCases')]
    public function test_max_of(array $times, Time $expected): void
    {
        $this->assertTrue(Time::maxOf(...$times)->equals($expected));
    }

    /**
     * @throws InvalidTime
     *
     * @return Iterator<non-empty-string, array{list<Time>, Time}>
     */
    public static function provideMax_ofCases(): iterable
    {
        yield 'simple order' => [
            [
                Time::at(hour: 10),
                Time::at(hour: 5),
                Time::at(hour: 8),
            ],
            Time::at(hour: 10),
        ];

        yield 'reverse order' => [
            [
                Time::at(hour: 23),
                Time::at(hour: 1),
                Time::at(hour: 12),
            ],
            Time::at(hour: 23),
        ];
    }

    #[DataProvider('provideClampCases')]
    public function test_clamp(Time $time, Time $min, Time $max, Time $expected): void
    {
        $this->assertTrue($time->clamp($min, $max)->equals($expected));
    }

    /**
     * @throws InvalidTime
     * @return Iterator<non-empty-string, list<Time>>
     */
    public static function provideClampCases(): iterable
    {
        yield 'below range' => [
            Time::at(hour: 2),
            Time::at(hour: 5),
            Time::at(hour: 10),
            Time::at(hour: 5),
        ];

        yield 'above range' => [
            Time::at(hour: 12),
            Time::at(hour: 5),
            Time::at(hour: 10),
            Time::at(hour: 10),
        ];

        yield 'inside range' => [
            Time::at(hour: 7),
            Time::at(hour: 5),
            Time::at(hour: 10),
            Time::at(hour: 7),
        ];
    }

    public function test_time_now(): void
    {
        $this->assertFalse(Time::now()->equals(Time::now('Asia/Tokyo')));
    }

    public function test_invalid_timezone_identifier(): void
    {
        $this->expectException(TimeException::class);

        Time::now('Asia/Paris');
    }

    #[DataProvider('provideFrom_notation_compact_validCases')]
    public function test_from_notation_compact_valid(string $value, Time $expected): void
    {
        $this->assertTrue($expected->equals(Time::fromNotation($value, TimeFormat::Compact)));
    }

    /**
     * @throws InvalidTime
     * @return iterable<non-empty-string, array{0: non-empty-string, 1: Time}>
     */
    public static function provideFrom_notation_compact_validCases(): iterable
    {
        yield 'hours and minutes' => ['1h 30m', Time::at(1, 30)];

        yield 'with seconds' => ['1h 30m 45s', Time::at(1, 30, 45)];

        yield 'zero values' => ['0h 0m', Time::midnight()];

        yield 'compact spacing' => ['12h34m56s', Time::at(12, 34, 56)];
    }

    #[DataProvider('provideFrom_notation_compact_invalidCases')]
    public function test_from_notation_compact_invalid(string $value): void
    {
        $this->expectException(InvalidTime::class);

        Time::fromNotation($value, TimeFormat::Compact);
    }

    /**
     * @return iterable<non-empty-string, array{0: string}>
     */
    public static function provideFrom_notation_compact_invalidCases(): iterable
    {
        yield 'missing hour' => ['30m'];

        yield 'missing minute' => ['1h'];

        yield 'seconds only' => ['45s'];

        yield 'microseconds only' => ['123us'];

        yield 'empty string' => [''];

        yield 'invalid unit order' => ['30m 1h'];

        yield 'invalid unit' => ['1h 30x'];
    }

    #[DataProvider('provideTo_notation_compactCases')]
    public function test_to_notation_compact(Time $time, string $expected): void
    {
        $this->assertSame($expected, $time->toNotation(TimeFormat::Compact));
    }

    /**
     * @throws InvalidTime
     * @return iterable<non-empty-string, array{0: Time, 1:non-empty-string}>
     */
    public static function provideTo_notation_compactCases(): iterable
    {
        yield 'hours and minutes' => [Time::at(1, 30), '1h30m'];

        yield 'with seconds' => [Time::at(1, 30, 45), '1h30m45s'];

        yield 'midnight' => [Time::midnight(), '0h0m'];

        yield 'microseconds' => [Time::at(1, microsecond: 256), '1h0m0s256µs'];
    }
}
