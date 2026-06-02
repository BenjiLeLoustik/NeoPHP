<?php
declare(strict_types=1);

namespace Neo\Core\Http;


use Neo\Core\Http\Client\Session\Session;
use Neo\Core\Http\File\UploadedFile;

class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $body;
    private array $headers;
    private array $server;
    private array $files;
    private Session $session;

    private function __construct(
        string $method,
        string $path,
        array $query,
        array $body,
        array $headers,
        array $server,
        array $files = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $this->sanitizePath($path);
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
        $this->server = $server;
        $this->files = $files;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = $_GET ?? [];

        $rawInput = file_get_contents('php://input') ?: '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        $body = [];
        if (stripos($contentType, 'application/json') !== false) {
            try {
                $decoded = json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);
                $body = is_array($decoded) ? $decoded : [];
            } catch (\JsonException $e) {
                $body = [];
            }
        } else {
            $body = $_POST ?? [];
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = (string) $_SERVER['CONTENT_LENGTH'];
        }

        $files = $_FILES ?? [];

        return new self($method, $path, $query, $body, $headers, $_SERVER, $files);
    }

    public static function createEmpty(): self
    {
        return new self('CLI', '/', [], [], [], [], []);
    }

    private function sanitizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public static function fromArray(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        array $headers = [],
        array $server = []
    ): self {
        return new self(
            $method,
            $path,
            $query,
            $body,
            $headers,
            $server
        );
    }

    public function file(string $key): ?UploadedFile
    {
        if (!isset($this->files[$key])) return null;

        return new UploadedFile($this->files[$key]);
    }

    public function allFiles(): array
    {
        return $this->files;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function body(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function allBody(): array
    {
        return $this->body;
    }

    public function getPost(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function header(string $name, $default = null)
    {
        return $this->headers[$name] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function server(): array
    {
        return $this->server;
    }

    public function getContent()
    {
        $rawInput = file_get_contents('php://input') ?: '';
        $contentType = $this->header('Content-Type', '');

        if (stripos($contentType, 'application/json') !== false) {
            try {
                return json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                return $rawInput;
            }
        }

        return $rawInput;
    }

    public function enablePreviousUrlTracking(Session $session): void
    {
        $this->session = $session;
        $this->rememberPreviousUrl();
    }

    private function rememberPreviousUrl(): void
    {
        if ($this->method !== 'GET') {
            return;
        }

        if (str_starts_with($this->path, '/api')) {
            return;
        }

        $previousUrl = $this->session->get('_current_url', null);
        $this->session->set('_previous_url', $previousUrl);

        $currentUrl = $this->getFullUrl();
        $this->session->set('_current_url', $currentUrl);
    }

    public function getPreviousUrl(?string $fallback = null): string
    {
        return $this->session->get('_previous_url', ($fallback ?? '/'));
    }

    public function getFullUrl(): string
    {
        $queryString = http_build_query($this->query);
        return $queryString ? $this->path . '?' . $queryString : $this->path;
    }

    public function getUserAgent(): ?string
    {
        return $this->server['HTTP_USER_AGENT'] ?? null;
    }

    public function getIp(): ?string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($this->server[$header])) {
                $ip = explode(',', $this->server[$header])[0];
                return trim($ip);
            }
        }

        return null;
    }

    public function getPostAll(): array
    {
        return $this->body;
    }

    public function getServer(): array
    {
        return $this->server;
    }

}
