<?php
declare(strict_types=1);

namespace Neo\Core\Database\Access\Collector;

use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Profiler\Interface\CollectorInterface;

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
    public function record(string $sql, array $params, float $duration, ?string $connection = null): void
    {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => round($duration, 3),
            'connection' => $connection ?? DatabaseConnection::getDefaultName() ?? 'default',
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

    public function renderTab(array $data): string
    {
        $count = $data['count'] ?? 0;
        $ms = $data['total_ms'] ?? 0;
        $color = $count > 20 ? '#f87171' : ($count > 10 ? '#fb923c' : '#a78bfa');

        return <<<HTML
<div class="n-tab" onclick="neoSwitch('database')" title="Requêtes SQL">
    <span class="n-label">SQL</span>
    <span class="n-value" style="color:{$color}">{$count} req</span>
    <span class="n-badge">{$ms} ms</span>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPanel(array $data): string
    {
        $queries = $data['queries'] ?? [];

        if (empty($queries)) {
            return '<p class="n-empty">No SQL queries.</p>';
        }

        $rows = '';
        foreach ($queries as $i => $q) {
            $n = $i + 1;
            $sql = htmlspecialchars($q['sql']);
            $ms = htmlspecialchars((string) $q['duration']);
            $params = htmlspecialchars(json_encode($q['params'], JSON_UNESCAPED_UNICODE));
            $conn = htmlspecialchars((string) ($q['connection'] ?? 'default'));

            $rows .= <<<HTML
<tr>
    <td style="color:#52525b;width:28px">{$n}</td>
    <td class="n-sql">{$sql}</td>
    <td class="n-conn">{$conn}</td>
    <td class="n-params">{$params}</td>
    <td class="n-ms">{$ms} ms</td>
</tr>
HTML;
        }

        return <<<HTML
<table>
    <thead>
        <tr><th>#</th><th>SQL</th><th>Connection</th><th>Params</th><th style="text-align:right">Time</th></tr>
    </thead>
    <tbody>{$rows}</tbody>
</table>
HTML;
    }
}