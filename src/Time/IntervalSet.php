<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use Closure;
use Countable;
use DateTimeInterface;
use IteratorAggregate;
use JsonSerializable;
use SortDirection;
use Traversable;
use TypeError;
use UnitEnum;
use ValueError;

use function array_all;
use function array_any;
use function array_column;
use function array_key_last;
use function array_last;
use function array_map;
use function array_pop;
use function array_shift;
use function count;
use function enum_exists;
use function get_debug_type;
use function in_array;
use function is_string;
use function max;
use function mb_strtolower;
use function min;
use function sprintf;
use function throw_unless;
use function usort;

/**
 * @phpstan-import-type NativeInterval from Interval
 *
 * @psalm-immutable
 * @author Brian Faust <brian@cline.sh>
 * @implements IteratorAggregate<Interval>
 */
final readonly class IntervalSet implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<Interval> */
    private array $intervals;

    private Duration $duration;

    /**
     * @throws InvalidDuration
     */
    public function __construct(Interval|self ...$intervals)
    {
        $this->intervals = $this->flatten(...$intervals);
        $this->duration = Duration::zero()->sum(...array_column($this->intervals, 'duration'));
    }

    /**
     * @return array{0: array{intervals: list<Interval>}, 1: array{}}
     */
    public function __serialize(): array
    {
        return [['intervals' => $this->intervals], []];
    }

    /**
     * @param array{0: array{intervals: list<Interval>}, 1: array{}} $data
     *
     * @throws InvalidDuration
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->intervals = $properties['intervals'];
        $this->duration = Duration::zero()->sum(...array_column($this->intervals, 'duration'));
    }

    public function count(): int
    {
        return count($this->intervals);
    }

    public function getIterator(): Traversable
    {
        yield from $this->intervals;
    }

    /**
     * @return list<Interval>
     */
    public function jsonSerialize(): array
    {
        return $this->intervals;
    }

    /**
     * @return list<Interval>
     */
    public function all(): array
    {
        return $this->intervals;
    }

    public function duration(): Duration
    {
        return $this->duration;
    }

    /**
     * @throws InvalidTime
     *
     * @return list<non-empty-string>
     */
    public function allFormatted(
        IntervalNotation $notation = IntervalNotation::Iso8601StartDuration,
        ?Unit $unitOfDay = null,
    ): array {
        return array_map(
            static fn (Interval $interval): string => $interval->toNotation($notation, $unitOfDay),
            $this->intervals,
        );
    }

    /**
     * @return list<NativeInterval>
     */
    public function allNative(DateTimeInterface $reference): array
    {
        return array_map(static fn (Interval $interval): array => $interval->toNative($reference), $this->intervals);
    }

    public function isEmpty(): bool
    {
        return [] === $this->intervals;
    }

    /**
     * @throws TimeException
     */
    public function get(int $offset): Interval
    {
        return $this->nth($offset) ?? throw InvalidIntervalSetOffset::forOffset($offset);
    }

    public function nth(int $offset): ?Interval
    {
        if ($offset < 0) {
            $offset += count($this->intervals);
        }

        return $this->intervals[$offset] ?? null;
    }

    /**
     * Tells whether the given interval is present in the set.
     */
    public function has(Interval ...$intervals): bool
    {
        foreach ($intervals as $interval) {
            if (null === $this->indexOf($interval)) {
                return false;
            }
        }

        return [] !== $intervals;
    }

    public function indexOf(Interval $interval): ?int
    {
        foreach ($this->intervals as $offset => $item) {
            if ($item->equals($interval)) {
                return $offset;
            }
        }

        return null;
    }

    public function lastIndexOf(Interval $interval): ?int
    {
        for ($offset = count($this->intervals) - 1; $offset >= 0; --$offset) {
            if ($interval->equals($this->intervals[$offset])) {
                return $offset;
            }
        }

        return null;
    }

    public function first(): ?Interval
    {
        return $this->nth(0);
    }

    public function last(): ?Interval
    {
        return $this->nth(-1);
    }

    /**
     * @param callable(Interval, int=): bool $predicate
     */
    public function firstMatching(callable $predicate): ?Interval
    {
        foreach ($this->intervals as $offset => $interval) {
            if (true === $predicate($interval, $offset)) {
                return $interval;
            }
        }

        return null;
    }

    /**
     * @param callable(Interval, int=): bool $predicate
     */
    public function lastMatching(callable $predicate): ?Interval
    {
        for ($offset = count($this->intervals) - 1; $offset >= 0; --$offset) {
            $interval = $this->intervals[$offset];

            if (true === $predicate($interval, $offset)) {
                return $interval;
            }
        }

        return null;
    }

    /**
     * @throws InvalidDuration
     */
    public function push(Interval|self ...$interval): self
    {
        $res = $this->flatten(...$interval);

        return [] === $res ? $this : new self(...$this->intervals, ...$res);
    }

    /**
     * @throws InvalidDuration
     */
    public function unshift(Interval|self ...$interval): self
    {
        $set = new self(...$interval);

        return $set->isEmpty() ? $this : $set->push($this);
    }

    /**
     * @throws InvalidDuration
     * @throws TimeException
     */
    public function replace(int $offset, Interval $interval): self
    {
        if ($offset < 0) {
            $offset += count($this->intervals);
        }

        throw_unless(isset($this->intervals[$offset]), InvalidIntervalSetOffset::forOffset($offset));

        $intervals = $this->intervals;
        $intervals[$offset] = $interval;

        return new self(...$intervals);
    }

    /**
     * @throws InvalidDuration
     */
    public function remove(int ...$offsets): self
    {
        if ([] === $offsets) {
            return $this;
        }

        $nbIntervals = count($this->intervals);
        $normalized = [];

        foreach ($offsets as $offset) {
            if ($offset < 0) {
                $offset += $nbIntervals;
            }

            if (0 > $offset) {
                continue;
            }

            if ($nbIntervals <= $offset) {
                continue;
            }

            $normalized[] = $offset;
        }

        if ([] === $normalized) {
            return $this;
        }

        return $this->filter(static fn (Interval $interval, int $index): bool => !in_array($index, $normalized, true)); /* @phpstan-ignore-line */
    }

    /**
     * @param callable(Interval, int=): bool $callback
     */
    public function any(callable $callback): bool
    {
        return array_any($this->intervals, fn ($interval, $key): bool => true === $callback($interval, $key));
    }

    /**
     * @param callable(Interval, int=): bool $callback
     */
    public function every(callable $callback): bool
    {
        foreach ($this->intervals as $key => $interval) {
            if (true !== $callback($interval, $key)) {
                return false;
            }
        }

        return [] !== $this->intervals;
    }

    /**
     * @template TValue
     *
     * @param callable(Interval, int=): TValue $callback
     *
     * @return iterable<TValue>
     */
    public function map(callable $callback): iterable
    {
        foreach ($this->intervals as $offset => $interval) {
            yield $callback($interval, $offset);
        }
    }

    /**
     * @param callable(Interval, int=): bool $callback
     *
     * @throws InvalidDuration
     */
    public function filter(callable $callback): self
    {
        $data = [];

        foreach ($this->intervals as $key => $interval) {
            if (true !== $callback($interval, $key)) {
                continue;
            }

            $data[] = $interval;
        }

        return $data === $this->intervals ? $this : new self(...$data);
    }

    /**
     * @template TReduceInitial
     * @template TReduceReturnType
     *
     * @param callable(TReduceInitial|TReduceReturnType, Interval, int=): TReduceReturnType $callback
     * @param TReduceInitial                                                                $initial
     *
     * @return TReduceInitial|TReduceReturnType
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $result = $initial;

        foreach ($this->intervals as $key => $interval) {
            $result = $callback($result, $interval, $key);
        }

        return $result;
    }

    /**
     * @param callable(Interval, int=): mixed $callback
     */
    public function each(callable $callback): bool
    {
        return array_all($this->intervals, fn ($interval, $key): bool => false !== $callback($interval, $key));
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function gaps(): self
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $result = [];
        $previous = null;

        foreach ($this->union()->sorted() as $interval) {
            if ($previous instanceof Interval && $interval->start->isAfter($previous->end)) {
                $result[] = Interval::between($previous->end, $interval->start);
            }

            $previous = $interval;
        }

        return new self(...$result);
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function difference(self|Interval ...$others): self
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $other = new self(...$others);

        if ($other->isEmpty()) {
            return $this;
        }

        $differences = [];
        $otherIntervals = $other->union()->intervals;

        foreach ($this->union()->intervals as $interval) {
            if (IntervalType::Collapsed === $interval->type) {
                continue;
            }

            $current = IntervalType::Circular !== $interval->type
                ? [[$interval->linearStart, $interval->linearEnd]]
                : [[0, Unit::Day->toMicroseconds(1)]];

            foreach ($otherIntervals as $otherInterval) {
                if (IntervalType::Collapsed === $otherInterval->type) {
                    continue;
                }

                $bStart = $otherInterval->linearStart;
                $bEnd = $otherInterval->linearEnd;
                $next = [];

                foreach ($current as [$start, $end]) {
                    if ($bEnd <= $start || $bStart >= $end) {
                        $next[] = [$start, $end];

                        continue;
                    }

                    if ($bStart > $start) {
                        $next[] = [$start, $bStart];
                    }

                    if ($bEnd >= $end) {
                        continue;
                    }

                    $next[] = [$bEnd, $end];
                }

                $current = $next;

                if ([] === $current) {
                    break;
                }
            }

            $differences = [...$differences, ...$current];
        }

        return new self(
            ...array_map(
                static fn (array $span): Interval => Interval::fromLinearSpan($span[0], $span[1]),
                $differences,
            ),
        );
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function intersect(self|Interval ...$others): self
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $other = new self(...$others);

        if ($other->isEmpty()) {
            return $this;
        }

        $intersections = [];
        $bSpans = $other->union()->intervals;

        foreach ($this->union()->intervals as $aInterval) {
            foreach ($bSpans as $bInterval) {
                $start = max($aInterval->linearStart, $bInterval->linearStart);
                $end = min($aInterval->linearEnd, $bInterval->linearEnd);

                if ($start >= $end) {
                    continue;
                }

                $intersections[] = Interval::fromLinearSpan($start, $end);
            }
        }

        return new self(...$intersections)->union();
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function complement(): self
    {
        return new self(Interval::fullDay())->difference($this)->union();
    }

    /**
     * @throws InvalidDuration|InvalidInterval
     */
    public function union(Interval|self ...$others): self
    {
        $set = $this->push(...$others)->sorted();

        if (1 >= count($set)) {
            return $set;
        }

        $merged = [];

        foreach ($set->intervals as $span) {
            if ([] !== $merged) {
                $lastIndex = array_key_last($merged);
                $prevSpan = $merged[$lastIndex];

                if ($span->linearStart <= $prevSpan->linearEnd) {
                    $merged[$lastIndex] = Interval::fromLinearSpan($prevSpan->linearStart, max($prevSpan->linearEnd, $span->linearEnd));

                    continue;
                }
            }

            $merged[] = $span;
        }

        if (count($merged) >= 2) {
            $first = $merged[0];
            $last = array_last($merged);

            if ($first->overlaps($last)) {
                array_shift($merged);
                array_pop($merged);

                $merged[] = Interval::fromLinearSpan($last->linearStart, $first->linearEnd + Unit::Day->microseconds());
            }
        }

        return new self(...$merged);
    }

    /**
     * @param callable(Interval, Interval): int $callback
     *
     * @throws InvalidDuration
     */
    public function sortedUsing(callable $callback): self
    {
        if (1 >= count($this->intervals)) {
            return $this;
        }

        $intervals = $this->intervals;
        usort($intervals, $callback);

        return $intervals === $this->intervals ? $this : new self(...$intervals);
    }

    /**
     * Sorts the set on each Interval starting time.
     *
     * @see https://wiki.php.net/rfc/sort_direction_enum
     *
     * The only Enum supported is the PHP8.6+ SortDirection Enum and its polyfill,
     * otherwise, the strings "asc", "ascending", "desc" and "descending" are
     * supported in a case-insensitive way.
     *
     * @throws InvalidDuration
     */
    public function sorted(Bound $sortBound = Bound::Start, UnitEnum|string $sortDirection = 'asc'): self
    {
        return $this->sortedUsing($this->filterCompare($sortBound, $this->filterSortDirection($sortDirection)));
    }

    /**
     * @return list<Interval>
     */
    private function flatten(Interval|self ...$intervals): array
    {
        $res = [];

        foreach ($intervals as $item) {
            $found = $item instanceof Interval ? [$item] : $item->intervals;

            foreach ($found as $interval) {
                $res[] = $interval;
            }
        }

        return $res;
    }

    /**
     * @param 'asc'|'desc' $sortDirection
     *
     * @return Closure(Interval, Interval): int
     */
    private function filterCompare(Bound $bound, string $sortDirection): Closure
    {
        $primary = match ($bound) {
            Bound::Start => static fn (Interval $x, Interval $y): int => $x->linearStart <=> $y->linearStart,
            Bound::End => static fn (Interval $x, Interval $y): int => $x->linearEnd <=> $y->linearEnd,
        };

        $primary = 'asc' === $sortDirection ? $primary : static fn (Interval $x, Interval $y): int => $primary($y, $x);

        $secondary = static fn (Interval $x, Interval $y): int => $x->duration->compareTo($y->duration);

        return static function (Interval $x, Interval $y) use ($primary, $secondary): int {
            $result = $primary($x, $y);

            return 0 !== $result ? $result : $secondary($x, $y);
        };
    }

    /**
     * @return 'asc'|'desc'
     */
    private function filterSortDirection(UnitEnum|string $sortDirection): string
    {
        if (enum_exists('\SortDirection') && $sortDirection instanceof SortDirection) {
            return match ($sortDirection) {
                SortDirection::Ascending => 'asc',
                SortDirection::Descending => 'desc',
                default => throw new ValueError(sprintf("Unknown sort direction '%s'", $sortDirection->name)),
            };
        }

        if (!is_string($sortDirection)) {
            throw new TypeError('Argument ($sortDirection) must be of type SortDirection, '.get_debug_type($sortDirection).' given,');
        }

        $sortDirection = mb_strtolower($sortDirection);

        return match ($sortDirection) {
            'asc', 'ascending' => 'asc',
            'desc', 'descending' => 'desc',
            default => throw new ValueError(sprintf("Unknown sort direction '%s'", $sortDirection)),
        };
    }
}
