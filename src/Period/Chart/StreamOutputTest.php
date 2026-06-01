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
use TypeError;

use const PHP_EOL;

use function chr;
use function fopen;
use function rewind;
use function stream_get_contents;

/**
 * @internal
 */
final class StreamOutputTest extends TestCase
{
    public function test_create_stream_with_invalid_parameter(): void
    {
        $this->expectException(TypeError::class);
        new StreamOutput(__DIR__.'/data/foo.csv', Terminal::Posix);
    }

    #[DataProvider('provideWritelnCases')]
    public function test_writeln(string $message, string $expected): void
    {
        $stream = $this->setStream();
        $output = new StreamOutput($stream, Terminal::Posix);
        $output->writeln($message, Color::Blue);
        $output->writeln($message);
        rewind($stream);

        /** @var string $data */
        $data = stream_get_contents($stream);

        $this->assertStringContainsString($expected, $data);
    }

    public static function provideWritelnCases(): \Iterator
    {
        yield 'empty message' => [
            'message' => '',
            'expected' => '',
        ];
        yield 'simple message' => [
            'message' => "I'm the king of the world",
            'expected' => chr(27).'[34m'."I'm the king of the world".chr(27).'[0m'.PHP_EOL,
        ];
    }

    #[DataProvider('provideWritelnUnknownCases')]
    public function test_writeln_unknown(string $message, string $expected): void
    {
        $stream = $this->setStream();
        $output = new StreamOutput($stream, Terminal::Colorless);
        $output->writeln($message, Color::Blue);
        $output->writeln($message);
        rewind($stream);

        /** @var string $data */
        $data = stream_get_contents($stream);

        $this->assertStringContainsString($expected, $data);
    }

    public static function provideWritelnUnknownCases(): \Iterator
    {
        yield 'empty message' => [
            'message' => '',
            'expected' => '',
        ];
        yield 'simple message' => [
            'message' => "I'm the king of the world",
            'expected' => "I'm the king of the world".PHP_EOL,
        ];
    }

    /**
     * @return resource
     */
    private function setStream()
    {
        return fopen('php://memory', 'r+b');
    }
}
