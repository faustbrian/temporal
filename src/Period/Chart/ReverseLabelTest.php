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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class ReverseLabelTest extends TestCase
{
    /**
     * @param array<string> $expected
     */
    #[DataProvider('provideGet_labelsCases')]
    public function test_get_labels(int $nbLabels, string $letter, array $expected): void
    {
        $generator = new ReverseLabel(
            new LatinLetter($letter),
        );
        $this->assertSame($expected, iterator_to_array($generator->generate($nbLabels), false));
    }

    /**
     * @return iterable<string, array{nbLabels: int, letter: string, expected: array<string>}>
     */
    public static function provideGet_labelsCases(): iterable
    {
        yield 'empty labels' => [
            'nbLabels' => 0,
            'letter' => 'i',
            'expected' => [],
        ];

        yield 'labels starts at i' => [
            'nbLabels' => 2,
            'letter' => 'i',
            'expected' => ['j', 'i'],
        ];

        yield 'labels starts ends at ab' => [
            'nbLabels' => 2,
            'letter' => 'aa',
            'expected' => ['ab', 'aa'],
        ];
    }

    public function test_format(): void
    {
        $generator = new ReverseLabel(
            new LatinLetter('AA'),
        );
        $this->assertSame('', $generator->format(''));
    }
}
