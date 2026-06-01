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

use Iterator;

/**
 * A class to attach a decimal number to the generated label.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see LabelGenerator
 * @psalm-immutable
 */
final readonly class DecimalNumber implements LabelGenerator
{
    public function __construct(
        public int $startLabel,
    ) {}

    public function format(string $label): string
    {
        return $label;
    }

    public function generate(int $nbLabels): Iterator
    {
        if (0 >= $nbLabels) {
            return;
        }

        $count = 0;
        $end = $this->startLabel + $nbLabels;
        $label = $this->startLabel;

        while ($label < $end) {
            yield $count => $this->format((string) $label);

            ++$count;
            ++$label;
        }
    }
}
