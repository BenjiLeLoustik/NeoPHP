<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Tests;

use Neo\Core\Event\Event\ResponseEvent;
use Neo\Core\Http\Response\JsonResponse;
use Neo\Core\Http\Response\RedirectResponse;
use Neo\Core\Http\Response\Response;
use Neo\Core\Profiler\Profiler;
use Neo\Core\Profiler\ProfilerResponseListener;
use Neo\Core\Profiler\Tests\Fixture\Collectors\FakeCollector;
use Neo\Core\Profiler\Toolbar\Toolbar;
use PHPUnit\Framework\TestCase;

class ProfilerResponseListenerTest extends TestCase
{
    private ProfilerResponseListener $listener;

    protected function setUp(): void
    {
        Profiler::reset();

        $profiler = Profiler::getInstance();
        $profiler->addCollector(new FakeCollector());

        $this->listener = new ProfilerResponseListener(new Toolbar($profiler));
    }

    protected function tearDown(): void
    {
        Profiler::reset();
    }

    public function testSkipsRedirectResponse(): void
    {
        $response = new RedirectResponse('/login');
        $event = new ResponseEvent($response);

        $this->listener->onResponse($event);

        self::assertSame('', $event->getResponse()->getContent());
    }

    /**
     * @throws \JsonException
     */
    public function testSkipsJsonResponse(): void
    {
        $response = new JsonResponse(['ok' => true]);
        $event = new ResponseEvent($response);

        $this->listener->onResponse($event);

        self::assertSame(json_encode(['ok' => true]), $event->getResponse()->getContent());
    }

    public function testSkipsNonHtmlContentType(): void
    {
        $response = new Response();
        $response->setHeader('Content-Type', 'text/plain');
        $response->setContent('hello');

        $event = new ResponseEvent($response);

        $this->listener->onResponse($event);

        self::assertSame('hello', $event->getResponse()->getContent());
    }

    public function testInjectsToolbarBeforeClosingBodyTag(): void
    {
        $response = new Response();
        $response->setHeader('Content-Type', 'text/html');
        $response->setContent('<html><body><h1>Hello</h1></body></html>');

        $event = new ResponseEvent($response);

        $this->listener->onResponse($event);

        $content = $event->getResponse()->getContent();

        self::assertStringContainsString('n-tab-fake', $content);
        self::assertStringEndsWith('</body></html>', $content);
    }

    public function testAppendsToolbarWhenNoBodyTagPresent(): void
    {
        $response = new Response();
        $response->setHeader('Content-Type', 'text/html');
        $response->setContent('<h1>Hello</h1>');

        $event = new ResponseEvent($response);

        $this->listener->onResponse($event);

        $content = $event->getResponse()->getContent();

        self::assertStringStartsWith('<h1>Hello</h1>', $content);
        self::assertStringContainsString('n-tab-fake', $content);
    }
}