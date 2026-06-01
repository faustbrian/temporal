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

use function array_reverse;
use function iterator_to_array;

/**
 * A class to revert the order of the generated labels.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see LabelGenerator
 * @psalm-immutable
 */
final readonly class ReverseLabel implements LabelGenerator
{
    public function __construct(
        public LabelGenerator $labelGenerator,
    ) {}

    public function generate(int $nbLabels): Iterator
    {
        $data = iterator_to_array($this->labelGenerator->generate($nbLabels), false);

        foreach (array_reverse($data, false) as $offset => $value) {
            yield $offset => $value;
        }
    }

    public function format(string $label): string
    {
        return $this->labelGenerator->format($label);
    }
}
