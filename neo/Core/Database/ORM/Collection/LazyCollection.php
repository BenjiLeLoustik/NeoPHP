<?php
declare(strict_types=1);

namespace Neo\Core\Database\ORM\Collection;

use Closure;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @extends Collection<TKey, TValue>
 */
final class LazyCollection extends Collection
{
    private bool $initialized = false;

    /**
     * @param Closure(): array<TKey, TValue> $loader
     */
    public function __construct(
        private Closure $loader,
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