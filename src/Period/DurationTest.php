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

namespace Cline\Temporal\Period;

use DateInterval;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

use function count;
use function date_default_timezone_get;
use function date_default_timezone_set;
use function implode;
use function is_int;
use function is_string;
use function mb_rtrim;
use function sprintf;
use function var_export;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class DurationTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    /**
     * @return iterable<string, array<string>>
     */
    public static function getDurationCreateFailsProvider(): iterable
    {
        yield from [
            'invalid interval spec 1' => ['PT'],
            'invalid interval spec 2' => ['P'],
            'invalid interval spec 3' => ['PT1'],
            'invalid interval spec 4' => ['P3'],
            'invalid interval spec 5' => ['PT3X'],
            'invalid interval spec 6' => ['PT3s'],
            'invalid string' => ['blablabbla'],
        ];
    }

    public function test_instantiation_from_set_state(): void
    {
        $duration = Duration::fromDateInterval(
            new DateInterval('P1D'),
        );

        /** @var Duration $generatedDuration */
        $generatedDuration = eval('return '.var_export($duration, true).';');

        $this->assertEquals($duration, $generatedDuration);
    }

    public function test_create_from_date_interval(): void
    {
        $duration = Duration::fromDateInterval(
            new DateInterval('P1D'),
        );

        $this->assertSame(1, $duration->dateInterval->d);
        $this->assertFalse($duration->dateInterval->days);
    }

    public function test_create_from_date_string(): void
    {
        $duration = Duration::fromDateString('+1 DAY');

        $this->assertSame(1, $duration->dateInterval->d);
        $this->assertFalse($duration->dateInterval->days);
    }

    #[DataProvider('provideCreate_from_secondsCases')]
    public function test_create_from_seconds(int $seconds, int $fraction, string $expected): void
    {
        $this->assertSame($expected, $this->formatDuration(Duration::fromSeconds($seconds, $fraction)));
    }

    /**
     * @return Iterator<string, array{seconds: int, fraction: int, expected: string}>
     */
    public static function provideCreate_from_secondsCases(): iterable
    {
        yield 'from an integer' => [
            'seconds' => 0,
            'fraction' => 0,
            'expected' => 'PT0S',
        ];

        yield 'negative seconds' => [
            'seconds' => -3,
            'fraction' => 2_345,
            'expected' => 'PT-3.002345S',
        ];
    }

    public function test_it_fails_to_create_a_duration_with_a_negative_fraction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Duration::fromSeconds(32, -1);
    }

    #[DataProvider('provideInterval_with_fractionCases')]
    public function test_interval_with_fraction(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->formatDuration(Duration::fromIsoString($input)));
    }

    /**
     * @return iterable<string, array{input: string, expected: string}>
     */
    public static function provideInterval_with_fractionCases(): iterable
    {
        yield 'IsoString with fraction v1' => [
            'input' => 'PT3.1S',
            'expected' => 'PT3.1S',
        ];

        yield 'IsoString with fraction v2' => [
            'input' => 'P0000-00-00T00:05:00.023658',
            'expected' => 'PT5M0.023658S',
        ];

        yield 'IsoString with fraction v3' => [
            'input' => 'PT5M23658F',
            'expected' => 'PT5M0.023658S',
        ];
    }

    #[DataProvider('provideCreate_from_chrono_string_failsCases')]
    public function test_create_from_chrono_string_fails(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        Duration::fromChronoString($input);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideCreate_from_chrono_string_failsCases(): iterable
    {
        yield 'invalid string' => ['foobar'];

        yield 'float like string' => ['-28.5'];
    }

    #[DataProvider('provideCreate_from_chrono_string_succeedsCases')]
    public function test_create_from_chrono_string_succeeds(string $chronometer, string $expected): void
    {
        $duration = Duration::fromChronoString($chronometer);

        $this->assertSame($expected, $this->formatDuration($duration));
    }

    /**
     * @return iterable<string, array{chronometer: string, expected: string}>
     */
    public static function provideCreate_from_chrono_string_succeedsCases(): iterable
    {
        yield 'minute and seconds' => [
            'chronometer' => '1:2',
            'expected' => 'PT1M2S',
        ];

        yield 'hour, minute, seconds' => [
            'chronometer' => '1:2:3',
            'expected' => 'PT1H2M3S',
        ];

        yield 'handling 0 prefix' => [
            'chronometer' => '00001:00002:000003.0004',
            'expected' => 'PT1H2M3.0004S',
        ];

        yield 'negative chrono' => [
            'chronometer' => '-12:28.5',
            'expected' => 'PT12M28.5S',
        ];
    }

    public function test_create_from_time_string_fails(): void
    {
        $this->expectException(Throwable::class);

        Duration::fromTimeString('123');
    }

    #[DataProvider('provideCreate_from_time_string_succeedsCases')]
    public function test_create_from_time_string_succeeds(string $chronometer, string $expected): void
    {
        $duration = Duration::fromTimeString($chronometer);

        $this->assertSame($expected, $this->formatDuration($duration));
    }

    /**
     * @return Iterator<(int|string), array{chronometer: string, expected: string}>
     */
    public static function provideCreate_from_time_string_succeedsCases(): iterable
    {
        yield 'hour and minute' => [
            'chronometer' => '1:2',
            'expected' => 'PT1H2M',
        ];

        yield 'hour, minute, seconds' => [
            'chronometer' => '1:2:3',
            'expected' => 'PT1H2M3S',
        ];

        yield 'handling 0 prefix' => [
            'chronometer' => '00001:00002:000003.0004',
            'expected' => 'PT1H2M3.0004S',
        ];

        yield 'negative chrono' => [
            'chronometer' => '-12:28',
            'expected' => 'PT-12H28M',
        ];

        yield 'negative chrono with seconds' => [
            'chronometer' => '-00:00:28.5',
            'expected' => 'PT28.5S',
        ];
    }

    #[DataProvider('provideAdjusted_toCases')]
    public function test_adjusted_to(string $input, int|string|DateTimeInterface $reference_date, string $expected): void
    {
        $duration = Duration::fromIsoString($input);

        $date = match (true) {
            is_int($reference_date) => DatePoint::fromTimestamp($reference_date)->date,
            is_string($reference_date) => DatePoint::fromDateString($reference_date)->date,
            default => $reference_date,
        };

        $this->assertSame($expected, $this->formatDuration($duration->adjustedTo($date)));
    }

    /**
     * @return iterable<string, array{input: string, reference_date: int|string|DateTimeInterface, expected: string}>
     */
    public static function provideAdjusted_toCases(): iterable
    {
        yield 'nothing to carry over' => [
            'input' => 'PT3H',
            'reference_date' => 0,
            'expected' => 'PT3H',
        ];

        yield 'hour transformed in days' => [
            'input' => 'PT24H',
            'reference_date' => 0,
            'expected' => 'P1D',
        ];

        yield 'days transformed in months' => [
            'input' => 'P31D',
            'reference_date' => 0,
            'expected' => 'P1M',
        ];

        yield 'months transformed in years' => [
            'input' => 'P12M',
            'reference_date' => 0,
            'expected' => 'P1Y',
        ];

        yield 'leap year' => [
            'input' => 'P29D',
            'reference_date' => '2020-02-01',
            'expected' => 'P1M',
        ];

        yield 'none leap year' => [
            'input' => 'P29D',
            'reference_date' => '2019-02-01',
            'expected' => 'P1M1D',
        ];

        /*
        * THIS IS FIXED AS OF PHP8.1
        'dst day' => [
            'input' => 'PT4H',
            'reference_date' => new DateTime('2019-03-31', new DateTimeZone('Europe/Brussels')),
            'expected' => 'PT3H',
        ],
        */
        yield 'non dst day' => [
            'input' => 'PT4H',
            'reference_date' => new DateTime('2019-04-01', new DateTimeZone('Europe/Brussels')),
            'expected' => 'PT4H',
        ];
    }

    private function formatDuration(Duration $duration): string
    {
        $interval = $duration->dateInterval;

        $date = ['P'];

        if (0 !== $interval->y) {
            $date[] = '%yY';
        }

        if (0 !== $interval->m) {
            $date[] = '%mM';
        }

        if (0 !== $interval->d) {
            $date[] = '%dD';
        }

        $time = ['T'];

        if (0 !== $interval->h) {
            $time[] = '%hH';
        }

        if (0 !== $interval->i) {
            $time[] = '%iM';
        }

        $dateFormat = implode('', $date);
        $timeFormat = 1 === count($time) ? '' : implode('', $time);

        if (0.0 !== $interval->f) {
            $second = $interval->s + $interval->f;

            if (0 > $interval->s) {
                $second = $interval->s - $interval->f;
            }

            return $interval->format($dateFormat.('' === $timeFormat ? 'T' : $timeFormat))
                .mb_rtrim(sprintf('%f', $second), '0').'S';
        }

        if (0 !== $interval->s) {
            return $interval->format($dateFormat.$timeFormat.'%sS');
        }

        if (1 === count($time) && 1 === count($date)) {
            return 'PT0S';
        }

        return $interval->format($dateFormat.$timeFormat);
    }
}
