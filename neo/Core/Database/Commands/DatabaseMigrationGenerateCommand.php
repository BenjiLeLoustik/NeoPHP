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
use Neo\Core\Database\DatabaseConnection;
use Neo\Core\Database\DatabaseIntrospector;
use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Migration\MigrationGenerator;
use Neo\Core\Database\Migration\MigrationSchemaSnapshot;
use Neo\Core\Database\Migration\SchemaDiffer;
use Neo\Core\DI\Container;

#[Command(
    name: 'database:migration:generate',
    description: 'Generate a migration file from the current database schema',
    category: 'Database',
)]
final class DatabaseMigrationGenerateCommand extends AbstractCommand
{
    public function __construct(
        private readonly Container $container
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
            name: 'name',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Migration name slug',
        );

        $this->addOption(
            name: 'dry-run',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Show the computed diff without writing a migration file',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project') ?? Input::choice('Target project ?', $this->getAvailableProjects());
        $name = $input->getOption('name') ?? Input::ask('Migration name ?', 'schema_update');
        $dryRun = (bool) $input->getOption('dry-run');

        $basePath = ROOT_DIR . "/src/$project";
        $migrationsPath = "$basePath/Database/Migrations";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        try {
            new ApplicationPaths($this->container)->register($project);
            $this->container->get(DatabaseConnection::class);

            if (!DatabaseConnection::isConnected()) {
                Output::error('Database not connected.');
                return ExitCode::FAILURE;
            }

            $introspector = new DatabaseIntrospector($this->container);
            $tables = $introspector->getTables();

            if (empty($tables)) {
                Output::warning('No tables found.');
                return ExitCode::SUCCESS;
            }

            $db = $this->container->get(DatabaseManager::class);
            $snapshot = new MigrationSchemaSnapshot($db, $introspector);
            $generator = new MigrationGenerator($introspector);
            $differ = new SchemaDiffer();

            $previousSchema = $snapshot->getLastSchema();

            if ($previousSchema === null) {
                if ($dryRun) {
                    Output::info('No previous snapshot found. Would generate a full schema migration.');
                    return ExitCode::SUCCESS;
                }

                Output::info('No previous snapshot found. Generating full schema migration.');
                $file = $generator->generate($migrationsPath, $name);
                $snapshot->take();

                Output::success('Migration file generated:');
                Output::muted('  ' . str_replace(ROOT_DIR, '', $file));
                return ExitCode::SUCCESS;
            }

            $currentSchema = $snapshot->getCurrentSchema();
            $diff = $differ->diff($previousSchema, $currentSchema);

            $diff['tableRenames'] = [];
            $tableRenameCandidates = $differ->findTableRenameCandidates($diff['tablesToCreate'], $diff['tablesToDrop']);

            foreach ($tableRenameCandidates as $candidate) {
                $confirmed = $dryRun || Input::confirm(
                        sprintf(
                            "Table '%s' has the same structure as new table '%s'. Treat as a rename (preserves data) ?",
                            $candidate['from'],
                            $candidate['to']
                        ),
                        true
                    );

                if ($confirmed) {
                    $diff['tableRenames'][] = $candidate;
                    unset($diff['tablesToCreate'][$candidate['to']]);
                    unset($diff['tablesToDrop'][$candidate['from']]);
                }
            }

            $diff['columnRenames'] = [];

            foreach ($diff['tableChanges'] as $table => $changes) {
                $columnCandidates = $differ->findColumnRenameCandidates($changes['removed'], $changes['added']);

                foreach ($columnCandidates as $candidate) {
                    $confirmed = $dryRun || Input::confirm(
                            sprintf(
                                "Column '%s.%s' has the same structure as new column '%s.%s'. Treat as a rename (preserves data) ?",
                                $table, $candidate['from'], $table, $candidate['to']
                            ),
                            true
                        );

                    if ($confirmed) {
                        $diff['columnRenames'][$table][] = $candidate;
                        $diff['tableChanges'][$table]['added'] = array_values(array_filter(
                            $diff['tableChanges'][$table]['added'],
                            fn($c) => $c['name'] !== $candidate['to']
                        ));
                        $diff['tableChanges'][$table]['removed'] = array_values(array_filter(
                            $diff['tableChanges'][$table]['removed'],
                            fn($c) => $c['name'] !== $candidate['from']
                        ));
                    }
                }

                if (empty($diff['tableChanges'][$table]['added'])
                    && empty($diff['tableChanges'][$table]['removed'])
                    && empty($diff['tableChanges'][$table]['modified'])
                ) {
                    unset($diff['tableChanges'][$table]);
                }
            }

            if ($differ->isEmpty($diff)) {
                Output::info('No schema changes detected. Nothing to migrate.');
                return ExitCode::SUCCESS;
            }

            $this->printDiffSummary($diff);

            $risks = $differ->findRiskyNotNullChanges($diff['tablesToCreate'], $diff['tableChanges']);
            if (!empty($risks)) {
                Output::newLine();
                Output::warning('Potentially unsafe changes detected:');
                foreach ($risks as $risk) {
                    Output::muted(sprintf(
                        "  ! %s.%s is NOT NULL without a default (%s) — will fail if the table already has rows.",
                        $risk['table'], $risk['column'], $risk['context']
                    ));
                }

                if (!$dryRun && !Input::confirm('Continue anyway ?', false)) {
                    Output::muted('Cancelled.');
                    return ExitCode::SUCCESS;
                }
            }

            $hasDrops = !empty($diff['tablesToDrop']) || $this->hasColumnDrops($diff['tableChanges']);
            if ($hasDrops && !$dryRun) {
                Output::newLine();
                Output::warning('This migration includes DROP operations (data loss on affected columns/tables).');
                if (!Input::confirm('Confirm you want to proceed ?', false)) {
                    Output::muted('Cancelled.');
                    return ExitCode::SUCCESS;
                }
            }

            if ($dryRun) {
                Output::newLine();
                Output::muted('Dry-run: no file written, no snapshot taken.');
                return ExitCode::SUCCESS;
            }

            $file = $generator->generateDiff($migrationsPath, $name, $diff);
            $snapshot->take();

            Output::success('Migration file generated:');
            Output::muted('  ' . str_replace(ROOT_DIR, '', $file));

            if (!empty($diff['tableRenames'])) {
                Output::newLine();
                Output::warning('Manual follow-up required after table rename(s):');
                foreach ($diff['tableRenames'] as $rename) {
                    Output::muted("  - '{$rename['from']}' → '{$rename['to']}'");
                }
                Output::muted('  1. php bin/neo database:generate --project=' . $project . ' --only=models --force');
                Output::muted('  2. Remove the old orphaned Model file(s) — see warnings from database:generate');
                Output::muted('  3. Update references to the old class name(s) in repositories, Twig extensions, controllers');
            }

            return ExitCode::SUCCESS;
        } catch (\Throwable $e) {
            Output::error('Generation failed: ' . $e->getMessage());
            return ExitCode::FAILURE;
        }
    }

    /**
     * @param array<string, array{added: array<int, array<string,mixed>>, removed: array<int, array<string,mixed>>, modified: array<int, mixed>}> $tableChanges
     */
    private function hasColumnDrops(array $tableChanges): bool
    {
        foreach ($tableChanges as $changes) {
            if (!empty($changes['removed'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{
     *     tablesToCreate: array<string, mixed>,
     *     tablesToDrop: array<string, mixed>,
     *     tableChanges: array<string, array{added: array<int, array<string,mixed>>, removed: array<int, array<string,mixed>>, modified: array<int, array{before: array<string,mixed>, after: array<string,mixed>}>}>,
     *     tableRenames?: array<int, array{from: string, to: string}>,
     *     columnRenames?: array<string, array<int, array{from: string, to: string}>>
     * } $diff
     */
    private function printDiffSummary(array $diff): void
    {
        foreach ($diff['tableRenames'] ?? [] as $rename) {
            Output::info("  ~ table `{$rename['from']}` → `{$rename['to']}` (rename)");
        }

        foreach (array_keys($diff['tablesToCreate']) as $table) {
            Output::success("  + table `$table`");
        }

        foreach (array_keys($diff['tablesToDrop']) as $table) {
            Output::warning("  - table `$table`");
        }

        foreach ($diff['columnRenames'] ?? [] as $table => $renames) {
            foreach ($renames as $rename) {
                Output::info("  ~ {$table}.{$rename['from']} → {$table}.{$rename['to']} (rename)");
            }
        }

        foreach ($diff['tableChanges'] as $table => $changes) {
            foreach ($changes['added'] as $col) {
                Output::success("  + {$table}.{$col['name']}");
            }
            foreach ($changes['removed'] as $col) {
                Output::warning("  - {$table}.{$col['name']}");
            }
            foreach ($changes['modified'] as $change) {
                Output::info("  ~ {$table}.{$change['after']['name']}");
            }
        }
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}