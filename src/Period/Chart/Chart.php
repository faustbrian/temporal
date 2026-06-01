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

/**
 * An interface to draw a dataset of intervals.
 * @author Brian Faust <brian@cline.sh>
 */
interface Chart
{
    /**
     * Visualizes one or more intervals provided via a Dataset object.
     */
    public function stroke(Data $dataset): void;
}
