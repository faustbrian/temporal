<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

use const JSON_UNESCAPED_SLASHES;

use function iterator_to_array;
use function json_encode;
use function serialize;
use function unserialize;

/**
 * @internal
 */
#[CoversClass(IntervalSet::class)]
#[CoversClass(Interval::class)]
#[CoversClass(InvalidInterval::class)]
#[CoversClass(IntervalFormat::class)]
#[CoversClass(Duration::class)]
#[CoversClass(Time::class)]
final class IntervalTest extends TestCase
{
    /*
     * -------------------------------------------------
     * Construction helpers
     * -------------------------------------------------
     */

    public function test_after_creates_expected_range(): void
    {
        $range = Interval::since(
            Time::at(10),
            Duration::of(minutes: 30),
        );

        $this->assertSame('[10:00:00,10:30:00)', $range->format(IntervalFormat::Iso80000));
    }

    public function test_before_creates_expected_range(): void
    {
        $range = Interval::until(
            Time::at(10),
            Duration::of(minutes: 30),
        );

        $this->assertSame('[09:30:00,10:00:00)', $range->format(IntervalFormat::Iso80000));
    }

    public function test_around_creates_symmetric_range(): void
    {
        $range = Interval::around(
            Time::at(10),
            Duration::of(minutes: 20),
        );

        $this->assertSame('[09:50:00,10:10:00)', $range->format(IntervalFormat::Iso80000));
    }

    /*
     * -------------------------------------------------
     * Duration & comparisons
     * -------------------------------------------------
     */

