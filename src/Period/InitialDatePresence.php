<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Temporal\Period;

/**
 * Presence Enum.
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since   5.0.0
 * @deprecated since version 5.2.0
 */
enum InitialDatePresence
{
    case Excluded;
    case Included;
}
