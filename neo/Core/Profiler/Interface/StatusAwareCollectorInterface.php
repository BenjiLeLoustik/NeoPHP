<?php

declare(strict_types=1);

namespace Neo\Core\Profiler\Interface;

interface StatusAwareCollectorInterface
{
    public function setStatusCode(?int $statusCode): void;
}