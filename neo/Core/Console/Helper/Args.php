<?php
declare(strict_types=1);

namespace Neo\Core\Console\Helper;

final class Args
{
    public static function option(array $args, string $option): ?string
    {
        $count = count($args);

        for ($i = 0; $i < $count; $i++) {
            if (str_starts_with($args[$i], '=')) {
                return explode('=', $args[$i], 2)[1];
            }

            if ($args[$i] === $option && isset($args[$i + 1]) && !str_starts_with($args[$i], '-')) {
                return $args[$i + 1];
            }
        }

        return null;
    }

    public static function flag(array $args, string $flag): bool
    {
        return in_array($flag, $args, true);
    }

    public static function positional(array $args, int $index): ?string
    {
        $positionals = array_values(
            array_filter($args, static fn(string $a) => !str_starts_with($a, '-'))
        );

        return $positionals[$index] ?? null;
    }

    public static function positionals(array $args): array
    {
        return array_values(
            array_filter($args, static fn(string $a) => !str_starts_with($a, '-'))
        );
    }
}