<?php

declare(strict_types=1);

namespace Cline\Temporal\Time;

enum RoundingMode
{
    case Floor;
    case Round;
    case Ceil;
}
