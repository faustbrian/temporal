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

use function array_keys;
use function json_encode;
use function mb_strlen;

/**
 * @internal
 */
final class SequenceTest extends PeriodTestCase
{
    public function test_is_empty(): void
    {
        $sequence = new Sequence();
        $this->assertTrue($sequence->isEmpty());
        $this->assertCount(0, $sequence);
        $this->assertNotInstanceOf(Period::class, $sequence->length());
        $sequence->push(Period::fromDay(2_012, 6, 23));
        $this->assertFalse($sequence->isEmpty());
        $this->assertCount(1, $sequence);
        $this->assertInstanceOf(Period::class, $sequence->length());
    }

    public function test_constructor(): void
    {
        $sequence = new Sequence(Period::fromDay(2_012, 6, 23), Period::fromDay(2_012, 6, 23));
        $this->assertCount(2, $sequence);
    }

    public function test_remove(): void
    {
        $event1 = Period::fromDay(2_012, 6, 23);
        $event2 = Period::fromDay(2_012, 6, 23);
        $sequence = new Sequence($event1, $event2);
        $this->assertSame($event1, $sequence->remove(0));
        $this->assertTrue($sequence->contains($event1));
        $this->assertCount(1, $sequence);
        $this->assertSame($event2, $sequence->remove(0));
        $this->assertCount(0, $sequence);
        $this->assertFalse($sequence->contains($event2));
        $this->expectException(InaccessibleInterval::class);
        $sequence->remove(1);
    }

    public function test_getter(): void
    {
        $event1 = Period::fromDay(2_012, 6, 23);
        $event2 = Period::fromDay(2_012, 6, 23);
        $event3 = Period::fromDay(2_012, 6, 25);
        $sequence = new Sequence($event1, $event2);
        $this->assertInstanceOf(Period::class, $sequence->length());
        $this->assertTrue($sequence->contains($event2));
        $this->assertTrue($sequence->contains($event1));
        $this->assertTrue($sequence->contains(Period::fromDay(2_012, 6, 23)));
        $this->assertSame($event2, $sequence->get(1));
        $this->assertSame(0, $sequence->indexOf(Period::fromDay(2_012, 6, 23)));
        $this->assertFalse($sequence->indexOf(Period::fromDay(2_014, 6, 23)));
        $sequence->push($event3);
        $this->assertCount(3, $sequence);
        $this->assertSame(2, $sequence->indexOf($event3));
        $sequence->unshift(Period::fromDay(2_018, 8, 8));
        $this->assertCount(4, $sequence);
        $this->assertTrue(Period::fromDay(2_018, 8, 8)->equals($sequence->get(0)));
        $sequence->clear();
        $this->assertTrue($sequence->isEmpty());
        $this->assertNotInstanceOf(Period::class, $sequence->length());
    }

    public function test_get_throws_exception_with_invalid_positive_index(): void
    {
        $this->expectException(InaccessibleInterval::class);
        new Sequence(DatePoint::fromDateString('2011-06-23')->day())->get(3);
    }

    public function test_get_throws_exception_with_invalid_negative_index(): void
    {
        $this->expectException(InaccessibleInterval::class);
        new Sequence(DatePoint::fromDateString('2011-06-23')->day())->get(-3);
    }

    public function test_negative_offset_with_a_sequence_with_a_single_item(): void
    {
        $sequence = new Sequence(DatePoint::fromDateString('today')->day());
        $this->assertSame($sequence[-1], $sequence[0]);
    }

    public function test_setter(): void
    {
        $sequence = new Sequence(DatePoint::fromDateString('2011-06-23')->day(), DatePoint::fromDateString('2011-06-23')->day());
        $sequence->set(0, DatePoint::fromDateString('2011-06-23')->day());
        $this->assertEquals(DatePoint::fromDateString('2011-06-23')->day(), $sequence->get(0));
        $sequence->set(1, Period::fromDay(2_012, 6, 23));
        $sequence->set(0, DatePoint::fromDateString('2013-06-23')->day());
        $this->assertEquals(Period::fromDay(2_012, 6, 23), $sequence->get(1));
        $this->assertEquals(DatePoint::fromDateString('2013-06-23')->day(), $sequence->get(0));
        $this->expectException(InaccessibleInterval::class);
        $sequence->set(3, DatePoint::fromDateString('2013-06-23')->day());
    }

