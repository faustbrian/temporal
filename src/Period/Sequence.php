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

use ArrayAccess;
use Closure;
use Countable;
use Iterator;
use IteratorAggregate;
use JsonSerializable;

use const ARRAY_FILTER_USE_BOTH;

use function array_any;
use function array_filter;
use function array_splice;
use function array_unshift;
use function array_values;
use function count;
use function reset;
use function uasort;
use function usort;

/**
 * Mutable collection type for orchestrating multiple {@see Period} instances.
 *
 * `Sequence` provides higher-order range operations such as union, intersection,
 * gap detection, sorting, and subtraction across a set of periods. Unlike the
 * `Time` namespace interval-set type, this class is intentionally mutable through
 * ArrayAccess-style operations so callers can build and refine working sets.
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since   4.1.0
 *
 * @implements IteratorAggregate<int, Period>
 * @implements ArrayAccess<int, Period>
 */
final class Sequence implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /** @var array<Period> */
    private array $periods;

    public function __construct(Period ...$periods)
    {
        $this->periods = $periods;
    }

    /**
     * Collapse the sequence to the smallest period that covers every member.
     *
     * If the sequence is empty, `null` is returned instead of inventing a
     * sentinel period.
     */
    public function length(): ?Period
    {
        $period = reset($this->periods);

        return match (false) {
            $period => null,
            default => $period->merge(...$this->periods),
        };
    }

    /**
     * Returns the sum of all instances durations as expressed in seconds.
     */
    public function totalTimeDuration(): int
    {
        return $this->reduce(fn (int $timestamp, Period $period): int => $timestamp + $period->timeDuration(), 0);
    }

    /**
     * Return every uncovered gap between sorted, non-touching periods.
     */
    public function gaps(): self
    {
        $sequence = new self();
        $interval = null;

        foreach ($this->sorted($this->sortByStartDate(...)) as $period) {
            if (!$interval instanceof Period) {
                $interval = $period;

                continue;
            }

            if (!$interval->overlaps($period) && !$interval->meets($period)) {
                $sequence->push($interval->gap($period));
            }

            if ($interval->contains($period)) {
                continue;
            }

            $interval = $period;
        }

        return $sequence;
    }

    /**
     * Return every overlapping segment discovered while traversing the sequence.
     */
    public function intersections(): self
    {
        $current = null;
        $isPreviouslyContained = false;
        $reducer = function (Sequence $sequence, Period $period) use (&$current, &$isPreviouslyContained): Sequence {
            if (!$current instanceof Period) {
                $current = $period;

                return $sequence;
            }

            /** @var Period $current */
            $isContained = $current->contains($period);

            if (!$sequence->contains($current)) {
                if ($isContained && $isPreviouslyContained) {
                    $sequence->push($current->intersect($period));

                    return $sequence;
                }

                if ($current->overlaps($period)) {
                    $sequence->push($current->intersect($period));
                }
            }

            $isPreviouslyContained = $isContained;

            if (!$isContained) {
                $current = $period;
            }

            return $sequence;
        };

        return $this
            ->sorted($this->sortByStartDate(...))
            ->reduce($reducer, new self());
    }

    /**
     * Merge touching and overlapping periods into their unioned representation.
     */
    public function unions(): self
    {
        $sequence = $this
            ->sorted($this->sortByStartDate(...))
            ->reduce($this->calculateUnion(...), new self());

        return match ($sequence->periods) {
            $this->periods => $this,
            default => $sequence,
        };
    }

    /**
     * Subtract a Sequence from the current instance.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains subtracted intervals.
     */
    public function subtract(self $sequence): self
    {
        $new = $sequence->reduce($this->subtractOne(...), $this);

        return match ($this->periods) {
            $new->periods => $this,
            default => $new,
        };
    }

    /**
     * Tells whether some intervals in the current instance satisfies the predicate.
     */
    public function some(Closure $predicate): bool
    {
        return array_any($this->periods, fn ($interval, $offset): bool => true === $predicate($interval, $offset));
    }

    /**
     * Tells whether all intervals in the current instance satisfies the predicate.
     */
    public function every(Closure $predicate): bool
    {
        foreach ($this->periods as $offset => $interval) {
            if (true !== $predicate($interval, $offset)) {
                return false;
            }
        }

        return [] !== $this->periods;
    }

    /**
     * Returns the list representation of the sequence where keys consist of consecutive numbers from 0 to count($array)-1.
     *
     * @return array<Period>
     */
    public function toList(): array
    {
        return array_values($this->periods);
    }

    /**
     * @return array<Period>
     */
    public function jsonSerialize(): array
    {
        return $this->periods;
    }

    /**
     * @return Iterator<int, Period>
     */
    public function getIterator(): Iterator
    {
        /** @var int $offset */
        foreach ($this->periods as $offset => $interval) {
            yield $offset => $interval;
        }
    }

    public function count(): int
    {
        return count($this->periods);
    }

    /**
     * @param int $offset the integer index of the Period instance to validate.
     */
    public function offsetExists($offset): bool
    {
        return null !== $this->filterOffset($offset);
    }

    /**
     * @see ::get
     * @param  int                  $offset the integer index of the Period instance to retrieve.
     * @throws InaccessibleInterval If the offset is illegal for the current sequence
     */
    public function offsetGet($offset): Period
    {
        return $this->get($offset);
    }

    /**
     * @see ::remove
     * @param  int                  $offset the integer index of the Period instance to remove
     * @throws InaccessibleInterval If the offset is illegal for the current sequence
     */
    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    /**
     * @see ::push
     * @see ::set
     * @param  null|int             $offset the integer index of the Period to add or update
     * @param  Period               $value  the Period instance to add
     * @throws InaccessibleInterval If the offset is illegal for the current sequence
     */
    public function offsetSet($offset, $value): void
    {
        if (null !== $offset) {
            $this->set($offset, $value);

            return;
        }

        $this->push($value);
    }

    /**
     * Tells whether the sequence is empty.
     */
    public function isEmpty(): bool
    {
        return [] === $this->periods;
    }

    /**
     * Tells whether the given interval is present in the sequence.
     */
    public function contains(Period ...$intervals): bool
    {
        foreach ($intervals as $period) {
            if (false === $this->indexOf($period)) {
                return false;
            }
        }

        return [] !== $intervals;
    }

    /**
     * Attempts to find the first offset attached to the submitted interval.
     *
     * If no offset is found the method returns boolean false.
     */
    public function indexOf(Period $interval): int|false
    {
        /** @var int $offset */
        foreach ($this->periods as $offset => $period) {
            if ($period->equals($interval)) {
                return $offset;
            }
        }

        return false;
    }

    /**
     * Returns the interval specified at a given offset.
     *
     * @throws InaccessibleInterval If the offset is illegal for the current sequence
     */
    public function get(int $offset): Period
    {
        return $this->periods[$this->getOffset($offset)];
    }

    /**
     * Sort the current instance according to the given comparison closure
     * and maintain index association.
     *
     * Returns true on success or false on failure
     */
    public function sort(Closure $compare): bool
    {
        return uasort($this->periods, $compare);
    }

    /**
     * Adds new intervals at the front of the sequence.
     *
     * The sequence is re-indexed after addition
     */
    public function unshift(Period ...$intervals): void
    {
        $this->periods = [...$intervals, ...$this->periods];
    }

    /**
     * Adds new intervals at the end of the sequence.
     */
    public function push(Period ...$intervals): void
    {
        $this->periods = [...$this->periods, ...$intervals];
    }

    /**
     * Inserts new intervals at the specified offset of the sequence.
     *
     * The sequence is re-indexed after addition
     *
     * @throws InaccessibleInterval If the offset is illegal for the current sequence.
     */
    public function insert(int $offset, Period $interval, Period ...$intervals): void
    {
        if (0 === $offset) {
            $this->unshift($interval, ...$intervals);

            return;
        }

        if (count($this->periods) === $offset) {
            $this->push($interval, ...$intervals);

            return;
        }

        array_unshift($intervals, $interval);
        array_splice($this->periods, $this->getOffset($offset), 0, $intervals);
    }

    /**
     * Updates the interval at the specify offset.
     *
     * @throws InaccessibleInterval If the offset is illegal for the current sequence.
     */
    public function set(int $offset, Period $interval): void
    {
        $this->periods[$this->getOffset($offset)] = $interval;
    }

    /**
     * Removes an interval from the sequence at the given offset and returns it.
     *
     * The sequence is re-indexed after removal
     *
     * @throws InaccessibleInterval If the offset is illegal for the current sequence.
     */
    public function remove(int $offset): Period
    {
        $index = $this->getOffset($offset);

        $interval = $this->periods[$index];
        unset($this->periods[$index]);
        $this->periods = array_values($this->periods);

        return $interval;
    }

    /**
     * Filters the sequence according to the given predicate.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the interval which validate the predicate.
     */
    public function filter(Closure $predicate): self
    {
        $intervals = array_filter($this->periods, $predicate, ARRAY_FILTER_USE_BOTH);

        return match ($this->periods) {
            $intervals => $this,
            default => new self(...$intervals),
        };
    }

    /**
     * Removes all intervals from the sequence.
     */
    public function clear(): void
    {
        $this->periods = [];
    }

    /**
     * Returns an instance sorted according to the given comparison closure
     * but does not maintain index association.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the sorted intervals. The key are re-indexed
     */
    public function sorted(Closure $compare): self
    {
        $intervals = $this->periods;
        usort($intervals, $compare);

        return match ($this->periods) {
            $intervals => $this,
            default => new self(...$intervals),
        };
    }

    /**
     * Returns an instance where the given function is applied to each element in
     * the collection. The Closure MUST return a Period object and takes a Period
     * and its associated key as argument.
     *
     * This method MUST retain the state of the current instance, and return
     * an instance that contains the returned intervals.
     *
     * @param Closure(Period, array-key): Period $closure
     */
    public function map(Closure $closure): self
    {
        $intervals = [];

        foreach ($this->periods as $offset => $period) {
            $intervals[$offset] = $closure($period, $offset);
        }

        return match ($this->periods) {
            $intervals => $this,
            default => new self(...$intervals),
        };
    }

    /**
     * Iteratively reduces the sequence to a single value using a callback.
     *
     * @template TReduceInitial
     * @template TReduceReturnType
     *
     * @param Closure(TReduceInitial|TReduceReturnType, Period, array-key=): TReduceReturnType $closure
     * @param TReduceInitial                                                                   $carry
     *
     * @return TReduceInitial|TReduceReturnType
     */
    public function reduce(Closure $closure, mixed $carry = null): mixed
    {
        foreach ($this->periods as $offset => $period) {
            $carry = $closure($carry, $period, $offset);
        }

        return $carry;
    }

    /**
     * Sorts two Interval instance using their start date endpoint.
     */
    private function sortByStartDate(Period $period1, Period $period2): int
    {
        return $period1->startDate <=> $period2->startDate;
    }

    /**
     * Iteratively calculate the union sequence.
     */
    private function calculateUnion(self $sequence, Period $period): self
    {
        if ($sequence->isEmpty()) {
            $sequence->push($period);

            return $sequence;
        }

        $index = $sequence->count() - 1;
        $currentInterval = $sequence[$index];

        if ($currentInterval->overlaps($period)) {
            $sequence[$index] = $currentInterval->merge($period);

            return $sequence;
        }

        $sequence->push($period);

        return $sequence;
    }

    /**
     * subtract an Interval from a Sequence.
     */
    private function subtractOne(self $sequence, Period $interval): self
    {
        $sub = fn (Period $source, Period $period): Sequence => $source->overlaps($period) ?
                $source->diff($period)->filter(fn (Period $item): bool => $source->overlaps($item)) :
                new Sequence($source);

        return $sequence->reduce(function (Sequence $sequence, Period $period) use ($interval, $sub): Sequence {
            $sequence->push(...$sub($period, $interval));

            return $sequence;
        }, new self());
    }

    /**
     * Filter and format the Sequence offset.
     *
     * This method allows the support of negative offset
     *
     * if no offset is found null is returned otherwise the return type is int
     */
    private function filterOffset(int $offset): ?int
    {
        $max = count($this->periods);

        return match (true) {
            [] === $this->periods, 0 > $max + $offset, 0 > $max - $offset - 1 => null,
            0 > $offset => $max + $offset,
            default => $offset,
        };
    }

    private function getOffset(int $offset): int
    {
        $index = $this->filterOffset($offset);

        return match (null) {
            $index => throw InaccessibleInterval::dueToInvalidIndex($offset),
            default => $index,
        };
    }
}
