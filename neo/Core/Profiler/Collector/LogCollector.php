<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

class LogCollector implements CollectorInterface
{
    private array $entries = [];

    public function getName(): string
    {
        return 'logs';
    }

    public function record(string $level, string $message, array $context, string $origin): void
    {
        $this->entries[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'origin' => $origin,
            'time' => microtime(true),
        ];
    }

    public function collect(): array
    {
        $byLevel = [];
        foreach ($this->entries as $entry) {
            $byLevel[$entry['level']] = ($byLevel[$entry['level']] ?? 0) + 1;
        }

        return [
            'count' => count($this->entries),
            'by_level' => $byLevel,
            'entries' => $this->entries,
        ];
    }
}