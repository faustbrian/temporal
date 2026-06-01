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

use Illuminate\Support\Facades\Date;
use Carbon\CarbonImmutable;
use Cline\Temporal\Period\Period;
use Cline\Temporal\Period\Sequence;
use PHPUnit\Framework\TestCase;

use const STDOUT;

use function fopen;
use function rewind;
use function stream_get_contents;

/**
 * @internal
 */
final class GanttChartTest extends TestCase
{
    private GanttChart $graph;

    /** @var resource */
    private $stream;

    protected function setUp(): void
    {
        $this->stream = $this->setStream();
        $this->graph = new GanttChart(
            new GanttChartConfig(output: new StreamOutput($this->stream, Terminal::Posix), colors: [Color::Red])
        );
    }

    public function test_display_empty_dataset(): void
    {
        $this->graph->stroke(
            new Dataset()
        );
        rewind($this->stream);
        $data = stream_get_contents($this->stream);

        $this->assertSame('', $data);
    }

    public function test_display_periods(): void
    {
        $this->graph->stroke(
            new Dataset([
            ['A', Period::fromDate(
                Date::parse('2018-01-01'), Date::parse('2018-01-15')
            )],
            ['B', Period::fromDate(
                Date::parse('2018-01-15'), Date::parse('2018-02-01')
            )],
        ])
        );

        rewind($this->stream);

        /** @var string $data */
        $data = stream_get_contents($this->stream);

        $this->assertStringContainsString('A [--------------------------)', $data);
        $this->assertStringContainsString('B                            [-------------------------------)', $data);
    }

    public function test_display_sequence(): void
    {
        $dataset = new Dataset([
            ['A', new Sequence(Period::fromDate(
                CarbonImmutable::parse('2018-01-01'), CarbonImmutable::parse('2018-01-15')
            ))],
            ['B', new Sequence(Period::fromDate(
                CarbonImmutable::parse('2018-01-15'), CarbonImmutable::parse('2018-02-01')
            ))],
        ]);

        $this->graph->stroke($dataset);

        rewind($this->stream);

        /** @var string $data */
        $data = stream_get_contents($this->stream);

        $this->assertStringContainsString('A [--------------------------)', $data);
        $this->assertStringContainsString('B                            [-------------------------------)', $data);
    }

    public function test_display_empty_sequence(): void
    {
        $dataset = new Dataset();
        $dataset->append('sequenceA', new Sequence());
        $dataset->append('sequenceB', new Sequence());

        $this->graph->stroke($dataset);

        rewind($this->stream);

        /** @var string $data */
        $data = stream_get_contents($this->stream);

        $this->assertStringContainsString('sequenceA                                  ', $data);
        $this->assertStringContainsString('sequenceB                                  ', $data);
    }

    public function test_constructor(): void
    {
        $graph = new GanttChart(
            new GanttChartConfig(
                new StreamOutput(STDOUT, Terminal::Posix)
            )
        );

        $this->assertSame([Color::Reset], $graph->config->colors);
    }

    /**
     * @return resource
     */
    private function setStream()
    {
        return fopen('php://memory', 'r+b');
    }
}
