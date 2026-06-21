<?php
declare(strict_types=1);

namespace Neo\Core\Event\Interface;

interface EventInterface
{
    public function isPropagationStopped(): bool;
    public function stopPropagation(): void;
}