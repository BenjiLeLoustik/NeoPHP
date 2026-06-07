<?php
declare(strict_types=1);

namespace Neo\Core\Event\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Event\AbstractEvent;
use Neo\Core\Event\Contract\EventInterface;
use Neo\Core\Event\Contract\EventSubscriberInterface;
use Neo\Core\Event\EventDispatcher;
use Neo\Core\Event\Exception\EventException;
use PHPUnit\Framework\TestCase;

final class TestEvent extends AbstractEvent {}

final class AnotherEvent extends AbstractEvent {}

final class SpyListener
{
    public bool $called = false;
    public ?EventInterface $receivedEvent = null;

    public function handle(EventInterface $event): void
    {
        $this->called = true;
        $this->receivedEvent = $event;
    }
}

final class StopPropagationListener
{
    public function handle(EventInterface $event): void
    {
        $event->stopPropagation();
    }
}

final class OrderRecorderListener
{
    public static array $order = [];
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function handle(EventInterface $event): void
    {
        self::$order[] = $this->name;
    }
}

final class TestSubscriber implements EventSubscriberInterface
{
    public bool $called = false;

    public static function getSubscribedEvents(): array
    {
        return [TestEvent::class => 'onTest'];
    }

    public function onTest(EventInterface $event): void
    {
        $this->called = true;
    }
}

class EventDispatcherTest extends TestCase
{
    private function makeDispatcher(): EventDispatcher
    {
        $container = new Container();
        $container->set('storagePath', sys_get_temp_dir());
        $container->set('listenersPath', sys_get_temp_dir() . '/nonexistent_listeners_' . uniqid('', false));
        return new EventDispatcher($container);
    }

    public function testDispatchReturnsTheSameEvent(): void
    {
        $dispatcher = $this->makeDispatcher();
        $event = new TestEvent();

        $returned = $dispatcher->dispatch($event);

        $this->assertSame($event, $returned);
    }

