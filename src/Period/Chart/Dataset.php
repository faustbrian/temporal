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

namespace Cline\Temporal\Period\Chart;

use Cline\Temporal\Period\Period;
use Cline\Temporal\Period\Sequence;
use Iterator;
use IteratorAggregate;
use MultipleIterator;
use TypeError;

use function array_column;
use function array_map;
use function count;
use function is_countable;
use function mb_strlen;
use function throw_unless;

/**
 * Labelled collection of chart-ready period sequences.
 *
 * `Dataset` is the canonical input shape for chart renderers. It keeps labels,
 * normalized {@see Sequence} values, the widest label width, and the overall
 * period span in sync as items are appended.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Dataset implements Data
{
    /** @var array<array{0:int|string, 1:Sequence}> */
    private array $pairs = [];

    private int $labelMaxLength = 0;

    private ?Period $length = null;

    /**
     * @param array<array{0:int|string, 1:Period|Sequence}>|Iterator<array{0:int|string, 1:Period|Sequence}>|IteratorAggregate<array{0:int|string, 1:Period|Sequence}> $pairs
     */
    public function __construct(array|Iterator|IteratorAggregate $pairs = [])
    {
        $this->appendAll($pairs);
    }

    /**
     * Create a dataset by pairing generated labels with a countable item collection.
     *
     * @param iterable<array-key, Period|Sequence> $items
     */
    public static function fromItems(iterable $items, LabelGenerator $labelGenerator = new LatinLetter('A')): self
    {
        throw_unless(is_countable($items), TypeError::class, 'The submitted items collection should be countable.');

        $pairs = new MultipleIterator(MultipleIterator::MIT_NEED_ALL | MultipleIterator::MIT_KEYS_ASSOC);
        $pairs->attachIterator($labelGenerator->generate(count($items)), 0);
        $pairs->attachIterator((function () use ($items): Iterator {
            foreach ($items as $key => $value) {
                yield $key => $value;
            }
        })(), 1);

        return new self($pairs);
    }

    /**
     * Creates a new collection from a generic iterable structure.
     *
     * @param iterable<int|string, Period|Sequence> $iterable
     */
    public static function fromIterable(iterable $iterable): self
    {
        $dataset = new self();

        foreach ($iterable as $label => $item) {
            $dataset->append($label, $item);
        }

        return $dataset;
    }

    /**
     * @param iterable<array{0:int|string, 1:Period|Sequence}> $pairs
     */
    public function appendAll(iterable $pairs): self
    {
        foreach ($pairs as [$label, $item]) {
            $this->append($label, $item);
        }

        return $this;
    }

    /**
     * Append a labelled period or sequence and update cached dataset metadata.
     */
    public function append(string|int $label, Period|Sequence $item): self
    {
        if ($item instanceof Period) {
            $item = new Sequence($item);
        }

        $this->setLabelMaxLength((string) $label);
        $this->setLength($item);

        $this->pairs[] = [$label, $item];

        return $this;
    }

    public function count(): int
    {
        return count($this->pairs);
    }

    public function getIterator(): Iterator
    {
        foreach ($this->pairs as $pair) {
            yield $pair;
        }
    }

    /**
     * @return array<array{label:int|string, item:Sequence}>
     */
    public function jsonSerialize(): array
    {
        return array_map(
            fn (array $pair): array => ['label' => $pair[0], 'item' => $pair[1]],
            $this->pairs,
        );
    }

    public function isEmpty(): bool
    {
        return [] === $this->pairs;
    }

    public function labels(): array
    {
        return array_column($this->pairs, 0);
    }

    public function items(): array
    {
        return array_column($this->pairs, 1);
    }

    public function length(): ?Period
    {
        return $this->length;
    }

    public function labelMaxLength(): int
    {
        return $this->labelMaxLength;
    }

    /**
     * Computes the label maximum length for the dataset.
     */
    private function setLabelMaxLength(string $label): void
    {
        $labelLength = mb_strlen($label);

        if ($this->labelMaxLength >= $labelLength) {
            return;
        }

        $this->labelMaxLength = $labelLength;
    }

    /**
     * Expand the cached dataset span so it covers the appended sequence.
     */
    private function setLength(Sequence $sequence): void
    {
        if (!$this->length instanceof Period) {
            $this->length = $sequence->length();

            return;
        }

        $this->length = $this->length->merge(...$sequence);
    }
}
