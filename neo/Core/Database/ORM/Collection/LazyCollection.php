<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Collection;

use Closure;

final class LazyCollection extends Collection
{
    private bool $initialized = false;

    public function __construct(
        private readonly Closure $loader,
    ) {
        parent::__construct([]);
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    protected function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;
        $this->elements = ($this->loader)();
    }
}