<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Listener;

use Neo\Core\Event\Event\ResponseEvent;
use Neo\Core\Event\Interface\EventSubscriberInterface;
use Neo\Core\Http\Response\Types\JsonResponse;
use Neo\Core\Http\Response\Types\RedirectResponse;
use Neo\Core\Profiler\Interface\StatusAwareCollectorInterface;
use Neo\Core\Profiler\ProfilerManager;
use Neo\Core\Profiler\Toolbar\Toolbar;

final class ProfilerResponseListener implements EventSubscriberInterface
{
    private const int CLEANUP_CHANCE = 20;

    private const int MAX_PROFILES = 100;

    private const int MAX_AGE_SECONDS = 86400;

    public function __construct(
        private readonly Toolbar $toolbar,
        private readonly ProfilerManager $profiler,
        private readonly string $storageDir,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ResponseEvent::class => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if ($response instanceof RedirectResponse || $response instanceof JsonResponse) {
            return;
        }

        $contentType = $response->getHeaders()['Content-Type'] ?? 'text/html';

        if (!str_contains($contentType, 'text/html')) {
            return;
        }

        $path = $_SERVER['REQUEST_URI'] ?? '/';

        if (str_starts_with($path, '/_profiler')) {
            return;
        }

        $statusCode = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null;

        foreach ($this->profiler->getCollectors() as $collector) {
            if ($collector instanceof StatusAwareCollectorInterface) {
                $collector->setStatusCode($statusCode);
            }
        }

        $this->saveProfile($statusCode, $path);

        $content = $response->getContent();

        $toolbarHtml = $this->toolbar->render($statusCode);

        $content = str_contains($content, '</body>')
            ? str_replace('</body>', $toolbarHtml . '</body>', $content)
            : $content . $toolbarHtml;

        $response->setContent($content);
        $event->setResponse($response);
    }

    private function saveProfile(?int $statusCode, string $path): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '—';

        $scheme = ($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $fullUrl = $scheme . '://' . $host . $path;

        $data = $this->profiler->export($statusCode, $method, $fullUrl, $ip);

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0777, true);
        }

        file_put_contents(
            $this->storageDir . '/' . $data['token'] . '.json',
            json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        );

        if (random_int(1, self::CLEANUP_CHANCE) === 1) {
            $this->cleanup();
        }
    }

    private function cleanup(): void
    {
        $files = glob($this->storageDir . '/*.json');

        if ($files === false || $files === []) {
            return;
        }

        $now = time();
        $kept = [];

        foreach ($files as $file) {
            $age = $now - (filemtime($file) ?: 0);

            if ($age > self::MAX_AGE_SECONDS) {
                unlink($file);
                continue;
            }

            $kept[] = $file;
        }

        if (count($kept) <= self::MAX_PROFILES) {
            return;
        }

        usort($kept, static fn (string $a, string $b) => filemtime($a) <=> filemtime($b));

        $toRemove = count($kept) - self::MAX_PROFILES;

        for ($i = 0; $i < $toRemove; $i++) {
            unlink($kept[$i]);
        }
    }
}