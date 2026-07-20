<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Listener;

use Neo\Core\Event\Event\ResponseEvent;
use Neo\Core\Event\Interface\EventSubscriberInterface;
use Neo\Core\Http\Response\Types\JsonResponse;
use Neo\Core\Http\Response\Types\RedirectResponse;
use Neo\Core\Profiler\Toolbar\Toolbar;

class ProfilerResponseListener implements EventSubscriberInterface
{
    public function __construct(private readonly Toolbar $toolbar) {}

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

        $content = $response->getContent();

        $toolbar = $this->toolbar->render();
        if (str_contains($content, '</body>')) {
            $content = str_replace('</body>', $toolbar . '</body>', $content);
        } else {
            $content .= $toolbar;
        }

        $response->setContent($content);
        $event->setResponse($response);
    }
}