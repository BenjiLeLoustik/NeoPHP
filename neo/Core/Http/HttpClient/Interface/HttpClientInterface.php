<?php
declare(strict_types=1);

namespace Neo\Core\Http\HttpClient\Interface;

use Neo\Core\Http\HttpClient\Exception\HttpClientException;
use Neo\Core\Http\Response\Types\Response;

interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $options
     * @throws HttpClientException
     */
    public function request(string $method, string $url, array $options = []): Response;
}