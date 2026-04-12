<?php
declare(strict_types=1);

namespace Neo\Core\Event\Contract;

interface EventInterface
{
    public function isPropagationStopped(): bool;
    public function stopPropagation(): void;
}