<?php
declare(strict_types=1);

namespace Neo\Core\Console\Helper;

final class Output
{
    private const string RESET = "\033[0m";
    private const string BOLD = "\033[1m";
    private const string DIM = "\033[2m";

    private const string BLACK = "\033[30m";
    private const string RED = "\033[31m";
    private const string GREEN = "\033[32m";
    private const string YELLOW = "\033[33m";
    private const string BLUE = "\033[34m";
    private const string MAGENTA = "\033[35m";
    private const string CYAN = "\033[36m";
    private const string WHITE = "\033[37m";

    private const string BG_RED = "\033[41m";
    private const string BG_GREEN = "\033[42m";
    private const string BG_YELLOW = "\033[43m";
    private const string BG_BLUE = "\033[44m";
    private const string BG_CYAN = "\033[46m";

    public static function success(string $message): void
    {
        echo self::GREEN . self::BOLD . '✔ ' . self::RESET . self::GREEN . $message . self::RESET . "\n";
    }

    public static function error(string $message): void
    {
        echo self::RED . self::BOLD . '✘ ' . self::RESET . self::RED . $message . self::RESET . "\n";
    }

    public static function warning(string $message): void
    {
        echo self::YELLOW . self::BOLD . '⚠ ' . self::RESET . self::YELLOW . $message . self::RESET . "\n";
    }

    public static function info(string $message): void
    {
        echo self::CYAN . '→ ' . self::RESET . $message . "\n";
    }

    public static function muted(string $message): void
    {
        echo self::DIM . $message . self::RESET . "\n";
    }

    public static function step(string $step, string $message): void
    {
        echo self::BOLD . self::BLUE . "[$step]" . self::RESET . ' ' . $message . "\n";
    }

    public static function skip(string $message): void
    {
        echo self::DIM . self::YELLOW . '[SKIP] ' . self::RESET . self::DIM . $message . self::RESET . "\n";
    }

    public static function label(string $label, string $value): void
    {
        echo self::BOLD . str_pad($label, 20) . self::RESET . ' ' . $value . "\n";
    }

    public static function title(string $message): void
    {
        $line = str_repeat('─', 60);
        echo "\n" . self::BOLD . self::WHITE . $message . self::RESET . "\n";
        echo self::DIM . $line . self::RESET . "\n";
    }

    public static function separator(int $width = 60): void
    {
        echo self::DIM . str_repeat('─', $width) . self::RESET . "\n";
    }

    public static function newLine(): void
    {
        echo "\n";
    }

    public static function badge(string $text, string $color = 'blue'): string
    {
        $code = match ($color) {
            'green' => self::BG_GREEN,
            'red' => self::BG_RED,
            'yellow' => self::BG_YELLOW,
            'cyan' => self::BG_CYAN,
            default => self::BG_BLUE,
        };

        return $code . self::BOLD . ' ' . $text . ' ' . self::RESET;
    }

    public static function usage(string $command, string $description): void
    {
        echo "\n";
        echo self::BOLD . 'Command : ' . self::RESET . self::CYAN . $command . self::RESET . "\n";
        echo self::BOLD . 'Description : ' . self::RESET . $description . "\n";
        echo "\n";
    }

    public static function option(string $flag, string $description): void
    {
        echo '  ' . self::YELLOW . str_pad($flag, 30) . self::RESET . $description . "\n";
    }

    public static function example(string $cmd): void
    {
        echo '  ' . self::DIM . '$ ' . self::RESET . self::GREEN . $cmd . self::RESET . "\n";
    }

    public static function progress(int $current, int $total, string $label = ''): void
    {
        $width = 30;
        $ratio = $total > 0 ? $current / $total : 1;
        $filled = (int) round($ratio * $width);
        $empty = $width - $filled;

        $bar = self::GREEN . str_repeat('█', $filled) . self::RESET;
        $bar .= self::DIM . str_repeat('░', $empty) . self::RESET;

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
            'red' => self::RED,
            'green' => self::GREEN,
            'yellow' => self::YELLOW,
            'blue' => self::BLUE,
            'magenta' => self::MAGENTA,
            'cyan' => self::CYAN,
            'white' => self::WHITE,
            'bold' => self::BOLD,
            'dim' => self::DIM,
            'black' => self::BLACK,
            default => '',
        };

        return $code . $text . self::RESET;
    }

    public static function prompt(string $message): string
    {
        return Input::ask($message);
    }

    public static function confirm(string $message, bool $default = true): bool
    {
        return Input::confirm($message, $default);
    }
}