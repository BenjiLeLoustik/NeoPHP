<?php
declare(strict_types=1);

namespace Neo\Core\Http\Client\Session;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Utils\Config\Config;

class Session
{
    protected Container $container;
    protected array $config;

    /**
     * @throws FrameworkException
     * @throws ContainerException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $config = $this->container->get(Config::class);
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
            ini_set('session.save_path',    $savePath);
        }
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function all(): array
    {
        return $_SESSION;
    }

    public function clear(): void
    {
        $_SESSION = [];
    }

    public function destroy(): void
    {
        session_destroy();
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
