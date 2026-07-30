<?php
declare(strict_types=1);

namespace Neo\Core\Http\Response\Types;

use Neo\Core\Http\HttpClient\Exception\HttpClientException;

class Response
{
    protected int $statusCode = 200;

    /** @var array<string, string> */
    protected array $headers = [];

    protected string $content = '';

    public function setStatusCode(int $code): static
    {
        $this->statusCode = $code;
        return $this;
    }

    public function setHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function addHeader(string $name, string $value): static
    {
        if (!isset($this->headers[$name])) {
            $this->headers[$name] = $value;
        } else {
            $this->headers[$name] .= ', ' . $value;
        }
        return $this;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     * @throws HttpClientException
     */
    public function toArray(): array
    {
        try {
            $decoded = json_decode($this->content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new HttpClientException(
                title: 'HTTP Response Not JSON',
                message: sprintf('The response body is not valid JSON: %s.', $e->getMessage()),
                code: 500,
                previous: $e
            );
        }

        if (!is_array($decoded)) {
            throw new HttpClientException(
                title: 'HTTP Response Not An Object',
                message: 'The JSON response did not decode to an array.',
                code: 500
            );
        }

        return $decoded;
    }

    public function send(): void
    {
        if (headers_sent() === false) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->content;
    }
}