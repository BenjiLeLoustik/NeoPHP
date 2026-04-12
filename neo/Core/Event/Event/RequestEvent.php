<?php
declare(strict_types=1);

namespace Neo\Core\Event\Event;

use Neo\Core\Event\AbstractEvent;
use Neo\Core\Http\Request;

class RequestEvent extends AbstractEvent
{
    public function __construct(private Request $request) {}

    public function getRequest(): Request
    {
        return $this->request;
    }
}