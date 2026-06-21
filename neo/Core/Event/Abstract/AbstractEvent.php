<?php
declare(strict_types=1);

namespace Neo\Core\Event\Abstract;

use Neo\Core\Event\Contract\EventInterface;

abstract class AbstractEvent implements EventInterface
{
    private bool $propagationStopped = false;

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}