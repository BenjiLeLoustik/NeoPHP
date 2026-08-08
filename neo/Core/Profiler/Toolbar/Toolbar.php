<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Toolbar;

use Neo\Core\Profiler\ProfilerManager;

final class Toolbar
{
    public function __construct(private readonly ProfilerManager $profiler)
    {
    }

    public function render(): string
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

            $chipsHtml .= $this->renderTemplate(
                __DIR__ . '/../Templates/toolbar-item.template.php',
                ['item' => $collector->toolbarData()]
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