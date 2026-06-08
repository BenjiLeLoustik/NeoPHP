<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Tests;

use Neo\Core\Event\EventDispatcher;
use Neo\Core\Profiler\Collector\CollectorInterface;
use Neo\Core\Profiler\Collector\EventCollector;
use Neo\Core\Profiler\Collector\LogCollector;
use Neo\Core\Profiler\Collector\QueryCollector;
use Neo\Core\Profiler\Collector\TranslationCollector;
use Neo\Core\Profiler\Profiler;
use Neo\Core\Profiler\Toolbar\Toolbar;
use PHPUnit\Framework\TestCase;

class ProfilerTest extends TestCase
{
    protected function setUp(): void
    {
        Profiler::reset();
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $a = Profiler::getInstance();
        $b = Profiler::getInstance();

        $this->assertSame($a, $b);
    }

    public function testResetCreatesNewInstance(): void
    {
        $before = Profiler::getInstance();
        Profiler::reset();
        $after = Profiler::getInstance();

        $this->assertNotSame($before, $after);
    }

    public function testAddCollectorStoresIt(): void
    {
        $profiler = Profiler::getInstance();
        $collector = $this->makeCollector('test');
        $profiler->addCollector($collector);

        $this->assertSame($collector, $profiler->getCollector('test'));
    }

    public function testGetCollectorReturnsNullForUnknownName(): void
    {
        $this->assertNull(Profiler::getInstance()->getCollector('unknown'));
    }

    public function testGetCollectorsReturnsAll(): void
    {
        $profiler = Profiler::getInstance();
        $profiler->addCollector($this->makeCollector('a'));
        $profiler->addCollector($this->makeCollector('b'));

        $this->assertCount(2, $profiler->getCollectors());
    }

    public function testAddCollectorOverwritesSameName(): void
    {
        $profiler = Profiler::getInstance();
        $first = $this->makeCollector('dup');
        $second = $this->makeCollector('dup');

        $profiler->addCollector($first);
        $profiler->addCollector($second);

        $this->assertSame($second, $profiler->getCollector('dup'));
    }

    public function testGetStartTimeIsPositive(): void
    {
        $this->assertGreaterThan(0.0, Profiler::getInstance()->getStartTime());
    }

    public function testGetStartMemoryIsPositive(): void
    {
        $this->assertGreaterThan(0, Profiler::getInstance()->getStartMemory());
    }

    public function testGetTotalDurationIsPositive(): void
    {
        $this->assertGreaterThan(0.0, Profiler::getInstance()->getTotalDuration());
    }

    public function testGetPeakMemoryIsPositive(): void
    {
        $this->assertGreaterThan(0, Profiler::getInstance()->getPeakMemory());
    }

    public function testCollectReturnsDurationKey(): void
    {
        $data = Profiler::getInstance()->collect();

        $this->assertArrayHasKey('duration', $data);
        $this->assertGreaterThanOrEqual(0.0, $data['duration']);
    }

    public function testCollectReturnsMemoryKey(): void
    {
        $data = Profiler::getInstance()->collect();

        $this->assertArrayHasKey('memory', $data);
        $this->assertGreaterThan(0, $data['memory']);
    }

    public function testCollectIncludesCollectorData(): void
    {
        $profiler = Profiler::getInstance();
        $collector = $this->makeCollector('custom', ['foo' => 'bar']);
        $profiler->addCollector($collector);

        $data = $profiler->collect();

        $this->assertArrayHasKey('custom', $data);
        $this->assertSame(['foo' => 'bar'], $data['custom']);
    }

    public function testLogCollectorGetName(): void
    {
        $this->assertSame('logs', (new LogCollector())->getName());
    }

    public function testLogCollectorEmptyCollect(): void
    {
        $data = (new LogCollector())->collect();

        $this->assertSame(0, $data['count']);
        $this->assertSame([], $data['by_level']);
        $this->assertSame([], $data['entries']);
    }

    public function testLogCollectorRecordIncreasesCount(): void
    {
        $collector = new LogCollector();
        $collector->record('info', 'hello', [], 'App');

        $this->assertSame(1, $collector->collect()['count']);
    }

    public function testLogCollectorGroupsByLevel(): void
    {
        $collector = new LogCollector();
        $collector->record('info', 'msg1', [], 'App');
        $collector->record('info', 'msg2', [], 'App');
        $collector->record('error', 'err', [], 'App');

        $byLevel = $collector->collect()['by_level'];

        $this->assertSame(2, $byLevel['info']);
        $this->assertSame(1, $byLevel['error']);
    }

    public function testLogCollectorStoresAllFields(): void
    {
        $collector = new LogCollector();
        $collector->record('warning', 'Watch out', ['key' => 'val'], 'Module');

        $entry = $collector->collect()['entries'][0];

        $this->assertSame('warning', $entry['level']);
        $this->assertSame('Watch out', $entry['message']);
        $this->assertSame(['key' => 'val'], $entry['context']);
        $this->assertSame('Module', $entry['origin']);
        $this->assertIsFloat($entry['time']);
    }

