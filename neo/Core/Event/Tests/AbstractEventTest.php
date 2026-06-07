<?php
declare(strict_types=1);

namespace Neo\Core\Event\Tests;

use Neo\Core\Event\AbstractEvent;
use PHPUnit\Framework\TestCase;

final class ConcreteEvent extends AbstractEvent {}

class AbstractEventTest extends TestCase
{
    public function testIsPropagationStoppedReturnsFalseByDefault(): void
    {
        $event = new ConcreteEvent();

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testStopPropagationSetsFlag(): void
    {
        $event = new ConcreteEvent();
        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }

    public function testStopPropagationIsIdempotent(): void
    {
        $event = new ConcreteEvent();
        $event->stopPropagation();
        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }
}