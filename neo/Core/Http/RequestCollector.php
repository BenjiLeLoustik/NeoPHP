<?php
declare(strict_types=1);

namespace Neo\Core\Http;

use Neo\Core\Http\Request\Request;
use Neo\Core\Profiler\Interface\CollectorInterface;

class RequestCollector implements CollectorInterface
{
    public function __construct(private readonly Request $request) {}

    public function getName(): string
    {
        return 'request';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return [
            'method' => $this->request->getMethod(),
            'path' => $this->request->getPath(),
            'query' => $this->request->allQuery(),
            'body' => $this->request->allBody(),
            'headers' => $this->request->headers(),
            'ip' => $this->request->getIp(),
            'user_agent' => $this->request->getUserAgent(),
        ];
    }

    public function renderTab(array $data): string
    {
        $method = htmlspecialchars($data['method'] ?? 'GET');
        $path = htmlspecialchars($data['path'] ?? '/');
        $statusCode = http_response_code() ?: 200;

        $statusColor = match(true) {
            $statusCode >= 500 => '#f87171',
            $statusCode >= 400 => '#fb923c',
            $statusCode >= 300 => '#60a5fa',
            default => '#4ade80',
        };

        return <<<HTML
<div class="n-tab n-status-chip" onclick="neoSwitch('request')" title="Requête HTTP">
    <span class="n-method">{$method}</span>
    <span class="n-status" style="color:{$statusColor}">{$statusCode}</span>
    <span class="n-path">{$path}</span>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPanel(array $data): string
    {
        $method = htmlspecialchars($data['method'] ?? '—');
        $path = htmlspecialchars($data['path'] ?? '—');
        $ip = htmlspecialchars($data['ip'] ?? '—');
        $ua = htmlspecialchars(substr($data['user_agent'] ?? '—', 0, 120));

        $headersRows = '';
        foreach (($data['headers'] ?? []) as $k => $v) {
            $headersRows .= '<dt>' . htmlspecialchars($k) . '</dt>'
                . '<dd>' . htmlspecialchars((string) $v) . '</dd>';
        }

        $headersBlock = $headersRows
            ? "<p class=\"n-section-title\">Headers</p><dl class=\"n-kv\">{$headersRows}</dl>"
            : '';

        $queryRows = '';
        foreach (($data['query'] ?? []) as $k => $v) {
            $queryRows .= '<dt>' . htmlspecialchars($k) . '</dt>'
                . '<dd>' . htmlspecialchars((string) $v) . '</dd>';
        }

        $queryBlock = $queryRows
            ? "<p class=\"n-section-title\">Query params</p><dl class=\"n-kv\">{$queryRows}</dl>"
            : '';

        $bodyRows = '';
        foreach (($data['body'] ?? []) as $k => $v) {
            $bodyRows .= '<dt>' . htmlspecialchars($k) . '</dt>'
                . '<dd>' . htmlspecialchars(is_array($v)
                    ? json_encode($v, JSON_UNESCAPED_UNICODE)
                    : (string) $v
                ) . '</dd>';
        }

        $bodyBlock = $bodyRows
            ? "<p class=\"n-section-title\">Body</p><dl class=\"n-kv\">{$bodyRows}</dl>"
            : '';

        return <<<HTML
<dl class="n-kv">
    <dt>Method</dt><dd>{$method}</dd>
    <dt>Path</dt><dd>{$path}</dd>
    <dt>IP</dt><dd>{$ip}</dd>
    <dt>User-Agent</dt><dd>{$ua}</dd>
</dl>
{$queryBlock}
{$bodyBlock}
{$headersBlock}
HTML;
    }
}