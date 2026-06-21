<?php
declare(strict_types=1);

namespace Neo\Core\Event\Tests;

use Neo\Core\Event\Tests\Fixture\ConcreteEvent;
use PHPUnit\Framework\TestCase;

final class AbstractEventTest extends TestCase
{
    public function testPropagationIsNotStoppedByDefault(): void
    {
        $event = new ConcreteEvent();

        self::assertFalse($event->isPropagationStopped());
    }

    public function testStopPropagationSetsFlag(): void
    {
        $event = new ConcreteEvent();
        $event->stopPropagation();

        self::assertTrue($event->isPropagationStopped());
    }
}