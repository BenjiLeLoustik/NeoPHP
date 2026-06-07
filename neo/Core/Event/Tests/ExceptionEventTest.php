<?php
declare(strict_types=1);

namespace Neo\Core\Event\Tests;

use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Event\Event\ExceptionEvent;
use PHPUnit\Framework\TestCase;

class ExceptionEventTest extends TestCase
{
    private function makeException(string $title = 'Test', int $code = 500): FrameworkException
    {
        return new FrameworkException($title, 'message', $code);
    }

    public function testGetExceptionReturnsConstructorException(): void
    {
        $exception = $this->makeException();
        $event = new ExceptionEvent($exception);

        $this->assertSame($exception, $event->getException());
    }

    public function testIsHandledReturnsFalseByDefault(): void
    {
        $event = new ExceptionEvent($this->makeException());

        $this->assertFalse($event->isHandled());
    }

    public function testSetHandledTrue(): void
    {
        $event = new ExceptionEvent($this->makeException());
        $event->setHandled(true);

        $this->assertTrue($event->isHandled());
    }

    public function testSetHandledFalseAfterTrue(): void
    {
        $event = new ExceptionEvent($this->makeException());
        $event->setHandled(true);
        $event->setHandled(false);

        $this->assertFalse($event->isHandled());
    }

    public function testSetExceptionReplacesException(): void
    {
        $original = $this->makeException('Original');
        $replacement = $this->makeException('Replacement');

        $event = new ExceptionEvent($original);
        $event->setException($replacement);

        $this->assertSame($replacement, $event->getException());
    }

    public function testImplementsEventInterface(): void
    {
        $event = new ExceptionEvent($this->makeException());

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testStopPropagation(): void
    {
        $event = new ExceptionEvent($this->makeException());
        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }
}