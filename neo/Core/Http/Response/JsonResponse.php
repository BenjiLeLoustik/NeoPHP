<?php
declare(strict_types=1);

namespace Neo\Core\Http\Response;

class JsonResponse extends Response
{
    public function __construct(array|object $data, int $statusCode = 200)
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');

        $json = json_encode($data, JSON_THROW_ON_ERROR);

        $this->setContent($json);
    }
}
