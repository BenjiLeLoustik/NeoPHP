<?php
declare(strict_types=1);

namespace Neo\Core\Module;

use Neo\Core\DI\Container;
use Neo\Core\Module\Exception\ModuleException;
use Neo\Core\Module\Interface\ModuleInterface;

class ModuleManager
{
    /** @var class-string<ModuleInterface>[] */
    private array $modules = [];

    public function __construct(private readonly Container $container) {}

    public function discover(string $basePath): self
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (!str_ends_with($file->getFilename(), 'Module.php')) {
                continue;
            }

            $fqcn = $this->resolveFqcn($file->getRealPath());

            if (str_contains($fqcn, '\\Tests\\') || str_contains($fqcn, '\\Fixture\\')) {
                continue;
            }

            if ($fqcn === null) {
                continue;
            }

            require_once $file->getRealPath();

            if (!class_exists($fqcn)) {
                continue;
            }

            $ref = new \ReflectionClass($fqcn);

            if ($ref->isAbstract() || $ref->isInterface()) {
                continue;
            }

            if (!$ref->implementsInterface(ModuleInterface::class)) {
                continue;
            }

            $this->modules[] = $fqcn;
        }

        return $this;
    }

    /**
     * @throws ModuleException
     */
    public function boot(): void
    {
        $ordered = $this->resolveDependencyOrder($this->modules);

        /** @var ModuleInterface[] $instances */
        $instances = [];
        foreach ($ordered as $moduleClass) {
            $module = new $moduleClass();
            $module->register($this->container);
            $instances[$moduleClass] = $module;
        }

        foreach ($instances as $module) {
            $module->boot($this->container);
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

        $className = trim($m[1]);

        return $namespace !== '' ? $namespace . '\\' . $className : $className;
    }

    /**
     * @param class-string<ModuleInterface>[] $modules
     * @return class-string<ModuleInterface>[]
     * @throws ModuleException
     */
    private function resolveDependencyOrder(array $modules): array
    {
        $resolved = [];
        $resolving = [];

        $resolve = function (string $moduleClass) use (&$resolve, &$resolved, &$resolving): void {
            if (in_array($moduleClass, $resolved, true)) {
                return;
            }

            if (in_array($moduleClass, $resolving, true)) {
                throw new ModuleException(
                    title: 'Circular Dependency',
                    message: sprintf('Circular dependency detected in module "%s".', $moduleClass),
                    code: 500
                );
            }

            if (!class_exists($moduleClass)) {
                throw new ModuleException(
                    title: 'Module Not Found',
                    message: sprintf('Module class "%s" does not exist.', $moduleClass),
                    code: 500
                );
            }

            $resolving[] = $moduleClass;

            $instance = new $moduleClass();
            foreach ($instance->dependencies() as $dep) {
                $resolve($dep);
            }

            $resolved[] = $moduleClass;
            $resolving = array_values(array_filter($resolving, fn($m) => $m !== $moduleClass));
        };

        foreach ($modules as $moduleClass) {
            $resolve($moduleClass);
        }

        return $resolved;
    }
}