    public function testQueryCollectorGetName(): void
    {
        $this->assertSame('database', (new QueryCollector())->getName());
    }

    public function testQueryCollectorEmptyCollect(): void
    {
        $data = (new QueryCollector())->collect();

        $this->assertSame(0, $data['count']);
        $this->assertSame(0.0, $data['total_ms']);
        $this->assertSame([], $data['queries']);
    }

    public function testQueryCollectorRecordIncreasesCount(): void
    {
        $collector = new QueryCollector();
        $collector->record('SELECT 1', [], 1.5);

        $this->assertSame(1, $collector->collect()['count']);
    }

    public function testQueryCollectorSumsDuration(): void
    {
        $collector = new QueryCollector();
        $collector->record('SELECT 1', [], 1.5);
        $collector->record('SELECT 2', [], 2.5);

        $this->assertSame(4.0, $collector->collect()['total_ms']);
    }

    public function testQueryCollectorStoresAllFields(): void
    {
        $collector = new QueryCollector();
        $collector->record('SELECT * FROM users', ['id' => 1], 3.14);

        $query = $collector->collect()['queries'][0];

        $this->assertSame('SELECT * FROM users', $query['sql']);
        $this->assertSame(['id' => 1], $query['params']);
        $this->assertSame(3.14, $query['duration']);
    }

    public function testQueryCollectorRoundsDuration(): void
    {
        $collector = new QueryCollector();
        $collector->record('SELECT 1', [], 1.23456789);

        $duration = $collector->collect()['queries'][0]['duration'];

        $this->assertSame(round(1.23456789, 3), $duration);
    }

    public function testEventCollectorGetName(): void
    {
        $dispatcher = $this->createStub(EventDispatcher::class);

        $this->assertSame('events', (new EventCollector($dispatcher))->getName());
    }

    public function testEventCollectorEmptyCollect(): void
    {
        $dispatcher = $this->createStub(EventDispatcher::class);
        $dispatcher->method('getListeners')->willReturn([]);

        $data = (new EventCollector($dispatcher))->collect();

        $this->assertSame(0, $data['count']);
        $this->assertSame([], $data['dispatched']);
    }

    public function testEventCollectorRecordIncreasesCount(): void
    {
        $dispatcher = $this->createStub(EventDispatcher::class);
        $dispatcher->method('getListeners')->willReturn([]);

        $collector = new EventCollector($dispatcher);
        $collector->record('MyEvent', ['ListenerA'], 1.0);

        $this->assertSame(1, $collector->collect()['count']);
    }

    public function testEventCollectorStoresDispatchedEvent(): void
    {
        $dispatcher = $this->createStub(EventDispatcher::class);
        $dispatcher->method('getListeners')->willReturn([]);

        $collector = new EventCollector($dispatcher);
        $collector->record('MyEvent', ['ListenerA', 'ListenerB'], 2.5);

        $dispatched = $collector->collect()['dispatched'][0];

        $this->assertSame('MyEvent', $dispatched['event']);
        $this->assertSame(['ListenerA', 'ListenerB'], $dispatched['listeners']);
        $this->assertSame(2.5, $dispatched['duration']);
    }

    public function testEventCollectorCollectsRegisteredListeners(): void
    {
        $dispatcher = $this->createStub(EventDispatcher::class);
        $dispatcher->method('getListeners')->willReturn([
            'MyEvent' => [['class' => 'ListenerA'], ['class' => 'ListenerB']],
        ]);

        $collector = new EventCollector($dispatcher);
        $registered = $collector->collect()['registered'];

        $this->assertArrayHasKey('MyEvent', $registered);
        $this->assertSame(['ListenerA', 'ListenerB'], $registered['MyEvent']);
    }

    public function testEventCollectorRoundsDuration(): void
    {
        $dispatcher = $this->createStub(EventDispatcher::class);
        $dispatcher->method('getListeners')->willReturn([]);

        $collector = new EventCollector($dispatcher);
        $collector->record('E', [], 1.23456789);

        $this->assertSame(round(1.23456789, 3), $collector->collect()['dispatched'][0]['duration']);
    }

    public function testTranslationCollectorGetName(): void
    {
        $this->assertSame('translation', $this->makeTranslationCollector()->getName());
    }

    public function testTranslationCollectorRecordHit(): void
    {
        $collector = $this->makeTranslationCollector();
        $collector->record('app.title', 'Hello', true);

        $hits = $this->readPrivate($collector, 'hits');

        $this->assertCount(1, $hits);
        $this->assertSame('app.title', $hits[0]['key']);
        $this->assertSame('Hello', $hits[0]['result']);
    }

    public function testTranslationCollectorRecordMiss(): void
    {
        $collector = $this->makeTranslationCollector();
        $collector->record('missing.key', 'missing.key', false);

        $misses = $this->readPrivate($collector, 'misses');

        $this->assertCount(1, $misses);
        $this->assertSame('missing.key', $misses[0]['key']);
    }

