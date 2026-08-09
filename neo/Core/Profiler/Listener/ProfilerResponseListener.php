<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Listener;

use Neo\Core\Event\Event\ResponseEvent;
use Neo\Core\Event\Interface\EventSubscriberInterface;
use Neo\Core\Http\Response\Types\JsonResponse;
use Neo\Core\Http\Response\Types\RedirectResponse;
use Neo\Core\Profiler\Interface\ResponseAwareCollectorInterface;
use Neo\Core\Profiler\Interface\StatusAwareCollectorInterface;
use Neo\Core\Profiler\ProfilerCleaner;
use Neo\Core\Profiler\ProfilerManager;
use Neo\Core\Profiler\TimelineRecorder;
use Neo\Core\Profiler\Toolbar\Toolbar;

final class ProfilerResponseListener implements EventSubscriberInterface
{

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
        $onResponseStart = microtime(true);

        $response = $event->getResponse();
        $path = $_SERVER['REQUEST_URI'] ?? '/';

        if (str_starts_with($path, '/_profiler')) {
            return;
        }

        $statusCode = $response->getStatusCode();

        foreach ($this->profiler->getCollectors() as $collector) {
            if ($collector instanceof StatusAwareCollectorInterface) {
                $collector->setStatusCode($statusCode);
            }

            if ($collector instanceof \Neo\Core\Profiler\Interface\ResponseAwareCollectorInterface) {
                $collector->setResponse($response);
            }
        }

        $data = $this->buildAndSaveProfile($statusCode, $path);

        if ($response instanceof RedirectResponse || $response instanceof JsonResponse) {
            TimelineRecorder::record('response', 'ProfilerResponseListener::onResponse()', $onResponseStart);
            return;
        }

        $contentType = $response->getHeaders()['Content-Type'] ?? 'text/html';

        if (!str_contains($contentType, 'text/html')) {
            TimelineRecorder::record('response', 'ProfilerResponseListener::onResponse()', $onResponseStart);
            return;
        }

        $content = $response->getContent();
        $toolbarHtml = $this->toolbar->renderFromExport($data['collectors'], $statusCode);

        $content = str_contains($content, '</body>')
            ? str_replace('</body>', $toolbarHtml . '</body>', $content)
            : $content . $toolbarHtml;

        $response->setContent($content);
        $event->setResponse($response);

        TimelineRecorder::record('response', 'ProfilerResponseListener::onResponse()', $onResponseStart);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAndSaveProfile(?int $statusCode, string $path): array
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

        ProfilerCleaner::clean($this->storageDir);

        return $data;
    }
}