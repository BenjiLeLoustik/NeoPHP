<?php
declare(strict_types=1);

namespace Neo\Core\Profiler;

use Neo\Core\DI\Container;

interface CollectorAwareInterface
{
    public function boot(Container $container): void;
}