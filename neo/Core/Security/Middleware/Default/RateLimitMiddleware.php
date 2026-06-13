<?php
declare(strict_types=1);

namespace Neo\Core\Security\Middleware\Default;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\Http\Request;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;
use Neo\Core\Utils\Cache\Cache;

class RateLimitMiddleware implements MiddlewareInterface
{
    private Cache $cache;
    private Request $request;
    private int $maxAttempts;
    private int $decaySeconds;
    private string $message;

    /**
     * @throws ContainerException
     */
    public function __construct(
        Container $container,
        int $maxAttempts = 60,
        int $decaySeconds = 60,
        string $message = "Too many requests. Please try again in a few moments."
    ) {
        $this->cache = $container->get(Cache::class);
        $this->request = $container->get(Request::class);
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->message = $message;
    }

    /**
     * @throws FrameworkException
     */
    public function handle(): bool
    {
        $key = $this->resolveKey();
        $attempts = (int) ($this->cache->get($key, 0));

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
        $path = $this->request->getPath();

        return 'rate_limit:' . md5($ip . ':' . $path);
    }
}