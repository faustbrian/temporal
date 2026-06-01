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

namespace Cline\Temporal\Period;

use DateTimeImmutable;

use function date_default_timezone_set;
use function json_decode;
use function json_encode;

/**
 * @internal
 */
final class PeriodFormattingTest extends PeriodTestCase
{
    public function test_to_string(): void
    {
        date_default_timezone_set('Africa/Nairobi');
        $period = Period::fromDate('2014-05-01', '2014-05-08');
        $format = 'Y-m-d\TH:i:s';

        $this->assertSame('2014-04-30T21:00:00/2014-05-07T21:00:00', $period->toIso8601($format));
        $this->assertSame('[2014-05-01T00:00:00, 2014-05-08T00:00:00)', $period->toIso80000($format));
        $this->assertSame('[2014-05-01T00:00:00, 2014-05-08T00:00:00[', $period->toBourbaki($format));
    }

    public function test_json_serialize(): void
    {
        $period = Period::fromMonth(2_015, 4);
        $json = json_encode($period);

        $this->assertNotFalse($json);

        /** @var array{startDate:string, endDate:string, startDateIncluded:bool, endDateIncluded:bool} $res */
        $res = json_decode($json, true);

        $this->assertEquals($period->startDate, new DateTimeImmutable($res['startDate']));
        $this->assertEquals($period->endDate, new DateTimeImmutable($res['endDate']));
        $this->assertSame($period->bounds->isStartIncluded(), $res['startDateIncluded']);
        $this->assertSame($period->bounds->isEndIncluded(), $res['endDateIncluded']);
    }

    public function test_format(): void
    {
        date_default_timezone_set('Africa/Nairobi');
        $this->assertSame('[2015-04, 2015-05)', Period::fromMonth(2_015, 4)->toIso80000('Y-m'));
        $this->assertSame(
            '[2015-04-01 Africa/Nairobi, 2015-04-01 Africa/Nairobi)',
            Period::fromDate('2015-04-01', '2015-04-01')->toIso80000('Y-m-d e'),
        );
    }
}
