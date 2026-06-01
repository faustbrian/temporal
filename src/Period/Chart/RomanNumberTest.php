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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

/**
 * @internal
 */
final class RomanNumberTest extends TestCase
{
    /**
     * @param array<string> $expected
     */
    #[DataProvider('provideGetLabelsCases')]
    public function test_get_labels(int $nbLabels, int $label, LetterCase $lettercase, array $expected, bool $isUpper): void
    {
        $generator = new RomanNumber(
            new DecimalNumber($label), $lettercase
        );
        $this->assertSame($expected, iterator_to_array($generator->generate($nbLabels), false));
        $this->assertSame($isUpper, $lettercase->isUpper());
    }

    public static function provideGetLabelsCases(): \Iterator
    {
        yield 'empty labels' => [
            'nbLabels' => 0,
            'label' => 1,
            'lettercase' => LetterCase::Upper,
            'expected' => [],
            'isUpper' => true,
        ];
        yield 'labels starts at 3' => [
            'nbLabels' => 1,
            'label' => 3,
            'lettercase' => LetterCase::Upper,
            'expected' => ['III'],
            'isUpper' => true,
        ];
        yield 'labels starts ends at 4' => [
            'nbLabels' => 2,
            'label' => 4,
            'lettercase' => LetterCase::Lower,
            'expected' => ['iv', 'v'],
            'isUpper' => false,
        ];
    }

    public function test_fails_to_create_roman_label_generator(): void
    {
        $this->expectException(UnableToDrawChart::class);

        new RomanNumber(
            new DecimalNumber(0), LetterCase::Lower
        );
    }

    public function test_format(): void
    {
        $upperRoman = new RomanNumber(
            new DecimalNumber(10), LetterCase::Upper
        );
        $lowerRoman = new RomanNumber(
            new DecimalNumber(10), LetterCase::Lower
        );

        $this->assertSame('FOOBAR', $upperRoman->format('fOoBaR'));
        $this->assertSame('foobar', $lowerRoman->format('fOoBaR'));
    }
}