    public function testDispatchWithNoListenersDoesNothing(): void
    {
        $dispatcher = $this->makeDispatcher();
        $event = new TestEvent();

        $dispatcher->dispatch($event);

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testAddListenerInstanceCallsHandleOnDispatch(): void
    {
        $dispatcher = $this->makeDispatcher();
        $listener = new SpyListener();

        $dispatcher->addListenerInstance(TestEvent::class, $listener);

        $event = new TestEvent();
        $dispatcher->dispatch($event);

        $this->assertTrue($listener->called);
        $this->assertSame($event, $listener->receivedEvent);
    }

    public function testAddListenerInstanceWithCustomMethod(): void
    {
        $dispatcher = $this->makeDispatcher();
        $listener = new SpyListener();

        $dispatcher->addListenerInstance(TestEvent::class, $listener, 'handle');

        $event = new TestEvent();
        $dispatcher->dispatch($event);

        $this->assertTrue($listener->called);
    }

    public function testAddListenerByClassResolvesFromContainer(): void
    {
        $container = new Container();
        $container->set('storagePath', sys_get_temp_dir());
        $container->set('listenersPath', sys_get_temp_dir() . '/nonexistent_' . uniqid('', false));

        $listener = new SpyListener();
        $container->instance(SpyListener::class, $listener);

        $dispatcher = new EventDispatcher($container);
        $dispatcher->addListener(TestEvent::class, SpyListener::class);

        $dispatcher->dispatch(new TestEvent());

        $this->assertTrue($listener->called);
    }

    public function testStopPropagationPreventsSubsequentListeners(): void
    {
        $dispatcher = $this->makeDispatcher();
        $stopper = new StopPropagationListener();
        $spy = new SpyListener();

        $dispatcher->addListenerInstance(TestEvent::class, $stopper, 'handle', 10);
        $dispatcher->addListenerInstance(TestEvent::class, $spy, 'handle', 0);

        $dispatcher->dispatch(new TestEvent());

        $this->assertFalse($spy->called);
    }

    public function testPropagationStoppedFlagIsSetOnEvent(): void
    {
        $dispatcher = $this->makeDispatcher();
        $dispatcher->addListenerInstance(TestEvent::class, new StopPropagationListener());

        $event = new TestEvent();
        $dispatcher->dispatch($event);

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testListenersAreCalledByPriorityDescending(): void
    {
        OrderRecorderListener::$order = [];

        $dispatcher = $this->makeDispatcher();
        $low = new OrderRecorderListener('low');
        $high = new OrderRecorderListener('high');
        $mid = new OrderRecorderListener('mid');

        $dispatcher->addListenerInstance(TestEvent::class, $low, 'handle', 0);
        $dispatcher->addListenerInstance(TestEvent::class, $high, 'handle', 20);
        $dispatcher->addListenerInstance(TestEvent::class, $mid, 'handle', 10);

        $dispatcher->dispatch(new TestEvent());

        $this->assertSame(['high', 'mid', 'low'], OrderRecorderListener::$order);
    }

    public function testAddSubscriberRegistersListeners(): void
    {
        $dispatcher = $this->makeDispatcher();
        $subscriber = new TestSubscriber();

        $dispatcher->addSubscriber($subscriber);

        $listeners = $dispatcher->getListeners(TestEvent::class);
        $this->assertCount(1, $listeners);
    }

    public function testAddSubscriberCallsCorrectMethod(): void
    {
        $container = new Container();
        $container->set('storagePath', sys_get_temp_dir());
        $container->set('listenersPath', sys_get_temp_dir() . '/nonexistent_' . uniqid('', false));

        $subscriber = new TestSubscriber();
        $container->instance(TestSubscriber::class, $subscriber);

        $dispatcher = new EventDispatcher($container);
        $dispatcher->addSubscriber($subscriber);

        $dispatcher->dispatch(new TestEvent());

        $this->assertTrue($subscriber->called);
    }

    public function testGetListenersReturnsAllWhenNoFilterGiven(): void
    {
        $dispatcher = $this->makeDispatcher();
        $dispatcher->addListenerInstance(TestEvent::class, new SpyListener());
        $dispatcher->addListenerInstance(AnotherEvent::class, new SpyListener());

        $all = $dispatcher->getListeners();

        $this->assertArrayHasKey(TestEvent::class, $all);
        $this->assertArrayHasKey(AnotherEvent::class, $all);
    }

    public function testGetListenersFiltersByEventClass(): void
    {
        $dispatcher = $this->makeDispatcher();
        $dispatcher->addListenerInstance(TestEvent::class, new SpyListener());
        $dispatcher->addListenerInstance(AnotherEvent::class, new SpyListener());

        $filtered = $dispatcher->getListeners(TestEvent::class);

        $this->assertCount(1, $filtered);
    }

    public function testGetListenersReturnsEmptyArrayForUnknownEvent(): void
    {
        $dispatcher = $this->makeDispatcher();

        $this->assertSame([], $dispatcher->getListeners(AnotherEvent::class));
    }

    public function testDispatchThrowsWhenListenerMethodDoesNotExist(): void
    {
        $dispatcher = $this->makeDispatcher();
        $listener = new SpyListener();

        $dispatcher->addListenerInstance(TestEvent::class, $listener, 'nonExistentMethod');

        $this->expectException(EventException::class);
        $dispatcher->dispatch(new TestEvent());
    }

    public function testMultipleListenersOnSameEventAreAllCalled(): void
    {
        $dispatcher = $this->makeDispatcher();
        $spy1 = new SpyListener();
        $spy2 = new SpyListener();

        $dispatcher->addListenerInstance(TestEvent::class, $spy1);
        $dispatcher->addListenerInstance(TestEvent::class, $spy2);

        $dispatcher->dispatch(new TestEvent());

        $this->assertTrue($spy1->called);
        $this->assertTrue($spy2->called);
    }

    public function testListenerOnDifferentEventIsNotCalled(): void
    {
        $dispatcher = $this->makeDispatcher();
        $spy = new SpyListener();

        $dispatcher->addListenerInstance(AnotherEvent::class, $spy);

        $dispatcher->dispatch(new TestEvent());

        $this->assertFalse($spy->called);
    }

    public function testAddListenerAddsToGetListeners(): void
    {
        $dispatcher = $this->makeDispatcher();
        $dispatcher->addListener(TestEvent::class, SpyListener::class, 5);

        $listeners = $dispatcher->getListeners(TestEvent::class);

        $this->assertCount(1, $listeners);
        $this->assertSame(SpyListener::class, $listeners[0]['class']);
        $this->assertSame(5, $listeners[0]['priority']);
    }

    public function testDispatchCanBeCalledMultipleTimes(): void
    {
        $dispatcher = $this->makeDispatcher();
        $spy = new SpyListener();
        $dispatcher->addListenerInstance(TestEvent::class, $spy);

        $dispatcher->dispatch(new TestEvent());
        $dispatcher->dispatch(new TestEvent());

        $this->assertTrue($spy->called);
    }
}