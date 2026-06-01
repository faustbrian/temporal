<?php

declare(strict_types=1);

namespace Cline\Temporal\Time;

enum IntervalType
{
    case Linear;
    case Overflow;
    case Circular;
    case Collapsed;
}