    public function testTranslationCollectorRecordBothHitAndMiss(): void
    {
        $collector = $this->makeTranslationCollector();
        $collector->record('found', 'Trouvé', true);
        $collector->record('lost', 'lost', false);

        $this->assertCount(1, $this->readPrivate($collector, 'hits'));
        $this->assertCount(1, $this->readPrivate($collector, 'misses'));
    }

    public function testToolbarRenderIsNotEmpty(): void
    {
        $profiler = $this->makeProfilerWithCollectors();

        $this->assertNotEmpty((new Toolbar($profiler))->render());
    }

    public function testToolbarRenderContainsNeoBar(): void
    {
        $this->assertStringContainsString('neo-bar', (new Toolbar($this->makeProfilerWithCollectors()))->render());
    }

    public function testToolbarRenderContainsNeoPanel(): void
    {
        $this->assertStringContainsString('neo-panel', (new Toolbar($this->makeProfilerWithCollectors()))->render());
    }

    public function testToolbarRenderContainsScriptTag(): void
    {
        $this->assertStringContainsString('<script>', (new Toolbar($this->makeProfilerWithCollectors()))->render());
    }

    public function testToolbarRenderContainsStyleTag(): void
    {
        $this->assertStringContainsString('<style>', (new Toolbar($this->makeProfilerWithCollectors()))->render());
    }

    public function testToolbarRenderEscapesRequestPath(): void
    {
        $profiler = Profiler::getInstance();

        $collector = $this->makeCollector('request', [
            'method' => 'GET',
            'path' => '/<script>alert(1)</script>',
            'query' => [],
            'body' => [],
            'headers' => [],
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);
        $profiler->addCollector($collector);

        $html = (new Toolbar($profiler))->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testToolbarRenderShowsQueryCount(): void
    {
        $profiler = Profiler::getInstance();
        $collector = new QueryCollector();
        $collector->record('SELECT 1', [], 1.0);
        $profiler->addCollector($collector);

        $html = (new Toolbar($profiler))->render();

        $this->assertStringContainsString('1 req', $html);
    }

    public function testToolbarRenderShowsLogCount(): void
    {
        $profiler = Profiler::getInstance();
        $collector = new LogCollector();
        $collector->record('info', 'hello', [], 'Test');
        $profiler->addCollector($collector);

        $this->assertStringContainsString('1', (new Toolbar($profiler))->render());
    }

    /** @param array<string, mixed> $data */
    private function makeCollector(string $name, array $data = []): CollectorInterface
    {
        return new class ($name, $data) implements CollectorInterface {
            public function __construct(private readonly string $name, private readonly array $data) {}
            public function getName(): string { return $this->name; }
            public function collect(): array { return $this->data; }
        };
    }

    private function makeTranslationCollector(): TranslationCollector
    {
        $ref = new \ReflectionClass(TranslationCollector::class);
        /** @var TranslationCollector $collector */
        $collector = $ref->newInstanceWithoutConstructor();
        foreach (['hits', 'misses'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setValue($collector, []);
        }
        return $collector;
    }

    /** @return mixed */
    private function readPrivate(object $obj, string $property): mixed
    {
        $ref  = new \ReflectionProperty($obj, $property);
        return $ref->getValue($obj);
    }

    private function makeProfilerWithCollectors(): Profiler
    {
        $profiler = Profiler::getInstance();

        $profiler->addCollector($this->makeCollector('request', [
            'method' => 'GET', 'path' => '/', 'query' => [], 'body' => [],
            'headers' => [], 'ip' => '127.0.0.1', 'user_agent' => 'PHPUnit',
        ]));
        $profiler->addCollector($this->makeCollector('router', [
            'route' => 'home.index', 'controller' => 'HomeController',
            'action' => 'index', 'params' => [], 'routes_count' => 1,
        ]));
        $profiler->addCollector($this->makeCollector('database', [
            'count' => 0, 'total_ms' => 0.0, 'queries' => [],
        ]));
        $profiler->addCollector($this->makeCollector('events', [
            'count' => 0, 'dispatched' => [], 'registered' => [],
        ]));
        $profiler->addCollector($this->makeCollector('logs', [
            'count' => 0, 'by_level' => [], 'entries' => [],
        ]));
        $profiler->addCollector($this->makeCollector('auth', [
            'authenticated' => false, 'guard' => null, 'user' => null,
        ]));
        $profiler->addCollector($this->makeCollector('mail', [
            'count' => 0, 'sent' => 0, 'failed' => 0, 'total_ms' => 0.0, 'mails' => [],
        ]));
        $profiler->addCollector($this->makeCollector('translation', [
            'enabled' => false, 'locale' => 'en', 'locales' => [],
            'hits_count' => 0, 'misses_count' => 0, 'hits' => [], 'misses' => [],
        ]));

        return $profiler;
    }
}