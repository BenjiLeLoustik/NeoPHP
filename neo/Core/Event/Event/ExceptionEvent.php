<?php
declare(strict_types=1);

namespace Neo\Core\Event\Event;

use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Event\Abstract\AbstractEvent;

class ExceptionEvent extends AbstractEvent
{
    private bool $handled = false;

    public function __construct(
        private FrameworkException $exception
    ) {}

    public function getException(): FrameworkException
    {
        return $this->exception;
    }

    public function setException(FrameworkException $exception): void
    {
        $this->exception = $exception;
    }

    public function setHandled(bool $handled): void
    {
        $this->handled = $handled;
    }

    public function isHandled(): bool
    {
        return $this->handled;
    }
}