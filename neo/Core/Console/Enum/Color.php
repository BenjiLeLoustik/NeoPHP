<?php

declare(strict_types=1);

namespace Neo\Core\Console\Enum;
enum Color: string
{
    case RESET = "\033[0m";
    case BOLD = "\033[1m";
    case DIM = "\033[2m";
    case BLACK = "\033[30m";
    case RED = "\033[31m";
    case GREEN = "\033[32m";
    case YELLOW = "\033[33m";
    case BLUE = "\033[34m";
    case MAGENTA = "\033[35m";
    case CYAN = "\033[36m";
    case WHITE = "\033[37m";
    case BG_RED = "\033[41m";
    case BG_GREEN = "\033[42m";
    case BG_YELLOW = "\033[43m";
    case BG_BLUE = "\033[44m";
    case BG_CYAN = "\033[46m";

    public function wrap(string $text): string
    {
        return $this->value . $text . self::RESET->value;
    }

    public function apply(): string
    {
        return $this->value;
    }
}