<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

class QueryCollector implements CollectorInterface
{
    private array $queries = [];

    public function getName(): string
    {
        return 'database';
    }

    public function record(string $sql, array $params, float $duration): void
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => round($duration, 3),
        ];
    }

    public function collect(): array
    {
        $total = array_sum(
            array_column($this->queries, 'duration')
        );

        return [
            'count' => count($this->queries),
            'total_ms' => round($total, 3),
            'queries' => $this->queries,
        ];
    }
}