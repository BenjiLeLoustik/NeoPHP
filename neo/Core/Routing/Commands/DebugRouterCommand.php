<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Commands;

use Neo\Core\Application\ApplicationPaths;
use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\DI\Container;
use Neo\Core\Routing\RouterManager;

#[Command(
    name: 'debug:router',
    description: 'Display all registered routes for a project',
    category: 'RouterManager',
)]
final class DebugRouterCommand extends AbstractCommand
{
    private const array METHOD_COLORS = [
        'GET' => 'green',
        'POST' => 'yellow',
        'PUT' => 'cyan',
        'PATCH' => 'cyan',
        'DELETE' => 'red',
    ];

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
            name: 'method',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Filter by HTTP method',
        );

        $this->addOption(
            name: 'name',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Filter by name',
        );

        $this->addOption(
            name: 'path',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Filter by path',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project');
        $filterMethod = $input->getOption('method');
        $filterName = $input->getOption('name');
        $filterPath = $input->getOption('path');

        $srcPath = ROOT_DIR . "/src/$project";
        if (!is_dir($srcPath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        new ApplicationPaths($this->container)->register($project);

        try {
            $router = $this->container->get(RouterManager::class);
        } catch (\Throwable $e) {
            Output::error('Unable to load RouterManager: ' . $e->getMessage());
            return ExitCode::FAILURE;
        }

        $routes = $router->getRoutes()->all();
        $rows = $this->filterRoutes($routes, $filterMethod, $filterName, $filterPath);

        if (empty($rows)) {
            Output::warning('No routes found.');
            return ExitCode::SUCCESS;
        }

        usort($rows, fn($a, $b) => strcmp($a['path'], $b['path']));
        $this->renderTable($rows, $project, $filterMethod, $filterName, $filterPath);

        return ExitCode::SUCCESS;
    }

    /**
     * @param array<string, array<string, array{name: string, controller: string, action: string}>> $routes
     * @return list<array{method: string, path: string, name: string, controller: string, action: string}>
     */
    private function filterRoutes(array $routes, ?string $m, ?string $n, ?string $p): array
    {
        $rows = [];
        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $path => $info) {
                if ($m && strtoupper($m) !== strtoupper($method)) continue;
                if ($n && !str_contains($info['name'], $n)) continue;
                if ($p && !str_contains($path, $p)) continue;

                $rows[] = [
                    'method' => $method,
                    'path' => $path,
                    'name' => $info['name'],
                    'controller' => $info['controller'],
                    'action' => $info['action'],
                ];
            }
        }
        return $rows;
    }

    /**
     * @param list<array{method: string, path: string, name: string, controller: string, action: string}> $rows
     */
    private function renderTable(array $rows, string $project, ?string $m, ?string $n, ?string $p): void
    {
        $title = "Routes for $project (" . count($rows) . ")";
        Output::title($title);

        foreach ($rows as $row) {
            $color = self::METHOD_COLORS[$row['method']] ?? 'white';
            $method = Output::colorize(str_pad($row['method'], 7), $color);
            $path = Output::colorize(str_pad($row['path'], 40), 'white');
            $name = Output::colorize(str_pad($row['name'], 35), 'dim');
            $ctrl = Output::colorize($row['controller'] . '::' . $row['action'], 'dim');

            echo "  $method $path $name $ctrl\n";
        }
        Output::newLine();
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}