    public function test_insert(): void
    {
        $sequence = new Sequence();
        $sequence->insert(0, DatePoint::fromDateString('2010-06-23')->day());
        $this->assertCount(1, $sequence);
        $sequence->insert(1, DatePoint::fromDateString('2011-06-24')->day());
        $this->assertCount(2, $sequence);
        $sequence->insert(-1, DatePoint::fromDateString('2012-06-25')->day());
        $this->assertCount(3, $sequence);
        $this->assertTrue(DatePoint::fromDateString('2012-06-25')->day()->equals($sequence->get(1)));
        $this->expectException(InaccessibleInterval::class);
        $sequence->insert(42, DatePoint::fromDateString('2011-06-23')->day());
    }

    public function test_json_serialize(): void
    {
        $day = DatePoint::fromDateString('2010-06-23')->day();
        $this->assertSame('['.json_encode($day).']', json_encode(
            new Sequence($day)
        ));
    }

    public function test_filter_returns_new_instance(): void
    {
        $sequence = new Sequence(Period::fromDay(2_012, 6, 23), DatePoint::fromDateString('2012-06-12')->day());
        $newCollection = $sequence->filter(fn (Period $period): bool => $period->startDate === CarbonImmutable::parse('2012-06-23'));

        $this->assertNotEquals($newCollection, $sequence);
        $this->assertCount(1, $newCollection);
    }

    public function test_filter_returns_same_instance(): void
    {
        $sequence = new Sequence(
            Period::fromDay(2_012, 6, 23),
            DatePoint::fromDateString('2012-06-12')->day(),
        );

        $this->assertSame($sequence, $sequence->filter(fn (Period $interval): bool => $interval->endDate >= $interval->startDate));
    }

    public function test_sorted_returns_same_instance(): void
    {
        $sequence = new Sequence(
            Period::fromDay(2_012, 6, 23),
            DatePoint::fromDateString('2012-06-12')->day(),
        );

        $this->assertSame($sequence, $sequence->sorted(fn (Period $event1, Period $event2): int => mb_strlen($event1::class) - mb_strlen($event2::class)));
    }

    public function test_sorted_returns_new_instance(): void
    {
        $sequence = new Sequence(
            Period::fromMonth(2_012, 6),
            Period::fromDay(2_012, 6, 23),
            Period::fromIsoWeek(2_018, 3),
        );

        $this->assertNotSame($sequence, $sequence->sorted(fn (Period $event1, Period $event2): int => $event1->durationCompare($event2)));
    }

    public function test_sort(): void
    {
        $day1 = Period::fromDay(2_012, 6, 23);
        $day2 = DatePoint::fromDateString('2012-06-12')->day();
        $sequence = new Sequence($day1, $day2);
        $this->assertSame([0 => $day1, 1 => $day2], $sequence->toList());

        $sequence->sort(fn (Period $period1, Period $period2): int => $period1->startDate <=> $period2->startDate);
        $this->assertSame([0 => $day2, 1 => $day1], $sequence->toList());
    }

    public function test_some(): void
    {
        $interval = Period::after(
            CarbonImmutable::parse('2012-02-01 12:00:00'), Duration::fromDateString('1 HOUR')
        );
        $predicate = fn (Period $event): bool => $interval->overlaps($event);

        $sequence = new Sequence(
            DatePoint::fromDateString('2012-02-01')->day(),
            DatePoint::fromDateString('2013-02-01')->day(),
            DatePoint::fromDateString('2014-02-01')->day(),
        );

        $this->assertTrue($sequence->some($predicate));
        $this->assertFalse(
            new Sequence()->some($predicate)
        );
    }

