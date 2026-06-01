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
final class ReverseLabelTest extends TestCase
{
    /**
     * @param array<string> $expected
     */
    #[DataProvider('provideGetLabelsCases')]
    public function test_get_labels(int $nbLabels, string $letter, array $expected): void
    {
        $generator = new ReverseLabel(
            new LatinLetter($letter)
        );
        $this->assertSame($expected, iterator_to_array($generator->generate($nbLabels), false));
    }

    public static function provideGetLabelsCases(): \Iterator
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
            new LatinLetter('AA')
        );
        $this->assertSame('', $generator->format(''));
    }
}
