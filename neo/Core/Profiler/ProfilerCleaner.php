<?php

declare(strict_types=1);

namespace Neo\Core\Profiler;

final class ProfilerCleaner
{
    private const int MAX_PROFILES = 10;

    private const int MAX_AGE_SECONDS = 86400;

    public static function clean(string $storageDir): void
    {
        $files = glob($storageDir . '/*.json');

        if ($files === false || $files === []) {
            return;
        }

        $now = time();
        $kept = [];

        foreach ($files as $file) {
            $age = $now - (filemtime($file) ?: 0);

            if ($age > self::MAX_AGE_SECONDS) {
                @unlink($file);
                continue;
            }

            $kept[] = $file;
        }

        if (count($kept) <= self::MAX_PROFILES) {
            return;
        }

        usort($kept, static fn (string $a, string $b) => filemtime($a) <=> filemtime($b));

        $toRemove = count($kept) - self::MAX_PROFILES;

        for ($i = 0; $i < $toRemove; $i++) {
            @unlink($kept[$i]);
        }
    }
}