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
use RuntimeException;
use TypeError;

use const PHP_EOL;

use function chr;
use function fopen;
use function rewind;
use function stream_get_contents;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class StreamOutputTest extends TestCase
{
    public function test_create_stream_with_invalid_parameter(): void
    {
        $this->expectException(TypeError::class);
        /** @var mixed $stream */
        $stream = __DIR__.'/data/foo.csv';

        new StreamOutput($stream, Terminal::Posix);
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

    /**
     * @return iterable<string, array{message: string, expected: string}>
     */
    public static function provideWritelnCases(): iterable
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

    #[DataProvider('provideWriteln_unknownCases')]
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

    /**
     * @return iterable<string, array{message: string, expected: string}>
     */
    public static function provideWriteln_unknownCases(): iterable
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
        $stream = fopen('php://memory', 'r+b');

        if (false === $stream) {
            throw new RuntimeException('Unable to create memory stream.');
        }

        return $stream;
    }
}
