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
use PHPUnit\Framework\TestCase;
use ValueError;

use const JSON_UNESCAPED_SLASHES;

use function array_map;
use function iterator_to_array;
use function json_encode;
use function serialize;
use function unserialize;

/**
 * @internal
 */
#[CoversClass(IntervalNotation::class)]
#[CoversClass(DurationNotation::class)]
#[CoversClass(IntervalSet::class)]
#[CoversClass(Interval::class)]
final class IntervalSetTest extends TestCase
{
    public function test_it_can_be_empty(): void
    {
        $set = new IntervalSet();

        $this->assertTrue($set->isEmpty());
        $this->assertCount(0, $set);
        $this->assertNotInstanceOf(Interval::class, $set->first());
        $this->assertNotInstanceOf(Interval::class, $set->last());
        $this->assertTrue(Duration::zero()->equals($set->duration()));
    }

    public function test_it_preserves_order(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));
        $notFound = Interval::between(Time::at(14), Time::at(15));
        $set = new IntervalSet($a, $b);

        $this->assertSame($a, $set->first());
        $this->assertSame($b, $set->last());
        $this->assertFalse(Duration::zero()->equals($set->duration()));
        $this->assertTrue($set->has($a));
        $this->assertTrue($set->has($a, $b));
        $this->assertFalse($set->has($a, $b, $notFound));
    }

    public function test_get_supports_negative_offsets(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $set = new IntervalSet($a, $b);

        $this->assertSame($b, $set->nth(-1));
        $this->assertSame($b, $set->get(-1));
        $this->assertSame($a, $set->nth(-2));
        $this->assertSame($a, $set->get(-2));
        $this->assertNotInstanceOf(Interval::class, $set->nth(-3));

        $this->expectExceptionObject(
            new TimeException('Invalid offset (-3) given to '.IntervalSet::class.'.')
        );
        $set->get(-3);
    }

    public function test_push_returns_same_instance_when_empty(): void
    {
        $set = new IntervalSet();

        $this->assertSame($set, $set->push());
        $this->assertSame($set, $set->unshift());
    }

    public function test_push_appends_intervals(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $setP = new IntervalSet($a)->push($b);

        $this->assertCount(2, $setP);
        $this->assertSame($a, $setP->first());
        $this->assertSame($b, $setP->last());

        $setU = new IntervalSet($a)->unshift($b);
        $this->assertCount(2, $setU);
        $this->assertSame($a, $setU->last());
        $this->assertSame($b, $setU->first());
    }

    public function test_matching_methods(): void
    {
        $a = Interval::between(Time::at(10), Time::at(13));
        $b = Interval::between(Time::at(12), Time::at(13));
        $time = Time::noon();
        $set = new IntervalSet($a, $b);

        $callback = static fn (Interval $interval): bool => $interval->includes($time);
        $callbackNotFound = static fn (Interval $interval): bool => $interval->includes(Time::midnight());

        $this->assertSame($a, $set->firstMatching($callback));
        $this->assertSame($b, $set->lastMatching($callback));
        $this->assertNotInstanceOf(Interval::class, $set->firstMatching($callbackNotFound));
        $this->assertNotInstanceOf(Interval::class, $set->lastMatching($callbackNotFound));
    }

    public function test_remove_elements(): void
    {
        $a = Interval::between(Time::at(10), Time::at(13));
        $b = Interval::between(Time::at(12), Time::at(13));
        $set = new IntervalSet($a, $b);

        $this->assertSame($set->remove(3), $set);
        $this->assertSame($set->remove(), $set);

        $resRemoveAll = $set->remove(0, 1);
        $this->assertTrue($resRemoveAll->isEmpty());

        $resRemoveOne = $set->remove(1);
        $this->assertCount(1, $resRemoveOne);
        $this->assertEquals($a, $resRemoveOne->first());

        $resRemoveOne = $set->remove(-1);
        $this->assertCount(1, $resRemoveOne);
        $this->assertEquals($a, $resRemoveOne->first());
    }

    public function test_replace_elements(): void
    {
        $a = Interval::between(Time::at(10), Time::at(13));
        $b = Interval::between(Time::at(12), Time::at(13));
        $set = new IntervalSet($a, $b);
        $c = Interval::between(Time::at(14), Time::at(15));

        $replaceNeg = $set->replace(-1, $c);
        $this->assertCount(2, $replaceNeg);
        $this->assertSame($c, $replaceNeg->last());
        $this->assertFalse($replaceNeg->has($b));

        $this->expectExceptionObject(
            new TimeException('Invalid offset (3) given to '.IntervalSet::class.'.')
        );
        $set->replace(3, $c);
    }

    public function test_set_can_be_serialized_and_unserialized(): void
    {
        $a = Interval::between(Time::at(10), Time::at(13));
        $b = Interval::between(Time::at(12), Time::at(13));
        $set = new IntervalSet($a, $b);

        $restored = unserialize(serialize($set));

        $this->assertInstanceOf(IntervalSet::class, $restored);
        $this->assertEquals($set, $restored);
    }

    public function test_normalize_sorts_intervals(): void
    {
        $a = Interval::between(Time::at(12), Time::at(14));
        $b = Interval::between(Time::at(10), Time::at(11));

        $normalized = new IntervalSet($a, $b)->union();
        $normalizedBis = $a->union($b);

        $this->assertTrue($normalized->first()?->equals($b));
        $this->assertTrue($normalized->last()?->equals($a));
        $this->assertEquals($normalizedBis, $normalized);
    }

    public function test_union_with_arguments(): void
    {
        $emptySet = new IntervalSet();
        $interval = Interval::between(Time::at(12), Time::at(14));
        $expectedSet = new IntervalSet($interval);

        $this->assertEquals($emptySet, $emptySet->union());
        $this->assertEquals($emptySet, $emptySet->union($emptySet));
        $this->assertEquals($expectedSet, $emptySet->union($interval));
        $this->assertEquals($expectedSet, $expectedSet->union($emptySet));
        $this->assertEquals($expectedSet, $expectedSet->union($interval));
    }

    public function test_normalize_merges_overlapping_intervals(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(14));

        $normalized = new IntervalSet($a, $b)->union();

        $this->assertCount(1, $normalized);

        $expected = Interval::between(Time::at(10), Time::at(14));
        $first = $normalized->first();
        $this->assertInstanceOf(Interval::class, $first);
        $this->assertTrue($expected->equals($first));
    }

    public function test_normalize_merges_abutting_intervals(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(12), Time::at(14));

        $normalized = new IntervalSet($a, $b)->union();

        $this->assertCount(1, $normalized);

        $expected = Interval::between(Time::at(10), Time::at(14));

        $first = $normalized->first();
        $this->assertInstanceOf(Interval::class, $first);
        $this->assertTrue($expected->equals($first));
    }

    public function test_normalize_keeps_disjoint_intervals(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(13), Time::at(14));

        $normalized = new IntervalSet($a, $b)->union();

        $this->assertCount(2, $normalized);
    }

    public function test_normalize_handles_circular_intervals(): void
    {
        $a = Interval::between(Time::at(22), Time::at(2));
        $b = Interval::between(Time::at(1), Time::at(3));

        $normalized = new IntervalSet($a, $b)->union();

        $this->assertCount(1, $normalized);

        $expected = Interval::between(Time::at(22), Time::at(3));

        $first = $normalized->first();
        $this->assertInstanceOf(Interval::class, $first);
        $this->assertTrue($expected->equals($first));
    }

    public function test_difference_of_disjoint_intervals_returns_original(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));
        $b = Interval::between(Time::at(10)->add(Duration::of(hours: 10)), Time::at(20)->add(Duration::of(hours: 10)));

        $result = new IntervalSet($a)->difference(
            new IntervalSet($b)
        );

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Interval::class, $result->first());
        $this->assertTrue($a->equals($result->first()));
    }

    public function test_difference_of_fully_contained_interval_splits_interval(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));
        $b = Interval::between(Time::at(13), Time::at(17));

        $result = new IntervalSet($a)->difference(
            new IntervalSet($b)
        );

        $this->assertCount(2, $result);

        $first = $result->first();
        $this->assertInstanceOf(Interval::class, $first);
        $this->assertTrue(Interval::between(Time::at(10), Time::at(13))->equals($first));

        $second = $result->nth(1);
        $this->assertInstanceOf(Interval::class, $second);
        $this->assertTrue(Interval::between(Time::at(17), Time::at(20))->equals($second));
    }

    public function test_difference_of_overlapping_left_side(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));
        $b = Interval::between(Time::at(5), Time::at(15));

        $result = new IntervalSet($a)->difference(
            new IntervalSet($b)
        );

        $this->assertCount(1, $result);
        $first = $result->first();
        $this->assertInstanceOf(Interval::class, $first);
        $this->assertTrue(Interval::between(Time::at(15), Time::at(20))->equals($first));
    }

    public function test_difference_of_overlapping_right_side(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));
        $b = Interval::between(Time::at(15), Time::at(23)->add(Duration::of(hours: 2)));

        $result = new IntervalSet($a)->difference(
            new IntervalSet($b)
        );

        $this->assertCount(1, $result);
        $first = $result->first();
        $this->assertInstanceOf(Interval::class, $first);
        $this->assertTrue(Interval::between(Time::at(10), Time::at(15))->equals($first));
    }

    public function test_difference_of_identical_intervals_returns_empty(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));

        $result = new IntervalSet($a)->difference(
            new IntervalSet($a)
        );

        $this->assertTrue($result->isEmpty());
    }

    public function test_difference_of_covering_interval_returns_empty(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));
        $b = Interval::between(Time::at(5), Time::at(2)->add(Duration::of(hours: 1)));

        $result = new IntervalSet($a)->difference(
            new IntervalSet($b)
        );

        $this->assertTrue($result->isEmpty());
    }

    public function test_normalize_is_idempotent(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(14));

        $set = new IntervalSet($a, $b);

        $normalized = $set->union();

        $this->assertEquals(
            $normalized,
            $normalized->union(),
        );
    }

    public function test_difference_with_empty_set_returns_original(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));

        $result = new IntervalSet($a)->difference();

        $this->assertCount(1, $result);
        $first = $result->first();
        $this->assertInstanceOf(Interval::class, $first);
        $this->assertTrue($a->equals($first));
    }

    public function test_difference_handles_circular_intervals(): void
    {
        $a = Interval::between(Time::at(22), Time::at(4));
        $b = Interval::between(Time::at(23), Time::at(1));

        $result = new IntervalSet($a)->difference($b);

        $this->assertCount(2, $result);
        $first = $result->first();
        $this->assertInstanceOf(Interval::class, $first);
        $this->assertTrue(Interval::between(Time::at(22), Time::at(23))->equals($first));

        $second = $result->nth(1);
        $this->assertInstanceOf(Interval::class, $second);
        $this->assertTrue(Interval::between(Time::at(1), Time::at(4))->equals($second));
    }

    public function test_map_transforms_intervals(): void
    {
        $set = new IntervalSet(
            $this->i(1, 2),
            $this->i(3, 4),
        );

        $result = $set->map(
            fn (Interval $i): string => $i->start->hour.'-'.$i->end->hour,
        );

        $this->assertSame(
            [
                '1-2',
                '3-4',
            ],
            iterator_to_array($result),
        );
    }

    public function test_map_preserves_order(): void
    {
        $set = new IntervalSet(
            $this->i(10, 12),
            $this->i(1, 3),
            $this->i(5, 7),
        );

        $result = $set->map(fn (Interval $i): Interval => $i);

        $this->assertSame(
            array_map(
                fn (Interval $i): int|float => $i->start->toUnitOfDay(Unit::Microsecond),
                $set->all(),
            ),
            array_map(
                fn (Interval $i): int|float => $i->start->toUnitOfDay(Unit::Microsecond),
                iterator_to_array($result),
            ),
        );
    }

    public function test_filter_keeps_matching_intervals(): void
    {
        $set = new IntervalSet(
            $this->i(1, 2),
            $this->i(3, 4),
            $this->i(5, 6),
        );

        $filtered = $set->filter(
            fn (Interval $i): bool => $i->start->toUnitOfDay(Unit::Microsecond) >= Time::at(3)->toUnitOfDay(Unit::Microsecond),
        );

        $this->assertCount(2, $filtered);

        $this->assertSame(
            [3, 5],
            array_map(
                fn (Interval $i): int => $i->start->hour,
                $filtered->all(),
            ),
        );
    }

    public function test_filter_returns_same_instance_if_no_change(): void
    {
        $set = new IntervalSet(
            $this->i(1, 2),
            $this->i(3, 4),
        );

        $filtered = $set->filter(fn (): true => true);

        $this->assertSame($set, $filtered);
    }

    public function test_filter_returns_empty_set_when_no_match(): void
    {
        $set = new IntervalSet(
            $this->i(1, 2),
            $this->i(3, 4),
        );

        $filtered = $set->filter(fn (): false => false);

        $this->assertTrue($filtered->isEmpty());
    }

    public function test_reduce_accumulates_values(): void
    {
        $set = new IntervalSet(
            $this->i(1, 2),
            $this->i(3, 5),
        );

        $totalDuration = $set->reduce(
            fn (int $carry, Interval $i): int => $carry + ($i->end->hour - $i->start->hour),
            0,
        );

        $this->assertSame(
            (2 - 1) + (5 - 3),
            $totalDuration,
        );
    }

    public function test_reduce_without_initial_value(): void
    {
        $set = new IntervalSet(
            $this->i(10, 20),
            $this->i(21, 23),
        );

        $result = $set->reduce(
            fn (?Interval $carry, Interval $i): Interval => $carry ?? $i,
        );

        $this->assertInstanceOf(Interval::class, $result);

        $this->assertSame(
            $this->i(10, 20)->start->toUnitOfDay(Unit::Microsecond),
            $result->start->toUnitOfDay(Unit::Microsecond),
        );
    }

    public function test_reduce_empty_set_returns_initial_value(): void
    {
        $set = new IntervalSet();

        $result = $set->reduce(
            fn (int $carry, Interval $i): int => $carry + 1,
            42,
        );

        $this->assertSame(42, $result);
    }

    public function test_formatted_strings(): void
    {
        $set = new IntervalSet($this->i(1, 2), $this->i(3, 4));

        $this->assertSame([$this->i(1, 2)->toNotation(IntervalNotation::Iso80000), $this->i(3, 4)->toNotation(IntervalNotation::Iso80000)], $set->allFormatted(IntervalNotation::Iso80000));
        $this->assertSame([$this->i(1, 2)->toNotation(), $this->i(3, 4)->toNotation()], $set->allFormatted());
        $this->assertSame([$this->i(1, 2)->toNotation(IntervalNotation::Bourbaki), $this->i(3, 4)->toNotation(IntervalNotation::Bourbaki)], $set->allFormatted(IntervalNotation::Bourbaki));
    }

    public function test_json_encoded_set(): void
    {
        $this->assertStringContainsString('"12:00:00/PT6H"', (string) json_encode(Business::shifts(), JSON_UNESCAPED_SLASHES));
    }

    public function test_native_conversion(): void
    {
        $class = new class() extends DateTimeImmutable {};
        $timeZoneName = 'Africa/Brazzaville';

        $converted = Business::shifts()->allNative(
            new $class('2025-03-02 23:12:59', new DateTimeZone($timeZoneName))
        );
        $this->assertCount(5, $converted);

        $interval = $converted[1];
        $this->assertInstanceOf($class::class, $interval['startDate']);
        $this->assertSame($interval['startDate']->getTimezone()->getName(), $timeZoneName);
        $this->assertSame('2025-03-02 12:00:00', $interval['startDate']->format('Y-m-d H:i:s'));
        $this->assertEquals(
            new DateInterval('PT6H'), $interval['interval']
        );
    }

    public function test_it_can_be_iterated_with_foreach(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::at(hour: 10), Time::at(hour: 11)),
            Interval::between(Time::noon(), Time::at(hour: 13)),
        );

        $results = [];

        foreach ($set as $item) {
            $results[] = $item;
        }

        $this->assertCount(2, $results);
        $this->assertSame($set->first(), $results[0]);
        $this->assertSame($set->last(), $results[1]);
    }

    public function test_union_on_empty_set(): void
    {
        $this->assertTrue(
            new IntervalSet()->union()->isEmpty()
        );
    }

    public function test_difference_on_empty_set(): void
    {
        $this->assertTrue(
            new IntervalSet()->difference()->isEmpty()
        );
        $this->assertEquals(Business::shifts(), Business::shifts()->difference());
        $this->assertTrue(
            new IntervalSet()->difference(Business::shifts())->isEmpty()
        );
    }

    public function test_sorted_on_empty_set(): void
    {
        $set = new IntervalSet();
        $this->assertSame($set, $set->sorted());
    }

    public function test_sorted_on_an_already_sorted_set(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::at(hour: 10), Time::at(hour: 13)),
            Interval::between(Time::noon(), Time::at(hour: 13)),
        );

        $this->assertSame($set, $set->sorted());
    }

    public function test_sorting_a_set_returns_a_new_set(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::noon(), Time::at(hour: 13)),
            Interval::between(Time::at(hour: 10), Time::at(hour: 13)),
        );
        $sorted = $set->sorted();

        $this->assertNotSame($set, $sorted);
        $this->assertSame($set->last(), $sorted->first());
    }

    public function test_sorting_a_set_descending(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::at(hour: 10), Time::at(hour: 13)),
            Interval::between(Time::noon(), Time::at(hour: 13)),
        );
        $sorted = $set->sorted(sortDirection: 'descending');

        $this->assertNotSame($set, $sorted);
        $this->assertSame($set->last(), $sorted->first());
    }

    public function test_sorting_with_an_invalid_sorting_direction(): void
    {
        $this->expectException(ValueError::class);

        new IntervalSet()->sorted(Bound::Start, 'foo');
    }

    /**
     * @throws InvalidDuration
     */
    public function test_complement_of_empty_set_is_full_day(): void
    {
        $set = new IntervalSet();

        $this->assertEquals(
            new IntervalSet(Interval::fullDay()), $set->complement()
        );
    }

    /**
     * @throws InvalidDuration
     */
    public function test_complement_of_full_day_is_empty(): void
    {
        $set = new IntervalSet(Interval::fullDay());

        $this->assertTrue($set->complement()->isEmpty());
    }

    public function test_complement_single_interval(): void
    {
        $set = new IntervalSet(Interval::between(Time::at(10), Time::noon()));
        $expected = new IntervalSet(
            Interval::between(Time::midnight(), Time::at(10)),
            Interval::between(Time::noon(), Time::midnight()),
        );

        $this->assertEquals($expected->allFormatted(), $set->complement()->allFormatted());
    }

    public function test_complement_interval_at_start(): void
    {
        $set = new IntervalSet(Interval::between(Time::midnight(), Time::at(3)));
        $expected = new IntervalSet(Interval::between(Time::at(3, 0), Time::midnight()));

        $this->assertEquals($expected->allFormatted(), $set->complement()->allFormatted());
    }

    public function test_complement_interval_at_end(): void
    {
        $set = new IntervalSet(Interval::between(Time::at(22, 0), Time::midnight()));
        $expected = new IntervalSet(Interval::between(Time::midnight(), Time::at(22)));

        $this->assertEquals($expected->allFormatted(), $set->complement()->allFormatted());
    }

    public function test_complement_multiple_intervals(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::at(2), Time::at(4)),
            Interval::between(Time::at(10), Time::noon()),
        );

        $expected = new IntervalSet(
            Interval::between(Time::midnight(), Time::at(2)),
            Interval::between(Time::at(4), Time::at(10)),
            Interval::between(Time::noon(), Time::midnight()),
        );

        $this->assertEquals($expected->allFormatted(), $set->complement()->allFormatted());
    }

    public function test_complement_of_circular_interval(): void
    {
        $set = new IntervalSet(Interval::fullDay());

        $this->assertTrue($set->complement()->isEmpty());
    }

    public function test_complement_is_involutive(): void
    {
        $set = new IntervalSet(Interval::between(Time::at(3), Time::at(7)));

        $this->assertEquals($set->allFormatted(), $set->complement()->complement()->allFormatted());
    }

    public function test_intersect_returns_itself_with_empty_intervals(): void
    {
        $set = new IntervalSet();
        $this->assertSame($set, $set->intersect(
            new IntervalSet()
        ));
        $this->assertSame($set, $set->intersect());

        $setBis = new IntervalSet(Interval::between(Time::at(3), Time::at(7)));
        $this->assertSame($setBis, $setBis->intersect());
        $this->assertSame($setBis, $setBis->intersect(
            new IntervalSet()
        ));
    }

    public function test_index_of(): void
    {
        $set = new IntervalSet();
        $notFound = Interval::between(Time::at(3), Time::at(7));
        $this->assertNull($set->indexOf($notFound));
        $this->assertNull($set->lastIndexOf($notFound));

        $expected = new IntervalSet(
            $found = Interval::between(Time::midnight(), Time::at(2)),
            Interval::between(Time::at(4), Time::at(10)),
            $found,
            Interval::between(Time::noon(), Time::midnight()),
        );

        $this->assertSame(0, $expected->indexOf($found));
        $this->assertSame(2, $expected->lastIndexOf($found));
        $this->assertNull($expected->indexOf($notFound));
        $this->assertNull($expected->lastIndexOf($notFound));
    }

    public function test_any(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::midnight(), Time::at(2)),
            Interval::between(Time::at(4), Time::at(10)),
            Interval::between(Time::noon(), Time::midnight()),
        );

        $this->assertTrue($set->any(fn (Interval $interval): bool => $interval->includes(Time::at(6))));
        $this->assertFalse($set->any(fn (Interval $interval, ?int $offset = null): bool => $interval->includes(Time::at(6)) && 0 === $offset));
    }

    public function test_every(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::midnight(), Time::at(2)),
            Interval::between(Time::at(4), Time::at(10)),
            Interval::between(Time::noon(), Time::midnight()),
        );

        $this->assertFalse($set->every(fn (Interval $interval): bool => $interval->includes(Time::at(6))));
        $this->assertTrue($set->every(fn (Interval $interval, ?int $offset = null): bool => true));
    }

    public function test_differences_are_the_same_independant_of_circular_interval(): void
    {
        $intervalSet = new IntervalSet(Interval::between(Time::midnight(), Time::noon()));

        $diff1 = new IntervalSet(Interval::circular(Time::at(hour: 10)))
            ->difference($intervalSet)
            ->allFormatted();

        $diff2 = new IntervalSet(Interval::fullDay())
            ->difference($intervalSet)
            ->allFormatted();

        $this->assertEquals($diff1, $diff2);
    }

    public function test_it_iterates_over_all_intervals(): void
    {
        $intervals = new IntervalSet(
            Interval::between(Time::at(1), Time::at(2)),
            Interval::between(Time::at(3), Time::at(4)),
            Interval::between(Time::at(5), Time::at(6)),
        );

        $visited = [];

        $result = $intervals->each(
            function (Interval $interval, ?int $index = null) use (&$visited): void {
                $visited[] = $index;
            },
        );

        $this->assertTrue($result);
        $this->assertSame([0, 1, 2], $visited);
    }

    public function test_it_stops_iteration_when_callback_returns_false(): void
    {
        $intervals = new IntervalSet(
            Interval::between(Time::at(1), Time::at(2)),
            Interval::between(Time::at(3), Time::at(4)),
            Interval::between(Time::at(5), Time::at(6)),
        );

        $visited = [];

        $result = $intervals->each(
            function (Interval $interval, ?int $index = null) use (&$visited): bool {
                $visited[] = $index;

                return 1 !== $index;
            },
        );

        $this->assertFalse($result);
        $this->assertSame([0, 1], $visited);
    }

    public function test_it_returns_true_on_empty_collection(): void
    {
        $intervals = new IntervalSet();

        $visited = false;

        $result = $intervals->each(
            function () use (&$visited): void {
                $visited = true;
            },
        );

        $this->assertTrue($result);
        $this->assertFalse($visited);
    }

    public function test_gaps_with_empty_collection(): void
    {
        $intervals = new IntervalSet();

        $this->assertSame($intervals, $intervals->gaps());
    }

    private function i(int $start, int $end): Interval
    {
        return Interval::between(Time::at($start), Time::at($end));
    }
}

enum Business
{
    case Morning;
    case Afternoon;
    case Evening;
    case Night;
    case Day;

    /**
     * @throws InvalidDuration|InvalidTime
     */
    public static function shifts(): IntervalSet
    {
        return new IntervalSet(...array_map(static fn (self $case): Interval => $case->interval(), self::cases()));
    }

    /**
     * @throws InvalidDuration|InvalidTime
     */
    public function interval(): Interval
    {
        return match ($this) {
            self::Morning => Interval::between(Time::at(6), Time::noon()),
            self::Afternoon => Interval::between(Time::noon(), Time::at(18)),
            self::Evening => Interval::between(Time::at(18), Time::at(22)),
            self::Night => Interval::between(Time::at(22), Time::at(6)),
            self::Day => Interval::between(Time::at(6), Time::at(22)),
        };
    }
}
