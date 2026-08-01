<?php
declare(strict_types=1);

namespace Neo\Core\Database\Seeder;

use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\Database\Seeder\Attribute\Seeder;
use Neo\Core\Database\Seeder\Interface\SeedInterface;
use Neo\Core\DI\Container;
use Neo\Core\Utils\Scanner\ScannerAttributeManager;
use ReflectionClass;

class SeedManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @return list<array{class: class-string, order: int, group: string}>
     */
    public function discover(string $directory, string $namespace): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $normalizedDir = rtrim(str_replace('\\', '/', $directory), '/');
        $seeders = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(substr($path, strlen($normalizedDir)), '/');
            $classPath = str_replace('/', '\\', substr($relative, 0, -4));
            $fqcn = $namespace . '\\' . $classPath;

            if (!class_exists($fqcn)) {
                require_once $file->getPathname();
            }
            if (!class_exists($fqcn, false)) {
                continue;
            }

            $refl = new ReflectionClass($fqcn);
            if ($refl->isAbstract() || $refl->isInterface()) {
                continue;
            }

            $scanResults = new ScannerAttributeManager($fqcn)
                ->onClass()
                ->withAttribute(Seeder::class)
                ->scan();

            if ($scanResults === []) {
                continue;
            }

            if (!$refl->implementsInterface(SeedInterface::class)) {
                throw new DatabaseException(
                    title: 'Invalid Seeder',
                    message: sprintf("Seeder '%s' must implement SeederInterface.", $fqcn),
                    code: 500
                );
            }

            /** @var Seeder $meta */
            $meta = $scanResults[0]->getAttribute();
            $seeders[] = [
                'class' => $fqcn,
                'order' => $meta->order,
                'group' => $meta->group
            ];
        }

        usort($seeders, static fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $seeders;
    }

    /**
     * @param list<array{class: class-string, order: int, group: string}> $seeders
     * @return list<array{class: class-string, order: int, group: string}>
     */
    public function filterByGroup(array $seeders, ?string $group, bool $includeDev): array
    {
        if ($group !== null) {
            return array_values(array_filter($seeders, static fn (array $s) => $s['group'] === $group));
        }

        if ($includeDev) {
            return $seeders;
        }

        return array_values(array_filter($seeders, static fn (array $s) => $s['group'] === 'reference'));
    }

    /**
     * @param list<array{class: class-string, order: int, group: string}> $seeders
     * @return list<string>
     */
    public function run(array $seeders): array
    {
        $em = $this->container->get(EntityManager::class);
        $executed = [];

        foreach ($seeders as $definition) {
            $seeder = $this->container->get($definition['class']);

            if (!$seeder instanceof SeedInterface) {
                continue;
            }

            $seeder->run($em);
            $executed[] = $definition['class'];
        }

        return $executed;
    }
}