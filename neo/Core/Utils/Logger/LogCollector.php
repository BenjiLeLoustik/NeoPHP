<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Logger;

use Neo\Core\Profiler\Interface\CollectorInterface;

class LogCollector implements CollectorInterface
{
    /**
     * @var array<int, array{
     *     level: string,
     *     message: string,
     *     context: array<string, mixed>,
     *     origin: string,
     *     time: float
     * }>
     */
    private array $entries = [];

    public function getName(): string
    {
        return 'logs';
    }

    /**
     * @param array<string, mixed> $context
     */
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

    /**
     * @return array<string, mixed>
     */
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

    public function renderTab(array $data): string
    {
        $count = $data['count'] ?? 0;
        $entries = $data['entries'] ?? [];
        $color = $this->badgeColor($entries);

        return <<<HTML
<div class="n-tab" onclick="neoSwitch('logs')" title="Logs">
    <span class="n-label">Logs</span>
    <span class="n-value" style="color:{$color}">{$count}</span>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPanel(array $data): string
    {
        $entries = $data['entries'] ?? [];

        if (empty($entries)) {
            return '<p class="n-empty">Aucun log émis.</p>';
        }

        $rows = '';
        foreach ($entries as $e) {
            $cls = 'n-log-' . strtolower($e['level']);
            $level = htmlspecialchars($e['level']);
            $msg = htmlspecialchars($e['message']);
            $origin = htmlspecialchars($e['origin']);
            $ctx = $e['context']
                ? htmlspecialchars(json_encode($e['context'], JSON_UNESCAPED_UNICODE))
                : '';

            $rows .= <<<HTML
<tr class="{$cls}">
    <td style="width:80px;font-weight:600">{$level}</td>
    <td>{$msg}</td>
    <td class="n-origin">{$origin}</td>
    <td style="color:#3f3f46;font-size:10px">{$ctx}</td>
</tr>
HTML;
        }

        return <<<HTML
<table>
    <thead>
        <tr><th>Level</th><th>Message</th><th>Origin</th><th>Context</th></tr>
    </thead>
    <tbody>{$rows}</tbody>
</table>
HTML;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function badgeColor(array $entries): string
    {
        $map = [
            'debug' => 0,
            'info' => 1,
            'notice' => 1,
            'warning' => 2,
            'error' => 3,
            'critical' => 3,
            'alert' => 3,
            'emergency' => 3,
        ];

        $worst = 0;
        foreach ($entries as $e) {
            $worst = max($worst, $map[strtolower($e['level'])] ?? 0);
        }

        return match ($worst) {
            3 => '#f87171',
            2 => '#fbbf24',
            default => '#a1a1aa',
        };
    }
}