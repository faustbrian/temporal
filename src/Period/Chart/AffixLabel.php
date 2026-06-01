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

use function preg_match;

/**
 * A class to attach a prefix and/or a suffix string to the generated label.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see LabelGenerator
 * @psalm-immutable
 */
final readonly class AffixLabel implements LabelGenerator
{
    public function __construct(
        public LabelGenerator $labelGenerator,
        public string $labelPrefix = '',
        public string $labelSuffix = '',
    ) {
        $this->filterAffix($this->labelPrefix);
        $this->filterAffix($this->labelSuffix);
    }

    public function generate(int $nbLabels): Iterator
    {
        foreach ($this->labelGenerator->generate($nbLabels) as $key => $label) {
            yield $key => $this->decorate($label);
        }
    }

    public function format(string $label): string
    {
        return $this->decorate($this->labelGenerator->format($label));
    }

    private function filterAffix(string $str): void
    {
        if (1 === preg_match("/[\r\n]/", $str)) {
            throw UnableToDrawChart::dueToInvalidLabel($str, $this);
        }
    }

    private function decorate(string $string): string
    {
        return $this->labelPrefix.$string.$this->labelSuffix;
    }
}
