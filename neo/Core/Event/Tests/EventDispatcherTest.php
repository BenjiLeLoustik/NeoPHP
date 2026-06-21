<?php
declare(strict_types=1);

namespace Neo\Core\Event\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Event\EventDispatcher;
use Neo\Core\Event\Tests\Fixture\ConcreteEvent;
use Neo\Core\Event\Tests\Fixture\ConcreteListener;
use Neo\Core\Event\Tests\Fixture\StoppingListener;
use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $container = $this->createMock(Container::class);

        $container->method('get')->willReturnMap([
            ['storagePath', sys_get_temp_dir()],
            ['listenersPath', sys_get_temp_dir() . '/__no_listeners__'],
        ]);

        $this->dispatcher = new EventDispatcher($container);
    }

    public function testDispatchReturnsTheSameEvent(): void
    {
        $event = new ConcreteEvent();

        $result = $this->dispatcher->dispatch($event);

        self::assertSame($event, $result);
    }

    public function testAddListenerInstanceAndDispatch(): void
    {
        $listener = new ConcreteListener();
        $this->dispatcher->addListenerInstance(ConcreteEvent::class, $listener);

        $this->dispatcher->dispatch(new ConcreteEvent());

        self::assertTrue($listener->called);
    }

    public function testDispatchStopsPropagationWhenListenerCallsStop(): void
    {
        $stopping = new StoppingListener();
        $after    = new ConcreteListener();

        $this->dispatcher->addListenerInstance(ConcreteEvent::class, $stopping, priority: 10);
        $this->dispatcher->addListenerInstance(ConcreteEvent::class, $after, priority: 0);

        $this->dispatcher->dispatch(new ConcreteEvent());

        self::assertFalse($after->called);
    }

    public function testListenersAreSortedByPriorityDescending(): void
    {
        $this->dispatcher->addListenerInstance(ConcreteEvent::class, new ConcreteListener(), priority: 1);
        $this->dispatcher->addListenerInstance(ConcreteEvent::class, new ConcreteListener(), priority: 10);
        $this->dispatcher->addListenerInstance(ConcreteEvent::class, new ConcreteListener(), priority: 5);

        $listeners = $this->dispatcher->getListeners(ConcreteEvent::class);
        $priorities = array_column($listeners, 'priority');

        self::assertSame([10, 5, 1], $priorities);
    }

    public function testGetListenersReturnsEmptyForUnknownEvent(): void
    {
        self::assertSame([], $this->dispatcher->getListeners(ConcreteEvent::class));
    }

    public function testGetListenersWithoutArgumentReturnsAll(): void
    {
        $this->dispatcher->addListenerInstance(ConcreteEvent::class, new ConcreteListener());

        self::assertArrayHasKey(ConcreteEvent::class, $this->dispatcher->getListeners());
    }
}