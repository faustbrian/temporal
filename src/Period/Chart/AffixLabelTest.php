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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use const PHP_EOL;

use function iterator_to_array;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class AffixLabelTest extends TestCase
{
    /**
     * @param array<string> $expected
     */
    #[DataProvider('provideGet_labelsCases')]
    public function test_get_labels(
        int $nbLabels,
        string $letter,
        string $prefix,
        string $suffix,
        array $expected,
    ): void {
        $generator = new AffixLabel(
            new LatinLetter($letter),
            $prefix,
            $suffix,
        );
        $this->assertSame($expected, iterator_to_array($generator->generate($nbLabels), false));
    }

    /**
     * @return Iterator<string, array{nbLabels: int, letter: string, prefix: string, suffix: string, expected: array<int, string>}>
     */
    public static function provideGet_labelsCases(): iterable
    {
        yield 'empty labels' => [
            'nbLabels' => 0,
            'letter' => 'i',
            'prefix' => '',
            'suffix' => '',
            'expected' => [],
        ];

        yield 'labels starts at i' => [
            'nbLabels' => 1,
            'letter' => 'i',
            'prefix' => '',
            'suffix' => '.',
            'expected' => ['i.'],
        ];

        yield 'labels starts ends at ab' => [
            'nbLabels' => 2,
            'letter' => 'aa',
            'prefix' => '-',
            'suffix' => '',
            'expected' => ['-aa', '-ab'],
        ];

        yield 'labels starts at 0 (1)' => [
            'nbLabels' => 1,
            'letter' => '   A     ',
            'prefix' => '.',
            'suffix' => '.',
            'expected' => ['.A.'],
        ];
    }

    public function test_fails_to_instantiate_new_instance_with_carriage_return_character(): void
    {
        $this->expectException(UnableToDrawChart::class);

        new AffixLabel(labelGenerator: new LatinLetter('foobar'), labelPrefix: 'toto', labelSuffix: 'toto'.PHP_EOL);
    }

    public function test_getter(): void
    {
        $generator = new AffixLabel(
            new RomanNumber(
                new DecimalNumber(10),
                LetterCase::Upper,
            ),
        );
        $this->assertSame('', $generator->labelSuffix);
        $this->assertSame('', $generator->labelPrefix);
    }

    public function test_format(): void
    {
        $generator = new AffixLabel(
            new RomanNumber(
                new DecimalNumber(10),
                LetterCase::Upper,
            ),
            ':',
            '.',
        );
        $this->assertSame(':FOOBAR.', $generator->format('foobar'));
    }
}
