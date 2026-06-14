<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Collector;

class QueryCollector implements CollectorInterface
{
    /**
     * @var array<int, array{
     *     sql: string,
     *     params: array<string, mixed>,
     *     duration: float
     * }>
     */
    private array $queries = [];

    public function getName(): string
    {
        return 'database';
    }

    /**
     * @param array<string, mixed> $params
     */
    public function record(string $sql, array $params, float $duration): void
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => round($duration, 3),
        ];
    }

    /**
     * @return array<string, mixed>
     */
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