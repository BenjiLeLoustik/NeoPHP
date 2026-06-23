<?php
declare(strict_types=1);

namespace Neo\Core\Controller;

use Closure;
use Neo\Core\Controller\Exception\AbstractControllerException;
use Neo\Core\Controller\Interface\ControllerExtensionInterface;
use Neo\Core\Database\DatabaseControllerExtension;
use Neo\Core\DI\Container;
use Neo\Core\Event\EventControllerExtension;
use Neo\Core\Extension\ExtensionControllerExtension;
use Neo\Core\Http\HttpControllerExtension;
use Neo\Core\Routing\RouterControllerExtension;
use Neo\Core\Security\Auth\AuthControllerExtension;
use Neo\Core\Security\Middleware\MiddlewareControllerExtension;
use Neo\Core\Utils\Cache\CacheControllerExtension;
use Neo\Core\Utils\Config\ConfigControllerExtension;
use Neo\Core\Utils\Logger\LoggerControllerExtension;
use Neo\Core\Utils\Notification\NotificationControllerExtension;
use Neo\Core\Utils\Scanner\ScannerControllerExtension;
use Neo\Core\View\ViewControllerExtension;

/**
 * @mixin EventControllerExtension
 * @mixin ExtensionControllerExtension
 * @mixin HttpControllerExtension
 * @mixin RouterControllerExtension
 * @mixin AuthControllerExtension
 * @mixin MiddlewareControllerExtension
 * @mixin CacheControllerExtension
 * @mixin ConfigControllerExtension
 * @mixin LoggerControllerExtension
 * @mixin NotificationControllerExtension
 * @mixin ViewControllerExtension
 * @mixin ScannerControllerExtension
 * @mixin DatabaseControllerExtension
 */
abstract class AbstractController
{
    protected Container $container;

    /** @var array<string, Closure> */
    private array $methods = [];

    public function __construct(?Container $container = null)
    {
        if ($container === null) return;

        $this->container = $container;

        $this->discoverExtensions();
    }

    public function registerMethod(string $name, Closure $resolver): void
    {
        $this->methods[$name] = $resolver;
    }

    /**
     * @param list<mixed> $arguments
     * @throws AbstractControllerException
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (isset($this->methods[$name])) {
            return ($this->methods[$name])(...$arguments);
        }

        throw new AbstractControllerException(
            title: 'Controller Error',
            message: sprintf("Method '%s' is not registered on this controller.", $name),
            code: 500
        );
    }

    private function discoverExtensions(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__ . '/../')
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (!str_ends_with($file->getFilename(), 'ControllerExtension.php')) {
                continue;
            }

            $fqcn = $this->resolveFqcn($file->getRealPath());
            if ($fqcn === null) {
                continue;
            }

            require_once $file->getRealPath();
            if (!class_exists($fqcn)) {
                continue;
            }

            $ref = new \ReflectionClass($fqcn);
            if ($ref->isAbstract() || !$ref->implementsInterface(ControllerExtensionInterface::class)) {
                continue;
            }

            /** @var ControllerExtensionInterface $extension */
            $extension = new $fqcn();
            $extension->extend($this, $this->container);
        }
    }

    private function resolveFqcn(string $filePath): ?string
    {
        $src = file_get_contents($filePath);
        if ($src === false) {
            return null;
        }

        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/i', $src, $m)) {
            $namespace = trim($m[1]);
        }

        if (!preg_match('/class\s+([A-Za-z0-9_]+)/i', $src, $m)) {
            return null;
        }

        return $namespace !== '' ? $namespace . '\\' . trim($m[1]) : trim($m[1]);
    }
}