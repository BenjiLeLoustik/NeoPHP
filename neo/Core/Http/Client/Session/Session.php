<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Session;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;

class Session
{
    protected Container $container;

    /**
     * @var array{
     *     enabled: bool,
     *     name: string,
     *     lifetime: int,
     *     secure: bool,
     *     http_only: bool,
     *     same_site: string,
     *     storage: array{
     *         enabled: bool,
     *         handler: string
     *     }
     * }
     */
    protected array $config;

    private bool $isCli;

    /**
     * @throws FrameworkException
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->isCli = PHP_SAPI === 'cli';

        if ($this->isCli) {
            return;
        }

        $config = $this->container->get('client.configModule');
        $this->config = $config->from('session')->get('session');

        $this->configureSession();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * @throws FrameworkException
     * @throws ContainerException
     */
    protected function configureSession(): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.name', $this->config['name']);
        ini_set('session.gc_maxlifetime', (string) $this->config['lifetime']);
        ini_set('session.cookie_lifetime', (string) $this->config['lifetime']);
        ini_set('session.cookie_secure', $this->config['secure'] ? '1' : '0');
        ini_set('session.cookie_httponly', $this->config['http_only'] ? '1' : '0');
        ini_set('session.cookie_samesite', $this->config['same_site']);

        if ($this->config['storage']['enabled']) {
            $savePath = $this->container->get('storagePath') . '/var/cache/session/';

            if (!is_dir($savePath) && !mkdir($savePath, 0777, true) && !is_dir($savePath)) {
                throw new FrameworkException(
                    title: 'Session Storage Error',
                    message: sprintf("Failed to create the session storage directory '%s'.", $savePath),
                    code: 500
                );
            }

            ini_set('session.save_handler', $this->config['storage']['handler']);
            ini_set('session.save_path', $savePath);
        }
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->isCli) {
            return;
        }

        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->isCli) {
            return $default;
        }

        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        if ($this->isCli) {
            return false;
        }

        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        if ($this->isCli) {
            return;
        }

        unset($_SESSION[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->isCli) {
            return [];
        }

        return $_SESSION;
    }

    public function clear(): void
    {
        if ($this->isCli) {
            return;
        }

        $_SESSION = [];
    }

    public function destroy(): void
    {
        if ($this->isCli) {
            return;
        }

        session_destroy();
    }

    public function regenerate(): void
    {
        if ($this->isCli) {
            return;
        }

        session_regenerate_id(true);
    }
}