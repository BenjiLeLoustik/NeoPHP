<?php
declare(strict_types=1);

namespace Neo\Core\Http\Response;

use JsonException;

class JsonResponse extends Response
{
    /**
     * @param array<string, mixed>|object $data
     * @throws JsonException
     */
    public function __construct(array|object $data, int $statusCode = 200)
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');

        $json = json_encode($data, JSON_THROW_ON_ERROR);

        $this->setContent($json);
    }
}
