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

use function array_pop;
use function chr;
use function implode;
use function mb_str_split;
use function mb_trim;
use function ord;
use function preg_match;

/**
 * A class to attach a latin letter to the generated label.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see LabelGenerator
 * @psalm-immutable
 */
final readonly class LatinLetter implements LabelGenerator
{
    public string $startLabel;

    public function __construct(string $startLabel)
    {
        $this->startLabel = $this->filterLabel($startLabel);
    }

    public function format(string $label): string
    {
        return $label;
    }

    public function generate(int $nbLabels): Iterator
    {
        if (0 >= $nbLabels) {
            $nbLabels = 0;
        }

        $count = 0;
        $label = $this->startLabel;

        while ($count < $nbLabels) {
            yield $count => $label;

            $label = $this->increment($label);

            ++$count;
        }
    }

    /**
     * Increments ASCII Letters like numbers in PHP.
     *
     * @see https://stackoverflow.com/questions/3567180/how-to-increment-letters-like-numbers-in-php/3567218
     */
    private function increment(string $current): string
    {
        static $asciiUpperCaseBounds = ['start' => 65, 'end' => 91];
        static $asciiLowerCaseBounds = ['start' => 97, 'end' => 123];

        $increase = true;
        $letters = mb_str_split($current);
        $nextLetters = [];

        while ([] !== $letters) {
            $nextLetter = array_pop($letters);

            if ($increase) {
                $letterAscii = ord($nextLetter) + 1;

                [$nextLetterAscii, $increase] = match ($letterAscii) {
                    $asciiUpperCaseBounds['end'] => [$asciiUpperCaseBounds['start'], true],
                    $asciiLowerCaseBounds['end'] => [$asciiLowerCaseBounds['start'], true],
                    default => [$letterAscii, false],
                };

                $nextLetter = chr($nextLetterAscii);

                if ($increase && [] === $letters) {
                    $nextLetter .= $nextLetter;
                }
            }

            $nextLetters = [$nextLetter, ...$nextLetters];
        }

        return implode('', $nextLetters);
    }

    private function filterLabel(string $str): string
    {
        $label = mb_trim($str);

        return match (1) {
            preg_match('/^[A-Za-z]+$/', $label) => $label,
            default => throw UnableToDrawChart::dueToInvalidLabel($str, $this),
        };
    }
}
