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

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use const STDERR;
use const STDOUT;

/**
 * @internal
 */
final class GanttChartConfigTest extends TestCase
{
    private GanttChartConfig $config;

    protected function setUp(): void
    {
        $this->config = GanttChartConfig::fromStream(STDOUT);
    }

    public function test_new_instance(): void
    {
        $this->assertSame('[', $this->config->startIncludedCharacter);
        $this->assertSame('(', $this->config->startExcludedCharacter);
        $this->assertSame(']', $this->config->endIncludedCharacter);
        $this->assertSame(')', $this->config->endExcludedCharacter);
        $this->assertSame('-', $this->config->bodyCharacter);
        $this->assertSame(' ', $this->config->spaceCharacter);
        $this->assertSame(60, $this->config->width);
        $this->assertSame(1, $this->config->gapSize);
        $this->assertSame([Color::Reset], $this->config->colors);
        $this->assertSame(Alignment::Left, $this->config->labelAlignment);
    }

    public function test_create_from_random(): void
    {
        $config1 = GanttChartConfig::fromRandomColor();
        $config2 = GanttChartConfig::fromRainbow();
        $this->assertContains($config1->colors[0], $config2->colors);
    }

    #[DataProvider('provideWidthCases')]
    public function test_width(int $size, int $expected): void
    {
        $this->assertSame($expected, $this->config->width($size)->width);
    }

    /**
     * @return \Iterator<string, array{int, int}>
     */
    public static function provideWidthCases(): \Iterator
    {
        yield '0 size' => [0, 10];
        yield 'negative size' => [-23, 10];
        yield 'basic usage' => [23, 23];
        yield 'default value' => [60, 60];
    }

    #[DataProvider('providerChars')]
    public function test_body(string $char, string $expected): void
    {
        $this->assertSame($expected, $this->config->bodyCharacter($char)->bodyCharacter);
    }

    #[DataProvider('providerChars')]
    public function test_end_excluded(string $char, string $expected): void
    {
        $this->assertSame($expected, $this->config->endExcludedCharacter($char)->endExcludedCharacter);
    }

    #[DataProvider('providerChars')]
    public function test_end_included(string $char, string $expected): void
    {
        $this->assertSame($expected, $this->config->endIncludedCharacter($char)->endIncludedCharacter);
    }

    #[DataProvider('providerChars')]
    public function test_start_excluded(string $char, string $expected): void
    {
        $this->assertSame($expected, $this->config->startExcludedCharacter($char)->startExcludedCharacter);
    }

    #[DataProvider('providerChars')]
    public function test_start_included(string $char, string $expected): void
    {
        $this->assertSame($expected, $this->config->startIncludedCharacter($char)->startIncludedCharacter);
    }

    #[DataProvider('providerChars')]
    public function test_space(string $char, string $expected): void
    {
        $this->assertSame($expected, $this->config->spaceCharacter($char)->spaceCharacter);
    }

    /**
     * @return \Iterator<(int | string), array{string, string}>
     */
    public static function providerChars(): \Iterator
    {
        yield ['-', '-'];
        yield ['=', '='];
        yield ['[', '['];
        yield [']', ']'];
        yield [')', ')'];
        yield ['(', '('];
        yield [' ', ' '];
        yield ['#', '#'];
        yield ["\t", "\t"];
        yield ['€', '€'];
        yield ['█', '█'];
        yield [' ', ' '];
        yield ['\uD83D\uDE00', '😀'];
    }

    #[DataProvider('provideColorsCases')]
    public function test_colors(Color $char, Color $expected): void
    {
        $this->assertSame($expected, $this->config->colors($char)->colors[0]);
    }

    /**
     * @return \Iterator<(int | string), array{Color, Color}>
     */
    public static function provideColorsCases(): \Iterator
    {
        yield [Color::Reset, Color::Reset];
        yield [Color::White, Color::White];
    }

    public function test_with_colors_return_same_instance(): void
    {
        $this->assertSame($this->config, $this->config->colors());
    }

    #[DataProvider('provideWithHeadBlockThrowsInvalidArgumentExceptionCases')]
    public function test_with_head_block_throws_invalid_argument_exception(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->config->bodyCharacter($input);
    }

    /**
     * @return \Iterator<(int | string), array{string}>
     */
    public static function provideWithHeadBlockThrowsInvalidArgumentExceptionCases(): \Iterator
    {
        yield ['coucou'];
        yield ['\uD83D\uDE00\uD83D\uDE00'];
    }

    #[DataProvider('providerGaps')]
    public function test_left_margin(int $gap, int $expected): void
    {
        $this->assertSame($expected, $this->config->leftMarginSize($gap)->leftMarginSize);
    }

    #[DataProvider('providerGaps')]
    public function test_gap(int $gap, int $expected): void
    {
        $this->assertSame($expected, $this->config->gapSize($gap)->gapSize);
    }

    public static function providerGaps(): \Iterator
    {
        yield 'single gap' => [
            'gap' => 1,
            'expected' => 1,
        ];
        yield 'empty gap' => [
            'gap' => 0,
            'expected' => 0,
        ];
        yield 'sequence with invalid chars' => [
            'gap' => -42,
            'expected' => 1,
        ];
    }

    #[DataProvider('providePaddingCases')]
    public function test_padding(Alignment $padding, Alignment $expected): void
    {
        $this->assertSame($expected, $this->config->labelAlignment($padding)->labelAlignment);
    }

    public static function providePaddingCases(): \Iterator
    {
        yield 'default' => [
            'padding' => Alignment::Left,
            'expected' => Alignment::Left,
        ];
        yield 'changing wit a defined config' => [
            'padding' => Alignment::Right,
            'expected' => Alignment::Right,
        ];
    }

    public function test_alignment_will_fail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Alignment::fromPadding(42);
    }

    public function test_with_output_always_returns_a_new_instance(): void
    {
        $newConfig = $this->config->output(
            new StreamOutput(STDOUT, Terminal::Posix)
        );
        $this->assertNotSame($this->config, $newConfig);
        $this->assertEquals($newConfig->output, $this->config->output);
    }

    public function test_constructors(): void
    {
        $this->assertEquals(
            GanttChartConfig::fromOutput(
                new StreamOutput(STDERR, Terminal::Posix)
            ),
            GanttChartConfig::fromStream(STDERR),
        );
    }
}
