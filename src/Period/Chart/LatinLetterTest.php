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
final class LatinLetterTest extends TestCase
{
    public function test_fails_to_create_new_instance_with_empty_string(): void
    {
        $this->expectException(UnableToDrawChart::class);

        new LatinLetter('');
    }

    public function test_fails_to_create_new_instance_with_invalid_string(): void
    {
        $this->expectException(UnableToDrawChart::class);

        new LatinLetter('F0obar');
    }

    /**
     * @param array<string> $expected
     */
    #[DataProvider('provideGet_labelsCases')]
    public function test_get_labels(int $nbLabels, string $letter, array $expected): void
    {
        $generator = new LatinLetter($letter);
        $this->assertSame($expected, iterator_to_array($generator->generate($nbLabels), false));
    }

    public static function provideGet_labelsCases(): iterable
    {
        yield 'empty labels' => [
            'nbLabels' => 0,
            'letter' => 'i',
            'expected' => [],
        ];

        yield 'labels starts at i' => [
            'nbLabels' => 1,
            'letter' => 'i',
            'expected' => ['i'],
        ];

        yield 'labels starts ends at ab' => [
            'nbLabels' => 2,
            'letter' => 'aa',
            'expected' => ['aa', 'ab'],
        ];

        yield 'labels starts ends at z' => [
            'nbLabels' => 3,
            'letter' => 'z',
            'expected' => ['z', 'aa', 'ab'],
        ];

        yield 'labels starts at 0 (1)' => [
            'nbLabels' => 1,
            'letter' => '   A     ',
            'expected' => ['A'],
        ];

        yield 'labels starts at 0 (2)' => [
            'nbLabels' => 1,
            'letter' => 'A',
            'expected' => ['A'],
        ];
    }

    public function test_format(): void
    {
        $generator = new LatinLetter('i');
        $this->assertSame('foobar', $generator->format('foobar'));
    }
}
