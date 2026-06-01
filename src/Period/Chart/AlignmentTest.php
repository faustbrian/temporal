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

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use const STR_PAD_BOTH;
use const STR_PAD_LEFT;
use const STR_PAD_RIGHT;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class AlignmentTest extends TestCase
{
    public function test_it_can_be_construct_from_padding(): void
    {
        $this->assertSame(Alignment::Left, Alignment::fromPadding(STR_PAD_LEFT));
        $this->assertSame(Alignment::Right, Alignment::fromPadding(STR_PAD_RIGHT));
        $this->assertSame(Alignment::Center, Alignment::fromPadding(STR_PAD_BOTH));
    }

    public function test_it_will_fail_on_unknown_padding(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Alignment::fromPadding(42);
    }

    public function test_it_can_be_converted_to_padding(): void
    {
        $this->assertSame(STR_PAD_LEFT, Alignment::Left->toPadding());
        $this->assertSame(STR_PAD_RIGHT, Alignment::Right->toPadding());
        $this->assertSame(STR_PAD_BOTH, Alignment::Center->toPadding());
    }
}
