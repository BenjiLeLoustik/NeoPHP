<?php
declare(strict_types=1);

namespace Neo\Core\Event\Event;

use Neo\Core\Event\Abstract\AbstractEvent;
use Neo\Core\Http\Response\Response;

class ResponseEvent extends AbstractEvent
{
    public function __construct(
        private Response $response
    ) {}

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }
}