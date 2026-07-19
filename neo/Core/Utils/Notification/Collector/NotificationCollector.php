<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Notification;

use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Utils\Notification\Enum\NotificationEnum;

class NotificationCollector implements CollectorInterface
{
    /**
     * @var array<int, array{
     *     channel: string,
     *     template: string,
     *     status: string,
     *     duration_ms: float,
     *     error: string|null,
     * }>
     */
    private array $entries = [];

    public function getName(): string
    {
        return 'mail';
    }

    public function record(
        string $channelClass,
        string $template,
        NotificationEnum $status,
        float $durationMs,
        ?string $error = null,
    ): void {
        $this->entries[] = [
            'channel' => class_basename($channelClass),
            'template' => $template,
            'status' => $status->value,
            'duration_ms' => round($durationMs, 2),
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $sent = array_filter($this->entries, fn($e) => $e['status'] === NotificationEnum::SUCCESS->value);
        $failed = array_filter($this->entries, fn($e) => $e['status'] === NotificationEnum::FAILED->value);
        $total = array_sum(array_column($this->entries, 'duration_ms'));

        $mails = array_map(fn($e) => [
            'to' => $e['channel'],
            'subject' => $e['template'],
            'status' => $e['status'] === NotificationEnum::SUCCESS->value ? 'sent' : $e['status'],
            'duration_ms' => $e['duration_ms'],
            'error' => $e['error'],
        ], $this->entries);

        return [
            'count' => count($this->entries),
            'sent' => count($sent),
            'failed' => count($failed),
            'total_ms' => round($total, 2),
            'mails' => array_values($mails),
        ];
    }

    public function renderTab(array $data): string
    {
        $failed = $data['failed'] ?? 0;
        $count = $data['count'] ?? 0;

        $color = $failed > 0 ? '#f87171' : ($count > 0 ? '#4ade80' : '#71717a');

        return <<<HTML
<div class="n-tab" onclick="neoSwitch('mail')" title="Mails">
    <span class="n-label">Mail</span>
    <span class="n-value" style="color:{$color}">{$count}</span>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPanel(array $data): string
    {
        if (($data['count'] ?? 0) === 0) {
            return '<p class="n-empty">Aucun mail envoyé.</p>';
        }

        $sent = $data['sent'] ?? 0;
        $failed = $data['failed'] ?? 0;
        $totalMs = $data['total_ms'] ?? 0;
        $failColor = $failed > 0 ? '#f87171' : '#4ade80';

        $rows = '';
        foreach (($data['mails'] ?? []) as $i => $m) {
            $n = $i + 1;
            $to = htmlspecialchars($m['to']);
            $subject = htmlspecialchars($m['subject']);
            $status = htmlspecialchars($m['status']);
            $ms = htmlspecialchars((string) $m['duration_ms']);
            $error = isset($m['error']) ? htmlspecialchars($m['error']) : '—';
            $statusColor = $m['status'] === 'sent' ? '#4ade80' : '#f87171';

            $rows .= <<<HTML
<tr>
    <td style="color:#52525b;width:28px">{$n}</td>
    <td class="n-event">{$to}</td>
    <td>{$subject}</td>
    <td style="color:{$statusColor};font-weight:600">{$status}</td>
    <td class="n-origin">{$error}</td>
    <td class="n-ms">{$ms} ms</td>
</tr>
HTML;
        }

        return <<<HTML
<dl class="n-kv" style="margin-bottom:12px">
    <dt>Total</dt><dd>{$data['count']}</dd>
    <dt>Envoyés</dt><dd style="color:#4ade80">{$sent}</dd>
    <dt>Échoués</dt><dd style="color:{$failColor}">{$failed}</dd>
    <dt>Durée totale</dt><dd>{$totalMs} ms</dd>
</dl>

<table>
    <thead>
        <tr><th>#</th><th>To</th><th>Subject</th><th>Status</th><th>Error</th><th style="text-align:right">Time</th></tr>
    </thead>
    <tbody>{$rows}</tbody>
</table>
HTML;
    }
}

if (!function_exists('class_basename')) {
    function class_basename(string $class): string
    {
        return basename(str_replace('\\', '/', $class));
    }
}