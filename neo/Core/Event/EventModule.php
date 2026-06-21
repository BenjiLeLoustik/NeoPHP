<?php
declare(strict_types=1);

namespace Neo\Core\Event;

use Neo\Core\DI\Container;
use Neo\Core\Module\Abstract\AbstractModule;

class EventModule extends AbstractModule
{
    /**
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [];
    }

    public function register(Container $container): void
    {
        $container->set(EventDispatcher::class, fn(Container $c) => new EventDispatcher($c));
    }

    protected function resolveDependencies(): void
    {
        $this->get(EventDispatcher::class);
    }
}