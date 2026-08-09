<?php

declare(strict_types=1);

namespace Neo\Core\Profiler;

final class TimelineRecorder
{
    /** @var list<array{category: string, label: string, offset: float, duration: float}> */
    private static array $entries = [];

    private static ?float $fallbackStart = null;

    public static function start(): float
    {
        if (defined('NEO_START_TIME')) {
            return (float) NEO_START_TIME;
        }

        return self::$fallbackStart ??= microtime(true);
    }

    public static function record(string $category, string $label, float $startedAt, ?float $endedAt = null): void
    {
        $endedAt ??= microtime(true);

        self::$entries[] = [
            'category' => $category,
            'label' => $label,
            'offset' => round(($startedAt - self::start()) * 1000, 2),
            'duration' => round(($endedAt - $startedAt) * 1000, 2),
        ];
    }

    /**
     * @return list<array{category: string, label: string, offset: float, duration: float}>
     */
    public static function getEntries(): array
    {
        $entries = self::$entries;
        usort($entries, static fn (array $a, array $b) => $a['offset'] <=> $b['offset']);

        return $entries;
    }
}