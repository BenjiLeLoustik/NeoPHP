<?php

declare(strict_types=1);

namespace Neo\Core\Profiler;

final class ProfilerHtmlRenderer
{
    public function render(array $data, string $token): string
    {
        $statusCode = (int) ($data['status_code'] ?? 200);
        $statusMeta = $this->statusMeta($statusCode);

        $routeMetrics = [
            ['label' => 'Duration', 'value' => (string) ($data['duration'] ?? 0), 'unit' => 'ms'],
            ['label' => 'Peak memory', 'value' => (string) round(($data['memory'] ?? 0) / 1024 / 1024, 2), 'unit' => 'MB'],
        ];

        [$navHtml, $sectionsHtml] = $this->buildNavAndSections($data['collectors'] ?? [], $routeMetrics);

        return $this->renderTemplate($this->templatesDir() . '/profiler.template.php', [
            'token' => $token,
            'method' => $data['method'] ?? 'GET',
            'path' => $data['path'] ?? '/',
            'ip' => (string) ($data['ip'] ?? '—'),
            'timestamp' => date('M j, Y \a\t H:i:s', $data['timestamp'] ?? time()),
            'statusCode' => $statusCode,
            'statusLabel' => $statusMeta['label'],
            'statusSolid' => $statusMeta['solid'],
            'statusGradient' => $statusMeta['gradient'],
            'navHtml' => $navHtml,
            'sectionsHtml' => $sectionsHtml,
        ]);
    }

    public function renderNotFound(string $token): string
    {
        return $this->renderTemplate($this->templatesDir() . '/profiler-not-found.template.php', ['token' => $token]);
    }

    public function templatesDir(): string
    {
        return __DIR__ . '/Templates';
    }

    /**
     * @param array<string, array{package: string|null, in_profiler: bool, profiler: array|null}> $collectors
     * @param list<array{label: string, value: string, unit?: string}> $routeMetrics
     * @return array{0: string, 1: string}
     */
    private function buildNavAndSections(array $collectors, array $routeMetrics): array
    {
        $navHtml = '';
        $sectionsHtml = '';
        $first = true;

        $coreEntries = [];
        $packageEntries = [];
        $packagesOwnInfo = null;

        foreach ($collectors as $key => $info) {
            if (!($info['in_profiler'] ?? false) || $info['profiler'] === null) {
                continue;
            }

            if ($key === 'packages') {
                $packagesOwnInfo = $info;
                continue;
            }

            $package = $info['package'] ?? null;

            if ($package !== null) {
                $packageEntries[$package][$key] = $info;
                continue;
            }

            $coreEntries[$key] = $info;
        }

        foreach ($coreEntries as $key => $info) {
            $item = $this->prepareItem($info['profiler'], $key === 'route' ? $routeMetrics : [], null);

            $navHtml .= $this->renderTemplate($this->templatesDir() . '/profiler-nav-item.template.php', [
                'key' => $key,
                'item' => $item,
                'active' => $first,
            ]);

            $sectionsHtml .= $this->renderTemplate($this->templatesDir() . '/profiler-item.template.php', [
                'key' => $key,
                'item' => $item,
                'active' => $first,
            ]);

            $first = false;
        }

        if ($packagesOwnInfo !== null || $packageEntries !== []) {
            [$groupNav, $groupSections, $first] = $this->buildPackagesGroup(
                $packagesOwnInfo['profiler'] ?? null,
                $packagesOwnInfo !== null,
                $packageEntries,
                $first
            );

            $navHtml .= $groupNav;
            $sectionsHtml .= $groupSections;
        }

        return [$navHtml, $sectionsHtml];
    }

