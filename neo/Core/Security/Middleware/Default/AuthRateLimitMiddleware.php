<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Default;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Http\Request;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;
use Neo\Core\Utils\Cache\Cache;

class AuthRateLimitMiddleware implements MiddlewareInterface
{
    private Cache $cache;
    private Request $request;
    private int $maxAttempts;
    private int $decaySeconds;
    private string $identifierField;
    private string $message;

    /**
     * @throws ContainerException
     */
    public function __construct(
        Container $container,
        int $maxAttempts = 5,
        int $decaySeconds = 300,
        string $identifierField = 'email',
        string $message = 'Trop de tentatives de connexion. Réessayez dans quelques minutes.',
    ) {
        $this->cache = $container->get(Cache::class);
        $this->request = $container->get(Request::class);
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->identifierField = $identifierField;
        $this->message = $message;
    }

    /**
     * @throws FrameworkException
     */
    public function handle(): bool
    {
        $key = $this->resolveKey();
        $attempts = (int) $this->cache->get($key, 0);

        if ($attempts >= $this->maxAttempts) {
            throw new FrameworkException(
                title: 'Too Many Requests',
                message: $this->message,
                code: 429
            );
        }

        $this->cache->set($key, $attempts + 1, $this->decaySeconds);

        return true;
    }

    private function resolveKey(): string
    {
        $ip = $this->request->getIp() ?? 'unknown';
        $identifier = (string) ($this->request->body($this->identifierField) ?? '');

        return 'auth_rate_limit:' . md5($ip . ':' . $identifier);
    }
}