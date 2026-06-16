<?php
declare(strict_types=1);

namespace Neo\Core\Console\Enum;

enum ExitCode: int
{
    case SUCCESS = 0;
    case FAILURE = 1;
    case INVALID = 2;
}