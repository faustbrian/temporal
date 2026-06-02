<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Time;

use const STR_PAD_LEFT;

use function implode;
use function mb_str_pad;
use function mb_substr;
use function mb_trim;
use function preg_match;
use function throw_if;

/**
 * Supported string encodings for {@see Time} values.
 *
 * The enum owns both parsing and formatting rules so time notation stays
 * symmetric across the package.
 * @author Brian Faust <brian@cline.sh>
 */
enum TimeFormat
{
    case Compact;
    case Iso8601;

    private const string REGEXP_ISO8601 = '/^
        (?<hour>\d{1,2}):
        (?<minute>\d{1,2})
        (:(?<second>\d{1,2}))?
        (?:\.(?<microsecond>\d{1,6}))?
    $/x';

    private const string REGEXP_COMPACT = '@^
        (?<hour>\d+)\s*h\s*
        (?<minute>\d+)\s*m\s*
        (?:(?<second>\d+)\s*s\s*)?
        (?:(?<microsecond>\d+)\s*(µs|us)\s*)?
    $@x';

    /**
     * Parse a textual clock representation into a normalized {@see Time}.
     *
     * @throws InvalidTime
     */
    public function decode(string $data): Time
    {
        $regexp = match ($this) {
            self::Iso8601 => self::REGEXP_ISO8601,
            self::Compact => self::REGEXP_COMPACT,
        };

        $data = mb_trim($data);
        throw_if(1 !== preg_match($regexp, $data, $parts), InvalidTime::class, 'Unknown or bad format `'.$data.'`.');
        $parts += ['hour' => '0', 'minute' => '0', 'second' => '0', 'microsecond' => '0'];

        return Time::at(
            hour: (int) $parts['hour'],
            minute: (int) $parts['minute'],
            second: (int) $parts['second'],
            microsecond: (int) mb_str_pad(mb_substr($parts['microsecond'], 0, 6), 6, '0'),
        );
    }

    /**
     * @return non-empty-string
     */
    public function encode(Time $time): string
    {
        return match ($this) {
            self::Iso8601 => self::toIso8601($time),
            self::Compact => self::toCompact($time),
        };
    }

    /**
     * Format a time using a fixed-width ISO-like clock representation.
     *
     * @return non-empty-string
     */
    private static function toIso8601(Time $time): string
    {
        $pad = static fn (int $value, int $length): string => mb_str_pad((string) $value, $length, '0', STR_PAD_LEFT);
        $formatted = $pad($time->hour, 2).':'.$pad($time->minute, 2).':'.$pad($time->second, 2);

        if (0 !== $time->microsecond) {
            $formatted .= '.'.$pad($time->microsecond, 6);
        }

        return $formatted;
    }

    /**
     * Format a time using the compact human-oriented `12h34m56s789µs` style.
     *
     * @return non-empty-string
     */
    private static function toCompact(Time $time): string
    {
        $parts = [];
        $parts[] = $time->hour.'h';
        $parts[] = $time->minute.'m';

        if (0 !== $time->second || 0 !== $time->microsecond) {
            $parts[] = $time->second.'s';
        }

        if (0 !== $time->microsecond) {
            $parts[] = $time->microsecond.'µs';
        }

        return implode('', $parts);
    }
}
