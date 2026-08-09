<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Toolbar;

use Neo\Core\Profiler\ProfilerManager;

final class Toolbar
{
    private const string DEFAULT_BADGE_COLOR = '#e2e8f0';

    public function __construct(private readonly ProfilerManager $profiler)
    {
    }

    public function render(?int $statusCode = null): string
    {
        $collectors = $this->profiler->getCollectors();
        $token = ProfilerManager::getToken();
        $duration = $this->profiler->getTotalDuration();
        $memory = $this->formatBytes($this->profiler->getPeakMemory());

        $chipsHtml = '';

        foreach ($collectors as $collector) {
            if (!$collector->inToolbar()) {
                continue;
            }

            $item = $collector->toolbarData();
            $item['badgeColor'] = $this->resolveBadgeColor($item, $statusCode);

            $chipsHtml .= $this->renderTemplate(
                __DIR__ . '/../Templates/toolbar-item.template.php',
                ['item' => $item]
            );
        }

        return $this->renderTemplate(
            __DIR__ . '/../Templates/toolbar.template.php',
            [
                'chipsHtml' => $chipsHtml,
                'duration' => $duration,
                'memory' => $memory,
                'token' => $token,
            ]
        );
    }

    /**
     * @param array{label: string, value: string, badge: string|null, badgeStatus?: bool} $item
     */
    private function resolveBadgeColor(array $item, ?int $statusCode): string
    {
        $isStatusBadge = $item['badgeStatus'] ?? false;

        if (!$isStatusBadge || $statusCode === null) {
            return self::DEFAULT_BADGE_COLOR;
        }

        return match (true) {
            $statusCode >= 500 => '#dc2626',
            $statusCode >= 400 => '#ea580c',
            $statusCode >= 300 => '#2563eb',
            default => '#059669',
        };
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function renderTemplate(string $__templatePath, array $__vars): string
    {
        extract($__vars);
        ob_start();
        include $__templatePath;
        return (string) ob_get_clean();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1) . ' MB';
        }

        return round($bytes / 1024, 1) . ' KB';
    }
}