    public function test_every(): void
    {
        $sequence = new Sequence(
            DatePoint::fromDateString('2012-02-01')->day(),
            DatePoint::fromDateString('2013-02-01')->day(),
            DatePoint::fromDateString('2014-02-01')->day(),
        );

        $interval = Period::after(
            Date::parse('2012-01-01'), Duration::fromDateString('5 YEARS')
        );
        $predicate = fn (Period $event): bool => $interval->contains($event);

        $this->assertTrue($sequence->every($predicate));
        $this->assertFalse(
            new Sequence()->every($predicate)
        );
        $this->assertFalse(
            new Sequence(DatePoint::fromDateString('1988-02-01')->day())->every($predicate)
        );
    }

    /**
     * subtract test 1.
     *
     *  [-------------)      [------------)
     *                   -
     *       [--)         [---------------------)
     *                   =
     *  [----)   [----)
     */
    public function test_subtract1(): void
    {
        $sequenceA = new Sequence(
            Period::fromDate(
                Date::parse('2000-01-01'), Date::parse('2000-01-10')
            ),
            Period::fromDate(
                Date::parse('2000-01-12'), Date::parse('2000-01-20')
            ),
        );
        $sequenceB = new Sequence(
            Period::fromDate(
                Date::parse('2000-01-05'), Date::parse('2000-01-08')
            ),
            Period::fromDate(
                Date::parse('2000-01-11'), Date::parse('2000-01-25')
            ),
        );
        $diff = $sequenceA->subtract($sequenceB);

        $this->assertCount(2, $diff);
        $this->assertSame('[2000-01-01, 2000-01-05)', $diff->get(0)->toIso80000('Y-m-d'));
        $this->assertSame('[2000-01-08, 2000-01-10)', $diff->get(1)->toIso80000('Y-m-d'));
        $this->assertEquals($diff, $sequenceA->subtract($sequenceB));
    }

    /**
     * subtract test 2.
     *
     *  [------)      [------)      [------)
     *                   -
     *  [------------------------------------------)
     *                   =
     *  ()
     */
    public function test_subtract2(): void
    {
        $sequenceA = new Sequence(
            Period::fromDate(
                Date::parse('2000-01-01'), Date::parse('2000-01-05')
            ),
            Period::fromDate(
                Date::parse('2000-01-10'), Date::parse('2000-01-15')
            ),
            Period::fromDate(
                Date::parse('2000-01-20'), Date::parse('2000-01-25')
            ),
        );
        $sequenceB = new Sequence(
            Period::fromDate(
                Date::parse('2000-01-01'), Date::parse('2000-01-30')
            ),
        );
        $diff = $sequenceA->subtract($sequenceB);

        $this->assertCount(0, $diff);
    }

    /**
     * subtract test 3.
     */
    public function test_subtract3(): void
    {
        $sequenceA = new Sequence(
            Period::fromDate(
                Date::parse('2000-01-01'), Date::parse('2000-01-10')
            ),
            Period::fromDate(
                Date::parse('2000-01-12'), Date::parse('2000-01-20')
            ),
        );
        $sequenceB = new Sequence();

        $diff1 = $sequenceA->subtract($sequenceB);
        $this->assertCount(2, $diff1);
        $this->assertSame('[2000-01-01, 2000-01-10)', $diff1->get(0)->toIso80000('Y-m-d'));
        $this->assertSame('[2000-01-12, 2000-01-20)', $diff1->get(1)->toIso80000('Y-m-d'));

        $diff2 = $sequenceB->subtract($sequenceA);
        $this->assertCount(0, $diff2);
    }

    /**
     * subtract test 4.
     */
    public function test_subtract4(): void
    {
        $sequenceA = new Sequence(
            Period::fromDate(
                Date::parse('2000-01-01'), Date::parse('2000-01-10')
            ),
            Period::fromDate(
                Date::parse('2000-01-12'), Date::parse('2000-01-20')
            ),
        );
        $sequenceB = new Sequence(Period::fromDate(
            Date::parse('2003-01-12'), Date::parse('2003-01-20')
        ));
        $this->assertSame($sequenceA, $sequenceA->subtract($sequenceB));
    }

