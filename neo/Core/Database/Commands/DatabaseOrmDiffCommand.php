<?php
declare(strict_types=1);

namespace Neo\Core\Database\Commands;

use Neo\Core\Application\ApplicationPaths;
use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Database\Access\Connection\DatabaseConnection;
use Neo\Core\Database\Access\Introspector\DatabaseIntrospector;
use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Exception\DatabaseException;
use Neo\Core\Database\Migration\Generator\MigrationGenerator;
use Neo\Core\Database\Migration\MigrationSchemaSnapshot;
use Neo\Core\Database\Migration\SchemaDiffer;
use Neo\Core\Database\ORM\Mapping\Attribute\Entity;
use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\Database\ORM\Platform\AbstractPlatform;
use Neo\Core\Database\ORM\Schema\SchemaTool;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;

#[Command(
    name: 'database:orm:diff',
    description: 'Generate a migration from the difference between entities and the database',
    category: 'Database',
)]
final class DatabaseOrmDiffCommand extends AbstractCommand
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function configure(): void
    {
        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'connection',
            shortcut: null,
            mode: InputOption::OPTIONAL,
            description: 'Database connection to use',
            default: null,
        );

        $this->addOption(
            name: 'name',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Migration name slug',
        );

        $this->addOption(
            name: 'dry-run',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Show the diff without writing a migration file',
        );
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @throws DatabaseException
     * @throws ContainerException
     */
    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project');
        $name = $input->getOption('name') ?? Input::ask('Migration name ?', 'schema_update');
        $dryRun = (bool) $input->getOption('dry-run');

        $basePath = ROOT_DIR . "/src/$project";
        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        new ApplicationPaths($this->container)->register($project);

        $connection = $input->getOption('connection');
        if ($connection !== null) {
            DatabaseConnection::connectTo($connection);
        }
        $this->container->get(DatabaseConnection::class);

        if (!DatabaseConnection::isConnected()) {
            Output::error('Database not connected.');
            return ExitCode::FAILURE;
        }

        $entityDir = "$basePath/Database/Entity";
        if (!is_dir($entityDir)) {
            Output::error("No entities found in $entityDir");
            return ExitCode::FAILURE;
        }

        $entities = $this->discoverEntities($entityDir, $project);
        if ($entities === []) {
            Output::warning('No #[Entity] classes discovered.');
            return ExitCode::SUCCESS;
        }
        Output::info(count($entities) . ' entities discovered.');

        $em = new EntityManager($this->container);
        $platform = $em->getPlatform();
        $schemaTool = new SchemaTool($em);

        $desired = $schemaTool->getSchema($entities);

        $introspector = $connection !== null
            ? DatabaseIntrospector::on($this->container, $connection)
            : new DatabaseIntrospector($this->container);

        $db = $this->container->get(DatabaseManager::class);
        $snapshot = new MigrationSchemaSnapshot($db, $introspector, $connection ?? 'default');
        $current = $this->canonicalizeSchema($snapshot->getCurrentSchema(), $platform);

        $current = array_intersect_key($current, $desired);

        $differ = new SchemaDiffer();
        $diff = $differ->diff($current, $desired);
        $diff['tableRenames'] = [];
        $diff['columnRenames'] = [];

        if ($this->isEmptyDiff($diff)) {
            Output::success('Schema is up to date. Nothing to migrate.');
            return ExitCode::SUCCESS;
        }

        $this->printSummary($diff, $output);

        if ($dryRun) {
            Output::info('Dry run: no migration written.');
            return ExitCode::SUCCESS;
        }

        $migrationsPath = $connection !== null
            ? "$basePath/Database/Migrations/$connection"
            : "$basePath/Database/Migrations";

        $generator = new MigrationGenerator($introspector);
        $file = $generator->generateDiff($migrationsPath, $name, $diff);

        Output::success('Migration file generated:');
        Output::muted('  ' . str_replace(ROOT_DIR, '', $file));

        return ExitCode::SUCCESS;
    }

    /**
     * @return list<class-string>
     * @throws ReflectionException
     */
    private function discoverEntities(string $entityDir, string $project): array
    {
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($entityDir, \FilesystemIterator::SKIP_DOTS)
        );

        $normalizedDir = $entityDir
                |> (fn (string $d): string => str_replace('\\', '/', $d))
                |> (fn (string $d): string => rtrim($d, '/'));

        $classes = [];

        foreach ($rii as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $relative = $normalizedDir
                    |> strlen(...)
                    |> (fn($x) => substr($path, $x))
                    |> (fn($x) => ltrim($x, '/'));

            $classPath = $relative
                    |> (fn (string $r): string => substr($r, 0, -4))
                    |> (fn (string $s): string => str_replace('/', '\\', $s));

            $fqcn = "Neo\\Src\\$project\\Database\\Entity\\$classPath";

            if (!class_exists($fqcn)) {
                require_once $file->getPathname();
            }
            if (!class_exists($fqcn, false)) {
                continue;
            }

            if (new ReflectionClass($fqcn)->getAttributes(Entity::class) !== []) {
                $classes[] = $fqcn;
            }
        }

        return $classes;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $schema
     * @return array<string, list<array<string, mixed>>>
     */
    private function canonicalizeSchema(array $schema, AbstractPlatform $platform): array
    {
        foreach ($schema as $table => $columns) {
            foreach ($columns as $i => $column) {
                if (isset($column['type'])) {
                    $schema[$table][$i]['type'] = $platform->canonicalizeType((string) $column['type']);
                }
            }
        }
        return $schema;
    }

    /**
     * @param array<string, mixed> $diff
     */
    private function isEmptyDiff(array $diff): bool
    {
        return empty($diff['tablesToCreate'])
            && empty($diff['tablesToDrop'])
            && empty(array_filter($diff['tableChanges'] ?? [], static fn($c) =>
                !empty($c['added']) || !empty($c['removed']) || !empty($c['modified'])));
    }

    /**
     * @param array<string, mixed> $diff
     */
    private function printSummary(array $diff, Output $output): void
    {
        foreach (array_keys($diff['tablesToCreate'] ?? []) as $table) {
            Output::info("  + create table $table");
        }
        foreach (array_keys($diff['tablesToDrop'] ?? []) as $table) {
            Output::warning("  - drop table $table");
        }
        foreach ($diff['tableChanges'] ?? [] as $table => $changes) {
            foreach ($changes['added'] ?? [] as $col) {
                Output::info("  ~ $table: add column {$col['name']}");
            }
            foreach ($changes['removed'] ?? [] as $col) {
                Output::warning("  ~ $table: drop column {$col['name']}");
            }
            foreach ($changes['modified'] ?? [] as $col) {
                Output::info("  ~ $table: modify column {$col['after']['name']}");
            }
        }
    }
}