    /**
     * @param array<string, array<string, array{profiler: array|null}>> $packageEntries
     * @return array{0: string, 1: string, 2: bool}
     */
    private function buildPackagesGroup(?array $packagesItemRaw, bool $hasOwnPanel, array $packageEntries, bool $first): array
    {
        $childrenNav = '';
        $childrenSections = '';

        foreach ($packageEntries as $packageName => $entries) {
            foreach ($entries as $key => $info) {
                $item = $this->prepareItem($info['profiler'], [], $packageName);

                $childrenNav .= $this->renderTemplate($this->templatesDir() . '/profiler-nav-item.template.php', [
                    'key' => $key,
                    'item' => $item,
                    'active' => $first,
                ]);

                $childrenSections .= $this->renderTemplate($this->templatesDir() . '/profiler-item.template.php', [
                    'key' => $key,
                    'item' => $item,
                    'active' => $first,
                ]);

                $first = false;
            }
        }

        $headerHtml = null;
        $ownSectionHtml = '';

        if ($hasOwnPanel && $packagesItemRaw !== null) {
            $item = $this->prepareItem($packagesItemRaw, [], null);

            $headerHtml = $this->renderTemplate($this->templatesDir() . '/profiler-nav-item.template.php', [
                'key' => 'packages',
                'item' => $item,
                'active' => $first,
            ]);

            $ownSectionHtml = $this->renderTemplate($this->templatesDir() . '/profiler-item.template.php', [
                'key' => 'packages',
                'item' => $item,
                'active' => $first,
            ]);

            $first = false;
        }

        $navHtml = $this->renderTemplate($this->templatesDir() . '/profiler-nav-group.template.php', [
            'headerHtml' => $headerHtml,
            'childrenHtml' => $childrenNav,
            'hasChildren' => $childrenNav !== '',
        ]);

        return [$navHtml, $ownSectionHtml . $childrenSections, $first];
    }

    /**
     * @param array{title: string, badge: string|null, badgeType?: string, metrics?: array, blocks?: array} $profilerData
     * @param list<array{label: string, value: string, unit?: string}> $extraMetrics
     */
    private function prepareItem(array $profilerData, array $extraMetrics, ?string $packageName): array
    {
        $metrics = array_merge($extraMetrics, $profilerData['metrics'] ?? []);

        return [
            'title' => $packageName ?? $profilerData['title'],
            'badge' => $profilerData['badge'] ?? null,
            'badgeType' => $profilerData['badgeType'] ?? 'neutral',
            'metricsHtml' => $this->renderMetrics($metrics),
            'blocksHtml' => $this->renderBlocks($profilerData['blocks'] ?? []),
        ];
    }

    /**
     * @param list<array{label: string, value: string, unit?: string}> $metrics
     */
    private function renderMetrics(array $metrics): string
    {
        $html = '';

        foreach ($metrics as $metric) {
            $html .= $this->renderTemplate($this->templatesDir() . '/block-metric.template.php', ['metric' => $metric]);
        }

        return $html;
    }

    /**
     * @param list<array<string, mixed>> $blocks
     */
    private function renderBlocks(array $blocks): string
    {
        $html = '';

        foreach ($blocks as $block) {
            $html .= match ($block['type'] ?? null) {
                'kv' => $this->renderTemplate($this->templatesDir() . '/block-kv.template.php', ['block' => $block]),
                'table' => $this->renderTemplate($this->templatesDir() . '/block-table.template.php', ['block' => $block]),
                'log-list' => $this->renderTemplate($this->templatesDir() . '/block-log-list.template.php', ['block' => $block]),
                'tabs' => $this->renderTabsBlock($block),
                default => '',
            };
        }

        return $html;
    }

    /**
     * @param array{section?: ?string, tabs: list<array{label: string, badge?: ?string, badgeType?: string, blocks: array}>} $block
     */
    private function renderTabsBlock(array $block): string
    {
        $id = 'tabs-' . substr(md5(($block['section'] ?? '') . json_encode(array_column($block['tabs'], 'label'))), 0, 10);

        $tabs = array_map(
            fn (array $tab) => [
                'label' => $tab['label'],
                'badge' => $tab['badge'] ?? null,
                'badgeType' => $tab['badgeType'] ?? 'neutral',
                'blocksHtml' => $this->renderBlocks($tab['blocks'] ?? []),
            ],
            $block['tabs']
        );

        return $this->renderTemplate($this->templatesDir() . '/block-tabs.template.php', [
            'id' => $id,
            'block' => ['section' => $block['section'] ?? null, 'tabs' => $tabs],
        ]);
    }

    /**
     * @return array{label: string, solid: string, gradient: string}
     */
    private function statusMeta(int $statusCode): array
    {
        return match (true) {
            $statusCode >= 500 => ['label' => 'Server Error', 'solid' => '#dc2626', 'gradient' => 'linear-gradient(90deg, #b91c1c, #ef4444)'],
            $statusCode >= 400 => ['label' => 'Client Error', 'solid' => '#ea580c', 'gradient' => 'linear-gradient(90deg, #c2410c, #f97316)'],
            $statusCode >= 300 => ['label' => 'Redirect', 'solid' => '#2563eb', 'gradient' => 'linear-gradient(90deg, #1d4ed8, #3b82f6)'],
            default => ['label' => 'OK', 'solid' => '#059669', 'gradient' => 'linear-gradient(90deg, #047857, #10b981)'],
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
}