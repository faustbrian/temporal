<?php

declare(strict_types=1);

namespace Cline\Temporal\Time;

enum TimeFormatLength
{
    case Short;
    case Medium;
    case Long;
    case Full;
}