    /**
     * subtract test 5.
     */
    public function test_subtract5(): void
    {
        $sequenceA = new Sequence(
            Period::fromDate(
                Date::parse('2000-01-01'), Date::parse('2000-01-10')
            ),
            Period::fromDate(
                Date::parse('2001-01-01'), Date::parse('2001-01-10')
            ),
        );
        $sequenceB = new Sequence(Period::fromDate(
            Date::parse('2000-01-01'), Date::parse('2000-01-10')
        ));
        $this->assertCount(0, $sequenceB->subtract($sequenceA));
    }

    /**
     * Intersections test 1.
     *
     *               [------------)
     *                    [--)
     *                    [-------)
     *
     *                 =
     *
     *                    [--)
     */
    public function test_get_intersections1(): void
    {
        $sequence = new Sequence(
            Period::fromDate(
                Date::parse('2018-01-01'), Date::parse('2018-01-31')
            ),
            Period::fromDate(
                Date::parse('2018-01-10'), Date::parse('2018-01-15')
            ),
            Period::fromDate(
                Date::parse('2018-01-10'), Date::parse('2018-01-31')
            ),
        );
        $intersections = $sequence->intersections();

        $this->assertCount(2, $intersections);
        $this->assertSame('[2018-01-10, 2018-01-15)', $intersections->get(0)->toIso80000('Y-m-d'));
        $this->assertSame('[2018-01-10, 2018-01-31)', $intersections->get(1)->toIso80000('Y-m-d'));
    }

    /**
     * Intersections test 2.
     *
     *        [--------)
     *                     [--)
     *                            [------)
     *               [---------------)
     *
     *                 =
     *
     *               [-)   [--)   [--)
     */
    public function test_get_intersections2(): void
    {
        $sequence = new Sequence(
            Period::fromDate(
                Date::parse('2018-01-01'), Date::parse('2018-01-31')
            ),
            Period::fromDate(
                Date::parse('2018-02-10'), Date::parse('2018-02-20')
            ),
            Period::fromDate(
                Date::parse('2018-03-01'), Date::parse('2018-03-31')
            ),
            Period::fromDate(
                Date::parse('2018-01-20'), Date::parse('2018-03-10')
            ),
        );
        $intersections = $sequence->intersections();
        $this->assertCount(3, $intersections);
        $this->assertSame('[2018-01-20, 2018-01-31)', $intersections->get(0)->toIso80000('Y-m-d'));
        $this->assertSame('[2018-02-10, 2018-02-20)', $intersections->get(1)->toIso80000('Y-m-d'));
        $this->assertSame('[2018-03-01, 2018-03-10)', $intersections->get(2)->toIso80000('Y-m-d'));
    }

    /**
     * gaps test 1.
     *
     *           [--)
     *                    [----)
     *        [-------)
     *
     *                 =
     *
     *                [---)
     */
    public function test_gaps1(): void
    {
        $sequence = new Sequence(
            DatePoint::fromDateString('2018-11-29')->day(),
            Period::after(
                CarbonImmutable::parse('2018-11-29 + 7 DAYS'), DateInterval::createFromDateString('1 DAY')
            ),
            Period::around(
                CarbonImmutable::parse('2018-11-29'), DateInterval::createFromDateString('4 DAYS')
            ),
        );

        $gaps = $sequence->gaps();
        $this->assertCount(1, $gaps);
        $this->assertSame('[2018-12-03, 2018-12-06)', $gaps->get(0)->toIso80000('Y-m-d'));
    }

    /**
     * gaps test 2.
     *
     * No gaps expected
     *
     *          [--)
     *         [----)
     */
    public function test_gaps2(): void
    {
        $sequence = new Sequence(
            DatePoint::fromDateString('2018-11-29')->day(),
            Period::around(
                Date::parse('2018-11-29'), Duration::fromDateString('4 DAYS')
            ),
        );

        $gaps = $sequence->gaps();
        $this->assertTrue($gaps->isEmpty());
    }

    public function test_union_returns_same_instance(): void
    {
        $sequence = new Sequence(DatePoint::fromDateString('2018-11-29')->day());
        $this->assertSame($sequence, $sequence->unions());
    }

