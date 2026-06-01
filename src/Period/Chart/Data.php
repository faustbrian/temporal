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

namespace Cline\Temporal\Period\Chart;

use Cline\Temporal\Period\Period;
use Cline\Temporal\Period\Sequence;
use Countable;
use Iterator;
use IteratorAggregate;
use JsonSerializable;

/**
 * @extends IteratorAggregate<array-key, array{0:array-key, 1:Sequence}>
 */
interface Data extends Countable, IteratorAggregate, JsonSerializable
{
    /**
     * Returns the number of pairs.
     */
    public function count(): int;

    /**
     * Returns the pairs.
     *
     * @return Iterator<int, array{0: int|string, 1: Sequence}>
     */
    public function getIterator(): Iterator;

    /**
     * @return array<array{label:int|string, item:Sequence}>
     */
    public function jsonSerialize(): array;

    /**
     * Tells whether the instance is empty.
     */
    public function isEmpty(): bool;

    /**
     * Returns the labels associated to all items.
     *
     * @return array<int|string>
     */
    public function labels(): iterable;

    /**
     * Returns all items as a collection of Sequences.
     *
     * @return array<Sequence>
     */
    public function items(): iterable;

    /**
     * Returns the dataset length.
     */
    public function length(): ?Period;

    /**
     * Returns the label maximum length.
     */
    public function labelMaxLength(): int;

    /**
     * Add a new pair to the collection.
     */
    public function append(string|int $label, Period|Sequence $item): self;

    /**
     * Add a collection of pairs to the collection.
     *
     * @param iterable<array{0:int|string, 1:Period|Sequence}> $pairs
     */
    public function appendAll(iterable $pairs): self;
}
