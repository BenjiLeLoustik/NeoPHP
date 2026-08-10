<?php
declare(strict_types=1);

namespace Neo\Core\Module;

use Neo\Core\DI\Container;
use Neo\Core\Module\Exception\ModuleException;
use Neo\Core\Module\Interface\ModuleInterface;
use Neo\Core\Profiler\ProfilerManager;
use Neo\Core\Profiler\TimelineRecorder;
use Neo\Core\Utils\Scanner\ScannerFileManager;
use ReflectionException;

class ModuleManager
{
    /** @var class-string<ModuleInterface>[] */
    private array $modules = [];

    public function __construct(private readonly Container $container) {}

    public function discover(string $basePath, bool $excludeTestFixtures = true): self
    {
        $results = new ScannerFileManager()
            ->paths([$basePath])
            ->withFilenameSuffix('Module.php')
            ->scan();

        foreach ($results as $result) {
            $fqcn = $result->getFqcn();

            if ($excludeTestFixtures && (str_contains($fqcn, '\\Tests\\') || str_contains($fqcn, '\\Fixture\\'))) {
                continue;
            }

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
     * @throws \Throwable
     */
    public function boot(): void
    {
        $ordered = $this->resolveDependencyOrder($this->modules);

        /** @var array<class-string, object> $initResults */
        $initResults = [];

        foreach ($ordered as $moduleClass) {
            try {
                $moduleStart = microtime(true);

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

                if (class_exists(TimelineRecorder::class)) {
                    TimelineRecorder::record('boot', $moduleClass, $moduleStart);
                }
            } catch (\Throwable $e) {
                $this->recordBootError($moduleClass, $e);
                throw $e;
            }
        }
    }

    private function recordBootError(string $moduleClass, \Throwable $e): void
    {
        try {
            if (!class_exists(ProfilerManager::class)) {
                return;
            }

            $profiler = ProfilerManager::getInstance();
            $profiler->setBootError($e);
        } catch (\Throwable) {}
    }

    /**
     * @throws ReflectionException
     */
    private function deriveAlias(string $moduleClass): string
    {
        $shortName = new \ReflectionClass($moduleClass)->getShortName();
        $stripped = str_ends_with($shortName, 'Module')
            ? substr($shortName, 0, -6)
            : $shortName;
        return lcfirst($stripped);
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

        $resolve = function (
            string $moduleClass,
            ?string $requiredBy = null
        ) use (&$resolve, &$resolved, &$resolving): void {
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
            $resolving
                |> (fn (array $r): array => array_filter($r, fn ($m) => $m !== $moduleClass))
                |> array_values(...);
        };

        foreach ($modules as $moduleClass) {
            $resolve($moduleClass);
        }

        return $resolved;
    }
}