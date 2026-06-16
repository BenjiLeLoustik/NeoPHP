<?php
declare(strict_types=1);

namespace Neo\Core\Console\Enum;

enum OutputType
{
    case SUCCESS;
    case ERROR;
    case WARNING;
    case INFO;
    case MUTED;
    case STEP;
    case SKIP;
}