    public function test_union(): void
    {
        $sequence = new Sequence(
            DatePoint::fromDateString('2018-11-29')->year(),
            DatePoint::fromDateString('2018-11-29')->month(),
            Period::around(
                CarbonImmutable::parse('2016-06-01'), Duration::fromDateString('3 MONTHS')
            ),
        );

        $unions = $sequence->unions();
        $this->assertEquals($sequence->length(), $unions->length());
        $this->assertTrue($unions->intersections()->isEmpty());
        $this->assertEquals($sequence->gaps(), $unions->gaps());
        $this->assertTrue(Period::around(
            CarbonImmutable::parse('2016-06-01'), Duration::fromDateString('3 MONTHS')
        )->equals($unions->get(0)));
        $this->assertTrue(DatePoint::fromDateString('2018-11-29')->year()->equals($unions->get(1)));
    }

    public function test_map(): void
    {
        $sequence = new Sequence(
            Period::fromMonth(2_018, 1),
            Period::fromDay(2_018, 1, 1),
        );

        $newSequence = $sequence->map(function (Period $period, int $offset): Period {
            if (1 === $offset) {
                return $period;
            }

            return $period->startingOn(
                CarbonImmutable::parse('2018-01-15')
            );
        });

        $this->assertSame($newSequence->get(1), $sequence->get(1));
        $this->assertSame('[2018-01-15, 2018-02-01)', $newSequence->get(0)->toIso80000('Y-m-d'));
    }

    public function test_map_returns_same_instance(): void
    {
        $sequence = new Sequence(
            Period::fromMonth(2_018, 1),
            Period::fromDay(2_018, 1, 1),
        );

        $newSequence = $sequence->map(fn (Period $period): Period => $period);

        $this->assertSame($newSequence, $sequence);
    }

    public function test_mapper_does_not_re_index_after_modification(): void
    {
        $sequence = new Sequence(Period::fromDay(2_018, 3, 1), Period::fromDay(2_018, 1, 1));
        $sequence->sort(fn (Period $interval1, Period $interval2): int => $interval1->startDate <=> $interval2->startDate);

        $retval = $sequence->map(fn (Period $interval): Period => $interval->moveEndDate(Duration::fromDateString('+1 DAY')));

        $this->assertSame(array_keys($sequence->toList()), array_keys($retval->toList()));
    }

    public function test_array_access(): void
    {
        $sequence = new Sequence();
        $sequence[] = Period::fromMonth(2_018, 1);
        $this->assertArrayHasKey(0, $sequence);
        $this->assertEquals(Period::fromMonth(2_018, 1), $sequence[0]);
        $sequence[0] = Period::fromMonth(2_017, 1);
        $this->assertNotEquals(Period::fromMonth(2_018, 1), $sequence[0]);
        unset($sequence[0]);
    }

    public function test_array_access_throws_invalid_index(): void
    {
        $this->expectException(InaccessibleInterval::class);
        $sequence = new Sequence();
        $sequence[0] = Period::fromMonth(2_017, 1);
    }

    public function test_array_access_throws_invalid_index2(): void
    {
        $this->expectException(InaccessibleInterval::class);
        $sequence = new Sequence();
        unset($sequence[0]);
    }

    public function test_total_time_duration(): void
    {
        $this->assertSame(0, new Sequence()->totalTimeDuration());

        $sequence = new Sequence(Period::fromMonth(2_017, 1), Period::fromMonth(2_018, 1));
        $period = $sequence->length();

        if (!$period instanceof Period) {
            return;
        }

        $this->assertNotSame($period->timeDuration(), $sequence->totalTimeDuration());
    }

    public function test_issue134_remove_duplicate_on_intersection(): void
    {
        $p1 = Period::fromDate('2023-01-01 00:00:00', '2023-01-03 00:00:00');
        $p2 = Period::fromDate('2023-01-01 00:00:00', '2023-01-03 00:00:00');
        $p3 = Period::fromDate('2023-01-01 00:00:00', '2023-01-03 00:00:00');
        $p4 = Period::fromDate('2023-01-02 00:00:00', '2023-01-04 00:00:00');
        $p5 = Period::fromDate('2023-01-02 00:00:00', '2023-01-04 00:00:00');

        $sequence = new Sequence($p1, $p2, $p3, $p4, $p5);
        $this->assertCount(2, $sequence->intersections());
    }
}
