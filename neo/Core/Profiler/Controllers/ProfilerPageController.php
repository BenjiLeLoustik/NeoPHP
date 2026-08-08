<?php

declare(strict_types=1);

namespace Neo\Core\Profiler\Controllers;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;

#[MainRoute(path: '/_profiler', name: 'profiler')]
final class ProfilerPageController extends AbstractController
{
    #[Route(path: '/{token}', name: 'show', methods: ['GET'])]
    public function show(string $token): Response
    {
        $path = $this->container->get('storagePath') . "/var/cache/profiler/{$token}.json";

        if (!file_exists($path)) {
            return $this->make()->setStatusCode(404)->setContent($this->renderTemplate(
                __DIR__ . '/../Templates/profiler-not-found.template.php',
                ['token' => $token]
            ));
        }

        $data = json_decode((string)file_get_contents($path), true);

        $statusCode = (int)($data['status_code'] ?? 200);
        $statusMeta = $this->statusMeta($statusCode);

        [$navHtml, $sectionsHtml] = $this->buildNavAndSections($data['collectors'] ?? []);

        $html = $this->renderTemplate(__DIR__ . '/../Templates/profiler.template.php', [
            'token' => $token,
            'method' => $data['method'] ?? 'GET',
            'path' => $data['path'] ?? '/',
            'ip' => (string)($data['ip'] ?? '—'),
            'timestamp' => date('M j, Y \a\t H:i:s', $data['timestamp'] ?? time()),
            'statusCode' => $statusCode,
            'statusLabel' => $statusMeta['label'],
            'statusSolid' => $statusMeta['solid'],
            'statusGradient' => $statusMeta['gradient'],
            'duration' => $data['duration'] ?? 0,
            'memory' => round(($data['memory'] ?? 0) / 1024 / 1024, 2),
            'navHtml' => $navHtml,
            'sectionsHtml' => $sectionsHtml,
        ]);

        return $this->make()->setContent($html);
    }

    /**
     * @param array<string, array{in_profiler: bool, profiler: array|null}> $collectors
     * @return array{0: string, 1: string}
     */
    private function buildNavAndSections(array $collectors): array
    {
        $navHtml = '';
        $sectionsHtml = '';
        $first = true;

        foreach ($collectors as $key => $info) {
            if (!($info['in_profiler'] ?? false) || $info['profiler'] === null) {
                continue;
            }

            $item = $info['profiler'];

            $navHtml .= $this->renderTemplate(__DIR__ . '/../Templates/profiler-nav-item.template.php', [
                'key' => $key,
                'item' => $item,
                'active' => $first,
            ]);

            $sectionsHtml .= $this->renderTemplate(__DIR__ . '/../Templates/profiler-item.template.php', [
                'key' => $key,
                'item' => $item,
                'active' => $first,
            ]);

            $first = false;
        }

        return [$navHtml, $sectionsHtml];
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