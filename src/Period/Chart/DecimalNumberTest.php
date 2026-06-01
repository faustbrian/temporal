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
final class DecimalNumberTest extends TestCase
{
    /**
     * @param array<string> $expected
     */
    #[DataProvider('provideGet_labelsCases')]
    public function test_get_labels(int $nbLabels, int $label, array $expected): void
    {
        $generator = new DecimalNumber($label);
        $this->assertSame($expected, iterator_to_array($generator->generate($nbLabels), false));
    }

    public static function provideGet_labelsCases(): iterable
    {
        yield 'empty labels' => [
            'nbLabels' => 0,
            'label' => 1,
            'expected' => [],
        ];

        yield 'labels starts at 3' => [
            'nbLabels' => 1,
            'label' => 3,
            'expected' => ['3'],
        ];

        yield 'labels starts ends at 4' => [
            'nbLabels' => 2,
            'label' => 4,
            'expected' => ['4', '5'],
        ];

        yield 'labels starts at 0 (1)' => [
            'nbLabels' => 1,
            'label' => -1,
            'expected' => ['-1'],
        ];

        yield 'labels starts at 0 (2)' => [
            'nbLabels' => 1,
            'label' => 0,
            'expected' => ['0'],
        ];
    }

    public function test_format(): void
    {
        $generator = new DecimalNumber(42);
        $this->assertSame('', $generator->format(''));
    }
}
