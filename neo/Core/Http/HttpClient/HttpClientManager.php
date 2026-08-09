<?php
declare(strict_types=1);

namespace Neo\Core\Http\HttpClient;

use Neo\Core\Http\HttpClient\Exception\HttpClientException;
use Neo\Core\Http\HttpClient\Interface\HttpClientInterface;
use Neo\Core\Http\Response\Types\Response;

class HttpClientManager implements HttpClientInterface
{
    /** @var list<array{method: string, url: string, requestHeaders: list<string>, requestBody: string|null, statusCode: int|null, responseHeaders: array<string, list<string>>, duration: float, error: string|null}> */
    private static array $requests = [];

    /**
     * @param array<string, mixed> $defaultOptions
     */
    public function __construct(
        private readonly array $defaultOptions = []
    ) {}

    public function request(string $method, string $url, array $options = []): Response
    {
        $options = array_replace($this->defaultOptions, $options);
        $method = strtoupper($method);

        if (isset($options['base_uri']) && !preg_match('#^https?://#i', $url)) {
            $url = rtrim((string) $options['base_uri'], '/') . '/' . ltrim($url, '/');
        }

        if (!empty($options['query']) && is_array($options['query'])) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($options['query']);
        }

        $headers = $this->normalizeHeaders($options['headers'] ?? []);
        $body = $this->buildBody($options, $headers);

        if (isset($options['bearer'])) {
            $headers[] = 'Authorization: Bearer ' . $options['bearer'];
        }

        /** @var array<string, list<string>> $responseHeaders */
        $responseHeaders = [];

        $ch = curl_init();
        $start = microtime(true);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => ((int) ($options['max_redirects'] ?? 20)) > 0,
            CURLOPT_MAXREDIRS => (int) ($options['max_redirects'] ?? 20),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT_MS => (int) (((float) ($options['timeout'] ?? 30.0)) * 1000),
            CURLOPT_ENCODING => '', // accepte gzip/deflate
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $trimmed = trim($line);

                if ($trimmed === '' || !str_contains($trimmed, ':')) {
                    return $length;
                }

                [$name, $value] = explode(':', $trimmed, 2);
                $responseHeaders[strtolower(trim($name))][] = trim($value);

                return $length;
            },
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if (isset($options['auth_basic'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->basicAuth($options['auth_basic']));
        }

        $content = curl_exec($ch);
        $duration = round((microtime(true) - $start) * 1000, 2);

        if ($content === false) {
            $message = curl_error($ch);

            self::$requests[] = [
                'method' => $method,
                'url' => $url,
                'requestHeaders' => $headers,
                'requestBody' => $body,
                'statusCode' => null,
                'responseHeaders' => [],
                'duration' => $duration,
                'error' => $message,
            ];

            throw new HttpClientException(
                title: 'HTTP Transport Error',
                message: sprintf("Request '%s %s' failed: %s.", $method, $url, $message),
                code: 500
            );
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        self::$requests[] = [
            'method' => $method,
            'url' => $url,
            'requestHeaders' => $headers,
            'requestBody' => $body,
            'statusCode' => $statusCode,
            'responseHeaders' => $responseHeaders,
            'duration' => $duration,
            'error' => null,
        ];

        $response = new Response()
            ->setStatusCode($statusCode)
            ->setContent((string) $content);

        foreach ($responseHeaders as $name => $values) {
            $response->setHeader($name, implode(', ', $values));
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $options
     * @param list<string> $headers Passé par référence pour ajouter Content-Type.
     * @throws HttpClientException
     */
    private function buildBody(array $options, array &$headers): ?string
    {
        if (array_key_exists('json', $options)) {
            if (!$this->hasHeader($headers, 'Content-Type')) {
                $headers[] = 'Content-Type: application/json';
            }

            try {
                return json_encode(
                    $options['json'],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            } catch (\JsonException $e) {
                throw new HttpClientException(
                    title: 'HTTP Request Body Error',
                    message: sprintf('Unable to encode the JSON request body: %s.', $e->getMessage()),
                    code: 500,
                    previous: $e
                );
            }
        }

        if (isset($options['body'])) {
            $rawBody = $options['body'];

            if (is_array($rawBody)) {
                if (!$this->hasHeader($headers, 'Content-Type')) {
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                }

                return http_build_query($rawBody);
            }

            return (string) $rawBody;
        }

        return null;
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            foreach ((array) $value as $single) {
                $lines[] = $name . ': ' . $single;
            }
        }

        return $lines;
    }

    /**
     * @param list<string> $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        $needle = strtolower($name) . ':';

        foreach ($headers as $line) {
            if (str_starts_with(strtolower($line), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string|array{0: string, 1: string} $auth
     */
    private function basicAuth(string|array $auth): string
    {
        return is_array($auth) ? implode(':', $auth) : $auth;
    }

    /**
     * @return list<array{method: string, url: string, requestHeaders: list<string>, requestBody: string|null, statusCode: int|null, responseHeaders: array<string, list<string>>, duration: float, error: string|null}>
     */
    public static function getRequests(): array
    {
        return self::$requests;
    }
}