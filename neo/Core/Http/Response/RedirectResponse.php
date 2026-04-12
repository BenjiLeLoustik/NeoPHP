<?php
declare(strict_types=1);

namespace Neo\Core\Http\Response;

class RedirectResponse extends Response
{
    public function __construct(string $url, int $statusCode = 302)
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Location', $url);
        $this->setContent('');
    }
}
