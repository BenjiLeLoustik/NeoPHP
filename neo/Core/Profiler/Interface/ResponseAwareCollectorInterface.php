<?php

declare(strict_types=1);

namespace Neo\Core\Profiler\Interface;

use Neo\Core\Http\Response\Types\Response;

interface ResponseAwareCollectorInterface
{
    public function setResponse(Response $response): void;
}