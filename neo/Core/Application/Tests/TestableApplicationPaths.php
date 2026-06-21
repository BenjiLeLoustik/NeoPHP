<?php
declare(strict_types=1);

namespace Neo\Core\Application\Tests;

use Neo\Core\Application\ApplicationPaths;

final readonly class TestableApplicationPaths extends ApplicationPaths
{
    public function exposeResolvePublicPath(string $basePath): string
    {
        return $this->resolvePublicPath($basePath);
    }
}