    public function test_duration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(11));

        $this->assertEquals(
            Duration::of(minutes: 60),
            $range->duration,
        );
    }

    public function test_same_duration(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(20), Time::at(21));

        $this->assertTrue($a->sameDurationAs($b));
    }

    public function test_longer_and_shorter(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(10), Time::at(11));

        $this->assertTrue($a->longerThan($b));
        $this->assertTrue($a->longerThanOrEqual($b));
        $this->assertTrue($b->shorterThan($a));
        $this->assertTrue($b->shorterThanOrEqual($a));
    }

    /*
     * -------------------------------------------------
     * contains (Time)
     * -------------------------------------------------
     */

    public function test_contains_time_inside(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $this->assertTrue($range->includes(Time::at(11)));
        $this->assertFalse($range->includes(Time::at(12))); // end excluded
    }

    /*
     * -------------------------------------------------
     * overlaps
     * -------------------------------------------------
     */

    public function test_overlaps_true(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(13));

        $this->assertTrue($a->overlaps($b));
    }

    public function test_overlaps_false(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $this->assertFalse($a->overlaps($b));
    }

    /*
     * -------------------------------------------------
     * abuts
     * -------------------------------------------------
     */

    public function test_abuts_true(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(11), Time::at(12));

        $this->assertTrue($a->abuts($b));
    }

    public function test_abuts_false(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(11, 1), Time::at(12));

        $this->assertFalse($a->abuts($b));
    }

    /*
     * -------------------------------------------------
     * intersect
     * -------------------------------------------------
     */

    public function test_intersect(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(13));

        $i = $a->intersect($b);

        $this->assertInstanceOf(Interval::class, $i);
        $this->assertSame('[11:00:00,12:00:00)', $i->format(IntervalFormat::Iso80000));
    }

    public function test_intersect_null_when_disjoint(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $this->assertNotInstanceOf(Interval::class, $a->intersect($b));
    }

    /*
     * -------------------------------------------------
     * gap
     * -------------------------------------------------
     */

    public function test_gap(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $gap = $a->gap($b);

        $this->assertInstanceOf(Interval::class, $gap);
        $this->assertSame('[11:00:00,12:00:00)', $gap->format(IntervalFormat::Iso80000));
    }

    public function test_gap_null_when_overlapping(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(13));

        $this->assertNotInstanceOf(Interval::class, $a->gap($b));
    }

    /*
     * -------------------------------------------------
     * splitForward
     * -------------------------------------------------
     */

    public function test_split_forward_basic(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));
        $parts = $range->splitBy(Duration::of(minutes: 30));

        $this->assertCount(4, $parts);
        $this->assertSame('[10:00:00,10:30:00)', $parts->get(0)->format(IntervalFormat::Iso80000));
        $this->assertSame('[10:30:00,11:00:00)', $parts->get(1)->format(IntervalFormat::Iso80000));
        $this->assertSame('[11:00:00,11:30:00)', $parts->get(2)->format(IntervalFormat::Iso80000));
        $this->assertSame('[11:30:00,12:00:00)', $parts->get(3)->format(IntervalFormat::Iso80000));
    }

    /*
     * -------------------------------------------------
     * splitBackward
     * -------------------------------------------------
     */

    public function test_split_backward_40_minute_duration(): void
    {
        $range = Interval::between(Time::at(9), Time::at(10));

        $splits = $range->splitBy(Duration::of(minutes: 40), Bound::End);

        $this->assertCount(2, $splits);

        $this->assertSame('[09:20:00,10:00:00)', $splits->get(0)->format(IntervalFormat::Iso80000));
        $this->assertSame('[09:00:00,09:20:00)', $splits->get(1)->format(IntervalFormat::Iso80000));
    }

    public function test_split_with_collapsed(): void
    {
        $range = Interval::collapsed(Time::at(10));

        $this->assertCount(0, $range->splitBy(Duration::of(minutes: 30)));
    }

    public function test_equals(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));
        $rangebis = Interval::since(Time::at(10), Duration::of(hours: 2));

        $this->assertTrue($range->equals($rangebis));
        $this->assertTrue($range->shorterThanOrEqual($rangebis));
        $this->assertTrue($range->longerThanOrEqual($rangebis));
    }

    public function test_contains_time_range_fully_inside(): void
    {
        $a = Interval::between(Time::at(10), Time::at(14));
        $b = Interval::between(Time::at(11), Time::at(13));

        $this->assertTrue($a->contains($b));
        $this->assertFalse($b->contains($a));
    }

    public function test_contains_time_range_boundary_excluded(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(12));

        $this->assertTrue($a->contains($b));
    }

    public function test_contains_time_range_partial_overlap_false(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(13));

        $this->assertFalse($a->contains($b));
    }

    public function test_contains_time_range_disjoint(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $this->assertFalse($a->contains($b));
    }

    public function test_contains_time_range_identical(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(10), Time::at(12));

        $this->assertTrue($a->contains($b));
    }

    public function test_contains_time_range_wraparound(): void
    {
        $a = Interval::between(Time::at(22), Time::at(0o2));
        $b = Interval::between(Time::at(23), Time::at(0o1));

        $this->assertTrue($a->contains($b));
    }

    public function test_contains_time_range_reverse(): void
    {
        $a = Interval::between(Time::at(23), Time::at(0o3));
        $b = Interval::between(Time::at(10), Time::at(11));

        $this->assertFalse($a->contains($b));
    }

    public function test_range_forward(): void
    {
        $range = Interval::between(Time::at(hour: 9), Time::at(hour: 10));
        $times = iterator_to_array($range->steps(Duration::of(minutes: 15)));

        $this->assertCount(4, $times);

        $this->assertSame('09:00:00', $times[0]->format());
        $this->assertSame('09:15:00', $times[1]->format());
        $this->assertSame('09:30:00', $times[2]->format());
        $this->assertSame('09:45:00', $times[3]->format());
    }

    public function test_range_backward(): void
    {
        $range = Interval::between(Time::at(hour: 9), Time::at(hour: 10));
        $times = iterator_to_array($range->steps(Duration::of(minutes: 15), Bound::End));

        $this->assertCount(4, $times);

        $this->assertSame('09:45:00', $times[0]->format());
        $this->assertSame('09:30:00', $times[1]->format());
        $this->assertSame('09:15:00', $times[2]->format());
        $this->assertSame('09:00:00', $times[3]->format());
    }

    public function test_range_with_zero_duration(): void
    {
        $range = Interval::between(Time::at(hour: 9), Time::at(hour: 10));
        $this->expectException(InvalidDuration::class);

        iterator_to_array($range->steps(Duration::zero(), Bound::End));
    }

    public function test_range_with_collapsed_interval(): void
    {
        $range = Interval::collapsed(Time::at(hour: 9));
        $this->assertCount(0, iterator_to_array($range->steps(Duration::of(hours: 3), Bound::End)));
    }

    public function test_expand(): void
    {
        $range = Interval::between(Time::at(hour: 10), Time::at(hour: 12));
        $expanded = $range->expand(Duration::of(hours: 1));

        $this->assertSame('[09:00:00,13:00:00)', $expanded->format(IntervalFormat::Iso80000));
    }

    public function test_expand_wraps_around_midnight(): void
    {
        $range = Interval::between(Time::at(hour: 0, minute: 2), Time::at(hour: 23, minute: 58));
        $expanded = $range->expand(Duration::of(minutes: 5));

        $this->assertSame('[23:57:00,00:03:00)', $expanded->format(IntervalFormat::Iso80000));
    }

    public function test_expand_can_shrink_range(): void
    {
        $range = Interval::between(Time::at(hour: 10), Time::at(hour: 14));
        $shrunk = $range->expand(Duration::of(hours: 1)->negated());

        $this->assertSame('[11:00:00,13:00:00)', $shrunk->format(IntervalFormat::Iso80000));
    }

    public function test_expand_by_24_hours_returns_same_range(): void
    {
        $range = Interval::between(
            Time::at(hour: 10),
            Time::at(hour: 12),
        );

        $expanded = $range->expand(Duration::of(hours: 24));

        $this->assertTrue($range->equals($expanded));
    }

    public function test_expand_by_multiple_of_24_hours_returns_same_range(): void
    {
        $range = Interval::between(
            Time::at(hour: 22),
            Time::at(hour: 3),
        );

        $expanded = $range->expand(Duration::of(hours: 48));

        $this->assertTrue($range->equals($expanded));
    }

    public function test_expand_can_collapse_range_to_empty(): void
    {
        $range = Interval::between(Time::at(hour: 10), Time::at(hour: 12));
        $collapsed = $range->expand(Duration::of(hours: 1)->negated());

        $this->assertSame('[11:00:00,11:00:00)', $collapsed->format(IntervalFormat::Iso80000));
    }

    public function test_collapsed_creates_zero_duration_range(): void
    {
        $time = Time::at(hour: 10);

        $range = Interval::collapsed($time);

        $this->assertEquals($time, $range->start);
        $this->assertEquals($time, $range->end);
        $this->assertTrue($range->duration->isZero());
        $this->assertSame(IntervalType::Collapsed, $range->type);
    }

    public function test_circular_creates_full_day_range(): void
    {
        $time = Time::at(hour: 10);

        $interval = Interval::circular($time);

        $this->assertEquals($time, $interval->start);
        $this->assertEquals($time, $interval->end);
        $this->assertTrue($interval->duration->equals(Duration::of(hours: 24)));
        $this->assertSame(IntervalType::Circular, $interval->type);
    }

    public function test_starting_on_returns_same_instance_when_unchanged(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $this->assertSame($range, $range->startingOn(Time::at(10)));
    }

    public function test_starting_on_changes_start(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->startingOn(Time::at(9));

        $this->assertSame('[09:00:00,12:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function test_ending_on_returns_same_instance_when_unchanged(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $this->assertSame(
            $range,
            $range->endingOn(Time::at(12)),
        );
    }

    public function test_ending_on_changes_end(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->endingOn(Time::at(14));

        $this->assertSame('[10:00:00,14:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function test_shift_returns_same_instance_for_zero_duration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $this->assertSame(
            $range,
            $range->shift(Duration::zero()),
        );
    }

    public function test_shift_moves_entire_range_forward(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $shifted = $range->shift(Duration::of(hours: 2));

        $this->assertSame('[12:00:00,14:00:00)', $shifted->format(IntervalFormat::Iso80000));
    }

    public function test_shift_supports_circular_wrapping(): void
    {
        $range = Interval::between(Time::at(22), Time::at(2));

        $shifted = $range->shift(Duration::of(hours: 3));

        $this->assertSame('[01:00:00,05:00:00)', $shifted->format(IntervalFormat::Iso80000));
    }

    public function test_shift_start_moves_only_start(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->shiftBound(duration: Duration::of(hours: 1), bound: Bound::Start);

        $this->assertSame('[11:00:00,12:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function test_shift_end_moves_only_end(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->shiftBound(duration: Duration::of(hours: 2), bound: Bound::End);

        $this->assertSame('[10:00:00,14:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function test_lasting_from_start_changes_duration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->lasting(duration: Duration::of(hours: 5), from: Bound::Start);

        $this->assertSame('[10:00:00,15:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function test_lasting_from_start_supports_circular_wrapping(): void
    {
        $range = Interval::between(Time::at(22), Time::at(23));

        $updated = $range->lasting(duration: Duration::of(hours: 4), from: Bound::Start);

        $this->assertSame('[22:00:00,02:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function test_lasting_from_end_changes_duration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->lasting(duration: Duration::of(hours: 5), from: Bound::End);

        $this->assertSame('[07:00:00,12:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function test_lasting_from_end_supports_circular_wrapping(): void
    {
        $range = Interval::between(Time::at(1), Time::at(3));

        $updated = $range->lasting(duration: Duration::of(hours: 6), from: Bound::End);

        $this->assertSame('[21:00:00,03:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function test_invert_swaps_start_and_end(): void
    {
        $range = Interval::between(
            Time::at(10),
            Time::at(14),
        );

        $this->assertSame(
            '[14:00:00,10:00:00)',
            $range->complement()->format(IntervalFormat::Iso80000),
        );
    }

    public function test_invert_of_collapsed_range_returns_circular_range(): void
    {
        $range = Interval::collapsed(Time::at(10));

        $inverted = $range->complement();

        $this->assertSame(IntervalType::Circular, $inverted->type);
    }

    public function test_invert_of_circular_range_returns_collapsed_range(): void
    {
        $range = Interval::circular(Time::at(10));

        $inverted = $range->complement();

        $this->assertSame(IntervalType::Collapsed, $inverted->type);
    }

    public function test_full_day(): void
    {
        $range = Interval::fullDay();
        $inverted = $range->complement();

        $this->assertSame(IntervalType::Collapsed, $inverted->type);
        $this->assertTrue(Time::midnight()->equals($inverted->start));
        $this->assertFalse($inverted->includes($inverted->start));
        $this->assertTrue($range->includes($range->end));
    }

    public function test_invert_is_an_involution(): void
    {
        $range = Interval::between(
            Time::at(22),
            Time::at(2),
        );

        $this->assertTrue(
            $range
                ->complement()
                ->complement()
                ->equals($range),
        );
    }

    public function test_invert_produces_complementary_duration(): void
    {
        $range = Interval::between(
            Time::at(22),
            Time::at(2),
        );

        $total = $range
            ->duration
            ->sum($range->complement()->duration);

        $this->assertTrue(
            $total->equals(Duration::of(hours: 24)),
        );
    }

    /**
     * @param non-empty-string $input
     * @param non-empty-string $expected
     *
     * @throws InvalidDuration
     * @throws InvalidTime
     * @throws TimeException
     */
    #[DataProvider('provideFrom_iso8601Cases')]
    public function test_from_iso8601(string $input, string $expected): void
    {
        $this->assertSame($expected, Interval::fromFormat($input, IntervalFormat::Iso8601)->format(IntervalFormat::Iso80000));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideFrom_iso8601Cases(): iterable
    {
        yield 'valid simple with start date' => ['10:00:00/PT1H', '[10:00:00,11:00:00)'];

        yield 'valid with spaces' => [' 10:00:00/PT1H ', '[10:00:00,11:00:00)'];

        yield 'valid simple with end date' => ['PT1H/11:00:00', '[10:00:00,11:00:00)'];

        yield 'valid with end date and space' => [' PT1H/11:00:00 ', '[10:00:00,11:00:00)'];

        yield 'valid with start and end time' => ['10:00:00/11:00:00', '[10:00:00,11:00:00)'];

        yield 'valid with start and end time with space' => [' 10:00:00 / 11:00:00 ', '[10:00:00,11:00:00)'];
    }

    /**
     * @param non-empty-string $input
     *
     * @throws InvalidInterval
     */
    #[DataProvider('provideFrom_iso8601_invalidCases')]
    public function test_from_iso8601_invalid(string $input): void
    {
        $this->expectException(TimeException::class);

        Interval::fromFormat($input, IntervalFormat::Iso8601);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideFrom_iso8601_invalidCases(): iterable
    {
        yield 'missing slash' => ['10:00:00PT1H'];

        yield 'empty string' => [''];

        yield 'invalid start time' => ['invalid/PT1H'];

        yield 'invalid end duration' => ['10:00:00/invalid'];

        yield 'extra segments' => ['10:00:00/PT1H/PT2H'];

        yield 'invalid end time' => ['PT2H/invalid'];

        yield 'invalid start duration' => ['Pinvalid/09:00:00'];
    }

    /**
     * @param non-empty-string $input
     * @param non-empty-string $expectedIso8601
     *
     * @throws InvalidInterval
     */
    #[DataProvider('provideFrom_iso80000Cases')]
    public function test_from_iso80000(string $input, string $expectedIso8601): void
    {
        $this->assertSame($expectedIso8601, Interval::fromFormat($input, IntervalFormat::Iso80000)->format());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideFrom_iso80000Cases(): iterable
    {
        yield 'full range' => ['[10:00:00,12:00:00)', '10:00:00/PT2H'];

        yield 'open start' => ['[,12:00:00)', '00:00:00/PT12H'];

        yield 'open end' => ['[10:00:00,)', '10:00:00/PT14H'];

        yield 'with spaces' => ['[ 10:00:00 , 12:00:00 )', '10:00:00/PT2H'];
    }

    /**
     * @param non-empty-string $input
     *
     * @throws InvalidInterval|InvalidTime
     */
    #[DataProvider('provideFrom_iso80000_invalidCases')]
    public function test_from_iso80000_invalid(string $input): void
    {
        $this->expectException(TimeException::class);

        Interval::fromFormat($input, IntervalFormat::Iso80000);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideFrom_iso80000_invalidCases(): iterable
    {
        yield 'unsupported boudaries' => ['[10:00:00,12:00:00]'];

        yield 'missing at least one boundary value' => ['[,)'];

        yield 'missing brackets' => ['10:00:00,12:00:00'];

        yield 'invalid format' => ['[invalid]'];

        yield 'invalid start' => ['[invalid,12:00:00)'];
    }

    /**
     * @param non-empty-string $input
     * @param non-empty-string $expectedIso8601
     *
     * @throws InvalidInterval
     */
    #[DataProvider('provideFrom_bourakiCases')]
    public function test_from_bouraki(string $input, string $expectedIso8601): void
    {
        $this->assertSame($expectedIso8601, Interval::fromFormat($input, IntervalFormat::Bourbaki)->format());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideFrom_bourakiCases(): iterable
    {
        yield 'full range' => ['[10:00:00,12:00:00[', '10:00:00/PT2H'];

        yield 'open start' => ['[,12:00:00[', '00:00:00/PT12H'];

        yield 'open end' => ['[10:00:00,[', '10:00:00/PT14H'];

        yield 'with spaces' => ['[ 10:00:00 , 12:00:00 [', '10:00:00/PT2H'];
    }

    /**
     * @param non-empty-string        $input
     * @param class-string<Throwable> $expectedException
     *
     * @throws InvalidTime
     * @throws TimeException
     */
    #[DataProvider('provideFrom_bourbaki_invalidCases')]
    public function test_from_bourbaki_invalid(string $input, string $expectedException): void
    {
        $this->expectException($expectedException);

        Interval::fromFormat($input, IntervalFormat::Bourbaki);
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string<Throwable>}>
     */
    public static function provideFrom_bourbaki_invalidCases(): iterable
    {
        yield 'unsupported boudaries' => ['[10:00:00,12:00:00', TimeException::class];

        yield 'missing at least one boundary value' => ['[,[', TimeException::class];

        yield 'missing brackets' => ['10:00:00,12:00:00', TimeException::class];

        yield 'invalid format' => ['[invalid', TimeException::class];

        yield 'invalid start' => ['[invalid,12:00:00[', TimeException::class];
    }

    public function test_interval_can_be_serialized_and_unserialized(): void
    {
        $interval = Interval::fromFormat('12:34:56/-PT23H30S', IntervalFormat::Iso8601);
        $restored = unserialize(serialize($interval));

        $this->assertInstanceOf(Interval::class, $restored);
        $this->assertEquals($interval, $restored);
    }

    public function test_duration_can_be_json_serialized(): void
    {
        $interval = Interval::between(Time::at(22), Time::at(2));

        $this->assertSame('"22:00:00/PT4H"', json_encode($interval, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param list<non-empty-string> $expected
     *
     * @throws InvalidTime
     */
    #[DataProvider('provideSplit_atCases')]
    public function test_split_at(Interval $interval, Time $split, array $expected): void
    {
        $this->assertSame($expected, $interval->splitAt($split)->allFormatted());
    }

    /**
     * @throws InvalidTime
     * @return iterable<string, array{0: Interval, 1: Time, 2: list<non-empty-string>}>
     */
    public static function provideSplit_atCases(): iterable
    {
        yield 'collapsed interval returns empty set' => [
            Interval::between(Time::at(hour: 10), Time::at(hour: 10)),
            Time::at(hour: 10),
            [],
        ];

        yield 'split inside interval' => [
            Interval::between(Time::at(hour: 10), Time::noon()),
            Time::at(hour: 11),
            ['10:00:00/PT1H', '11:00:00/PT1H'],
        ];

        yield 'split at start returns original interval' => [
            Interval::between(Time::at(hour: 10), Time::noon()),
            Time::at(hour: 10),
            ['10:00:00/PT2H'],
        ];

        yield 'split at end returns original interval' => [
            Interval::between(Time::at(hour: 10), Time::noon()),
            Time::noon(),
            ['10:00:00/PT2H'],
        ];

        yield 'split outside interval returns empty set' => [
            Interval::between(Time::at(hour: 10), Time::noon()),
            Time::at(hour: 13),
            ['10:00:00/PT2H'],
        ];
    }

    #[DataProvider('provideInterval_stateCases')]
    public function test_interval_state(Interval $interval, IntervalType $type): void
    {
        $this->assertSame($type, $interval->type);
    }

    /**
     * @throws InvalidDuration
     * @throws InvalidTime
     *
     * @return iterable<non-empty-string, array{interval: Interval, type:IntervalType}>
     */
    public static function provideInterval_stateCases(): iterable
    {
        yield 'interval is circular' => [
            'interval' => Interval::circular(Time::at(hour: 10)),
            'type' => IntervalType::Circular,
        ];

        yield 'interval is linear' => [
            'interval' => Interval::between(Time::noon(), Time::at(18)),
            'type' => IntervalType::Linear,
        ];

        yield 'interval overflows' => [
            'interval' => Interval::between(Time::at(22), Time::at(6)),
            'type' => IntervalType::Overflow,
        ];

        yield 'interval collapsed' => [
            'interval' => Interval::collapsed(Time::at(hour: 10)),
            'type' => IntervalType::Collapsed,
        ];
    }

    public function test_until_with_negative_duration(): void
    {
        $interval = Interval::until(Time::at(hour: 10), Duration::fromFormat('-PT3H', DurationFormat::Iso8601));
        $this->assertSame(1, $interval->duration->sign);
        $this->assertEquals($interval->duration, Duration::of(hours: 21));
    }

    public function test_around_with_negative_duration(): void
    {
        $interval = Interval::around(Time::at(hour: 10, minute: 30), Duration::fromFormat('-PT1H', DurationFormat::Iso8601));
        $this->assertSame(1, $interval->duration->sign);
        $this->assertEquals($interval->duration, Duration::of(hours: 23));
        $this->assertEquals($interval->start, Time::at(hour: 11));
        $this->assertEquals($interval->end, Time::at(hour: 10, minute: 0o0));
    }

    public function test_it_can_be_converted_to_using_php_native_objects(): void
    {
        $class = new class() extends DateTimeImmutable {};
        $timeZoneName = 'Africa/Brazzaville';

        $interval = Interval::between(Time::noon(), Time::at(18))->toNative(
            new $class('2025-03-02 23:12:59', new DateTimeZone($timeZoneName)),
        );
        $this->assertInstanceOf($class::class, $interval['startDate']);
        $this->assertSame($interval['startDate']->getTimezone()->getName(), $timeZoneName);
        $this->assertSame('2025-03-02 12:00:00', $interval['startDate']->format('Y-m-d H:i:s'));
        $this->assertEquals(
            new DateInterval('PT6H'),
            $interval['interval'],
        );
    }

    public function test_splitting_is_coherent(): void
    {
        $interval = Interval::between(Time::at(hour: 9), Time::at(hour: 10));
        $duration = Duration::of(minutes: 10);
        $steps = $interval->steps($duration, Bound::End);

        $this->assertEquals($interval->splitAt(...$steps)->sorted(), $interval->splitBy($duration, Bound::End)->sorted());
    }

    public function test_differences(): void
    {
        $this->assertEquals(
            Interval::fullDay(),
            Interval::fullDay()->difference(Interval::collapsed(Time::at(hour: 10)))->first(),
        );

        $this->assertEquals(
            new IntervalSet(),
            Interval::collapsed(Time::at(hour: 10))->difference(Interval::fullDay()),
        );
    }

    public function test_interval_formatting_improved(): void
    {
        $interval = Interval::between(
            Time::at(10, 0, 0, 500_000),
            Time::at(12, 0, 0, 250_000),
        );

        $this->assertSame('[10:00:00.500000,12:00:00.250000[', $interval->format(IntervalFormat::Bourbaki));
        $this->assertSame('[10:00:00.500000,12:00:00.250000)', $interval->format(IntervalFormat::Iso80000));
        $this->assertSame('[600.008333,720.004167)', $interval->format(IntervalFormat::Iso80000, Unit::Minute));
        $this->assertSame('[600.008333,720.004167[', $interval->format(IntervalFormat::Bourbaki, Unit::Minute));
        $this->assertSame('10:00:00.500000/12:00:00.250000', $interval->format(IntervalFormat::Iso8601StartEnd, Unit::Minute));
        $this->assertSame(
            $interval->format(IntervalFormat::Iso8601StartEnd),
            $interval->format(IntervalFormat::Iso8601StartEnd, Unit::Minute),
        );
        $this->assertSame('10:00:00/12:00:00', $interval->roundTo(Unit::Second, RoundingMode::Floor)->format(IntervalFormat::Iso8601StartEnd));
        $this->assertSame('10:00:01/12:00:00', $interval->roundTo(Unit::Second, RoundingMode::Nearest)->format(IntervalFormat::Iso8601StartEnd));
        $this->assertSame('[600,720[', $interval->roundTo(Unit::Second, RoundingMode::Floor)->format(IntervalFormat::Bourbaki, Unit::Minute));
        $this->assertSame('[600.016667,720[', $interval->roundTo(Unit::Second, RoundingMode::Nearest)->format(IntervalFormat::Bourbaki, Unit::Minute));
        $this->assertSame('[600.016667,720[', Interval::fromFormat(
            '[600.016667,720[',
            IntervalFormat::Bourbaki,
            Unit::Minute,
        )->format(IntervalFormat::Bourbaki, Unit::Minute));
    }
}
