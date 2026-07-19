<?php
declare(strict_types=1);

namespace Neo\Core\Event\Event;

use Neo\Core\Event\Abstract\AbstractEvent;
use Neo\Core\Http\Request\Request;

class RequestEvent extends AbstractEvent
{
    public function __construct(
        private readonly Request $request
    ) {}

    public function getRequest(): Request
    {
        return $this->request;
    }
}