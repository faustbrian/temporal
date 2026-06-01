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

use ArrayIterator;
use ArrayObject;
use Carbon\CarbonImmutable;
use Cline\Temporal\Period\Period;
use Cline\Temporal\Period\Sequence;
use Illuminate\Support\Facades\Date;
use Iterator;
use IteratorAggregate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TypeError;

use function iterator_to_array;
use function json_encode;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class DatasetTest extends TestCase
{
    public function test_from_sequence_constructor(): void
    {
        $periodA = Period::fromDay(2_018, 3, 15);
        $periodB = Period::fromDay(2_019, 3, 15);
        $labelGenerator = new LatinLetter('A');
        $sequence = new Sequence($periodA, $periodB);
        $dataset = Dataset::fromItems($sequence, $labelGenerator);
        $arr = iterator_to_array($dataset);

        $this->assertCount(2, $dataset);
        $this->assertSame('B', $arr[1][0]);
        $this->assertTrue($periodB->equals($arr[1][1][0]));

        $emptyDataset = Dataset::fromItems(
            new Sequence(),
            $labelGenerator,
        );
        $this->assertCount(0, $emptyDataset);
        $this->assertTrue($emptyDataset->isEmpty());
    }

    /**
     * @param iterable<int, Period|Sequence> $input
     */
    #[DataProvider('provideFrom_iterable_constructorCases')]
    public function test_from_iterable_constructor(iterable $input, int $expectedCount, bool $isEmpty, bool $boundaryIsNull): void
    {
        $dataset = Dataset::fromIterable($input);
        $this->assertCount($expectedCount, $dataset);
        $this->assertSame($isEmpty, $dataset->isEmpty());
        $this->assertSame($boundaryIsNull, !$dataset->length() instanceof Period);
    }

    /**
     * @return Iterator<string, array{input: iterable<int, (Period|Sequence)>, expectedCount: int, isEmpty: bool, boundaryIsNull: bool}>
     */
    public static function provideFrom_iterable_constructorCases(): iterable
    {
        yield 'empty structure' => [
            'input' => [],
            'expectedCount' => 0,
            'isEmpty' => true,
            'boundaryIsNull' => true,
        ];

        yield 'single array' => [
            'input' => [Period::fromDay(2_019, 3, 15)],
            'expectedCount' => 1,
            'isEmpty' => false,
            'boundaryIsNull' => false,
        ];

        yield 'using an iterator' => [
            'input' => new ArrayObject([Period::fromDay(2_019, 3, 15)]),
            'expectedCount' => 1,
            'isEmpty' => false,
            'boundaryIsNull' => false,
        ];

        yield 'using a direct sequence' => [
            'input' => new Sequence(
                Period::fromDay(2_018, 9, 10),
                Period::fromDay(2_019, 10, 11),
            ),
            'expectedCount' => 2,
            'isEmpty' => false,
            'boundaryIsNull' => false,
        ];

        yield 'using a wrapped sequence' => [
            'input' => [new Sequence(
                Period::fromDay(2_018, 9, 10),
                Period::fromDay(2_019, 10, 11),
            )],
            'expectedCount' => 1,
            'isEmpty' => false,
            'boundaryIsNull' => false,
        ];
    }

    public function test_append_dataset(): void
    {
        $dataset = new Dataset([
            ['A', new Sequence(Period::fromDate(
                Date::parse('2018-01-01'),
                Date::parse('2018-01-15'),
            ))],
            ['B', Period::fromDate(
                Date::parse('2018-01-15'),
                Date::parse('2018-02-01'),
            )],
        ]);

        $this->assertCount(2, $dataset);
    }

    public function test_labelize_dataset(): void
    {
        $dataset = new Dataset([
            ['A', new Sequence(Period::fromDate(
                CarbonImmutable::parse('2018-01-01'),
                CarbonImmutable::parse('2018-01-15'),
            ))],
            ['B', new Sequence(Period::fromDate(
                CarbonImmutable::parse('2018-01-15'),
                CarbonImmutable::parse('2018-02-01'),
            ))],
        ]);
        $this->assertSame(['A', 'B'], $dataset->labels());
        $this->assertSame(1, $dataset->labelMaxLength());

        $newDataset = Dataset::fromItems($dataset->items(), new DecimalNumber(42));
        $this->assertSame(['42', '43'], $newDataset->labels());
        $this->assertSame($dataset->items(), $newDataset->items());
        $this->assertSame(2, $newDataset->labelMaxLength());
    }

    public function test_labelize_dataset_returns_same_instance(): void
    {
        $dataset = new Dataset([
            ['A', new Sequence(Period::fromDate(
                CarbonImmutable::parse('2018-01-01'),
                CarbonImmutable::parse('2018-01-15'),
            ))],
            ['B', new Sequence(Period::fromDate(
                CarbonImmutable::parse('2018-01-15'),
                CarbonImmutable::parse('2018-02-01'),
            ))],
        ]);

        $this->assertEquals($dataset, Dataset::fromItems($dataset->items(), new LatinLetter('A')));
        $this->assertEquals(
            new Dataset(),
            Dataset::fromItems(
                new Dataset()->items(),
                new DecimalNumber(42),
            ),
        );
    }

    public function test_empty_instance(): void
    {
        $dataset = new Dataset();
        $this->assertSame(0, $dataset->labelMaxLength());
        $this->assertSame([], $dataset->items());
        $this->assertSame([], $dataset->labels());
    }

    public function test_json_encoding(): void
    {
        $this->assertSame('[]', json_encode(
            new Dataset(),
        ));
        $dataset = new Dataset([
            ['A', new Sequence(Period::fromDate(
                Date::parse('2018-01-01'),
                Date::parse('2018-01-15'),
            ))],
            ['B', new Sequence(Period::fromDate(
                Date::parse('2018-01-15'),
                Date::parse('2018-02-01'),
            ))],
        ]);

        $this->assertStringContainsString('label', (string) json_encode($dataset));
    }

    public function test_from_items_fails_with_non_countable_iterator(): void
    {
        $items = new class() implements IteratorAggregate
        {
            /**
             * @return ArrayIterator<array-key, Period>
             */
            public function getIterator(): Iterator
            {
                return new ArrayIterator([Period::fromIso80000('!Y-m-d', '[2021-01-23, 2022-02-03]')]);
            }
        };

        $this->expectException(TypeError::class);

        Dataset::fromItems(
            new $items(),
            new LatinLetter('A'),
        );
    }
}
