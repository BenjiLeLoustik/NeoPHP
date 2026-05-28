<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Cookie;

use Neo\Core\DI\Container;
use Neo\Core\Utils\Config\Config;

class Cookie
{
    protected Container $container;
    protected array $config;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $cfg = $this->container->get(Config::class);
        $this->config = $cfg->from('session')->get('cookie');
    }

    protected function buildName(string $name): string
    {
        return $this->config['prefix'] . $name;
    }

    public function set(
        string $name,
        string $value,
        ?int $expire = null,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        ?bool $httpOnly = null
    ): void {
        $name = $this->buildName($name);

        setcookie(
            $name,
            $value,
            [
                'expires' => $expire ?? time() + $this->config['lifetime'],
                'path' => $path ?? $this->config['path'],
                'domain' => $domain ?? $this->config['domain'],
                'secure' => $secure ?? $this->config['secure'],
                'httponly' => $httpOnly ?? $this->config['http_only'],
                'samesite' => $this->config['same_site'],
            ]
        );

        $_COOKIE[$name] = $value;
    }

    public function get(string $name, mixed $default = null): mixed
    {
        $name = $this->buildName($name);
        return $_COOKIE[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($_COOKIE[$this->buildName($name)]);
    }

    public function remove(string $name): void
    {
        $name = $this->buildName($name);

        setcookie(
            $name,
            '',
            [
                'expires' => time() - 3600,
                'path'    => $this->config['path'],
                'domain'  => $this->config['domain'],
            ]
        );

        unset($_COOKIE[$name]);
    }

    public function all(): array
    {
        return $_COOKIE;
    }
}
