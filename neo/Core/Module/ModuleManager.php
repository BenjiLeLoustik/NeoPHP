<?php
declare(strict_types=1);

namespace Neo\Core\Module;

use Neo\Core\DI\Container;
use Neo\Core\Module\Exception\ModuleException;
use Neo\Core\Module\Interface\ModuleInterface;
use ReflectionException;

class ModuleManager
{
    /** @var class-string<ModuleInterface>[] */
    private array $modules = [];

    public function __construct(private readonly Container $container) {}

    public function discover(string $basePath, bool $excludeTestFixtures = true): self
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

            if ($fqcn === null) {
                continue;
            }

            if ($excludeTestFixtures && (str_contains($fqcn, '\\Tests\\') || str_contains($fqcn, '\\Fixture\\'))) {
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
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $ordered = $this->resolveDependencyOrder($this->modules);

        /** @var array<class-string, object> $initResults */
        $initResults = [];

        foreach ($ordered as $moduleClass) {
            /** @var ModuleInterface $module */
            $module = new $moduleClass();

            $module->register($this->container);

            $ownAlias = $this->deriveAlias($moduleClass);

            foreach ($module->dependencies() as $depClass) {
                if (!array_key_exists($depClass, $initResults)) {
                    continue;
                }

                $depKey = lcfirst(new \ReflectionClass($depClass)->getShortName());
                $this->container->set($ownAlias . '.' . $depKey, $initResults[$depClass]);
            }

            $result = $module->init($this->container);
            $initResults[$moduleClass] = $result;

            $this->container->set($ownAlias . '.manager', $result);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function deriveAlias(string $moduleClass): string
    {
        $shortName = new \ReflectionClass($moduleClass)->getShortName();
        $stripped = str_ends_with($shortName, 'Module') ? substr($shortName, 0, -6) : $shortName;
        return lcfirst($stripped);
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

        $resolve = function (string $moduleClass, ?string $requiredBy = null) use (&$resolve, &$resolved, &$resolving): void {
            if (in_array($moduleClass, $resolved, true)) {
                return;
            }

            if (in_array($moduleClass, $resolving, true)) {
                throw new ModuleException(
                    title: 'Circular Dependency',
                    message: sprintf('Circular dependency detected in module "%s".', $moduleClass),
                    code: 500,
                    context: ['module' => $moduleClass, 'chain' => $resolving]
                );
            }

            if (!class_exists($moduleClass)) {
                $message = $requiredBy !== null
                    ? sprintf(
                        "Module '%s' is missing but is required by '%s'. Make sure it is present in neo/Core and correctly loaded.",
                        $moduleClass,
                        $requiredBy
                    )
                    : sprintf('Module "%s" does not exist.', $moduleClass);

                throw new ModuleException(
                    title: 'Module Not Found',
                    message: $message,
                    code: 500,
                    context: ['missing' => $moduleClass, 'requiredBy' => $requiredBy]
                );
            }

            $resolving[] = $moduleClass;

            /** @var ModuleInterface $instance */
            $instance = new $moduleClass();
            foreach ($instance->dependencies() as $dep) {
                $resolve($dep, $moduleClass);
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