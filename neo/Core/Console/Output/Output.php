<?php
declare(strict_types=1);

namespace Neo\Core\Console\Output;

use Neo\Core\Console\Enum\Color;

final class Output
{
    public static function success(string $message): void
    {
        echo Color::GREEN->wrap(Color::BOLD->apply() . '✔ ' . Color::RESET->apply() . $message) . "\n";
    }

    public static function error(string $message): void
    {
        echo Color::RED->wrap(Color::BOLD->apply() . '✘ ' . Color::RESET->apply() . $message) . "\n";
    }

    public static function warning(string $message): void
    {
        echo Color::YELLOW->wrap(Color::BOLD->apply() . '⚠ ' . Color::RESET->apply() . $message) . "\n";
    }

    public static function info(string $message): void
    {
        echo Color::CYAN->wrap('→ ') . $message . "\n";
    }

    public static function muted(string $message): void
    {
        echo Color::DIM->wrap($message) . "\n";
    }

    public static function step(string $step, string $message): void
    {
        echo Color::BOLD->wrap(Color::BLUE->wrap("[$step]")) . ' ' . $message . "\n";
    }

    public static function skip(string $message): void
    {
        echo Color::DIM->wrap(Color::YELLOW->wrap('[SKIP] ') . $message) . "\n";
    }

    public static function label(string $label, string $value): void
    {
        echo Color::BOLD->wrap(str_pad($label, 20)) . ' ' . $value . "\n";
    }

    public static function title(string $message): void
    {
        $line = str_repeat('─', 60);
        echo "\n" . Color::BOLD->wrap(Color::WHITE->wrap($message)) . "\n";
        echo Color::DIM->wrap($line) . "\n";
    }

    public static function separator(int $width = 60): void
    {
        echo Color::DIM->wrap(str_repeat('─', $width)) . "\n";
    }

    public static function newLine(): void
    {
        echo "\n";
    }

    public static function badge(string $text, string $color = 'blue'): string
    {
        $bg = match ($color) {
            'green' => Color::BG_GREEN,
            'red' => Color::BG_RED,
            'yellow' => Color::BG_YELLOW,
            'cyan' => Color::BG_CYAN,
            default => Color::BG_BLUE,
        };

        return $bg->wrap(Color::BOLD->apply() . ' ' . $text . ' ');
    }

    public static function usage(string $command, string $description): void
    {
        echo "\n";
        echo Color::BOLD->wrap('Command     : ') . Color::CYAN->wrap($command) . "\n";
        echo Color::BOLD->wrap('Description : ') . $description . "\n";
        echo "\n";
    }

    public static function option(string $flag, string $description): void
    {
        echo '  ' . Color::YELLOW->wrap(str_pad($flag, 36)) . $description . "\n";
    }

    public static function argument(string $name, string $description, string $mode = ''): void
    {
        $label = $name . ($mode !== '' ? ' (' . $mode . ')' : '');
        echo '  ' . Color::CYAN->wrap(str_pad($label, 36)) . $description . "\n";
    }

    public static function example(string $cmd): void
    {
        echo '  ' . Color::DIM->wrap('$ ') . Color::GREEN->wrap($cmd) . "\n";
    }

    public static function progress(int $current, int $total, string $label = ''): void
    {
        $width = 30;
        $ratio = $total > 0 ? $current / $total : 1;
        $filled = (int) round($ratio * $width);
        $empty = $width - $filled;

        $bar = Color::GREEN->wrap(str_repeat('█', $filled));
        $bar .= Color::DIM->wrap(str_repeat('░', $empty));

        $pct = str_pad((int) ($ratio * 100) . '%', 4, ' ', STR_PAD_LEFT);
        $count = "$current/$total";

        echo "\r  [$bar] $pct  $count  $label";

        if ($current >= $total) {
            echo "\n";
        }
    }

    public static function colorize(string $text, string $color): string
    {
        $code = match ($color) {
            'red' => Color::RED,
            'green' => Color::GREEN,
            'yellow' => Color::YELLOW,
            'blue' => Color::BLUE,
            'magenta' => Color::MAGENTA,
            'cyan' => Color::CYAN,
            'white' => Color::WHITE,
            'bold' => Color::BOLD,
            'dim' => Color::DIM,
            'black' => Color::BLACK,
            default => null,
        };

        return $code !== null ? $code->wrap($text) : $text;
    }
}