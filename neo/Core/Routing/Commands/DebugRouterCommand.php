<?php
declare(strict_types=1);

namespace Neo\Core\Routing\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;
use Neo\Core\DI\Container;
use Neo\Core\Routing\Router;

#[Command(
    name: 'debug:router',
    description: 'Display all registered routes for a project',
    category: 'Router'
)]
final class DebugRouterCommand extends AbstractCommand
{
    private const METHOD_COLORS = [
        'GET' => 'green',
        'POST' => 'yellow',
        'PUT' => 'cyan',
        'PATCH' => 'cyan',
        'DELETE' => 'red',
    ];

    public function __construct(
        private Container $container
    ) {}

    public function execute(array $args): void
    {
        $project = Args::option($args, '--project');
        $filterMethod = Args::option($args, '--method');
        $filterName = Args::option($args, '--name');
        $filterPath = Args::option($args, '--path');

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            $project = Input::choice('Target project ?', $projects);
        }

        $srcPath = ROOT_DIR . "/src/$project";

        if (!is_dir($srcPath)) {
            Output::error("Project '$project' not found in src/.");
            return;
        }

        $this->container->set('controllerNamespace', "Neo\\Src\\$project\\App\\Controllers");
        $this->container->set('controllersPath', $srcPath . '/App/Controllers');
        $this->container->set('storagePath', $srcPath . '/storage');

        try {
            $router = $this->container->get(Router::class);
        } catch (\Throwable $e) {
            Output::error('Unable to load Router: ' . $e->getMessage());
            return;
        }

        $routes = $router->getRoutes()->all();
        $rows = $this->filterRoutes($routes, $filterMethod, $filterName, $filterPath);

        if (empty($rows)) {
            Output::warning('No routes found matching the given filters.');
            return;
        }

        usort($rows, fn($a, $b) => strcmp($a['path'], $b['path']));

        $this->renderTable($rows, $project, $filterMethod, $filterName, $filterPath);
    }

    private function filterRoutes(
        array $routes,
        ?string $filterMethod,
        ?string $filterName,
        ?string $filterPath
    ): array {
        $rows = [];

        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $path => $info) {
                if ($filterMethod && strtoupper($filterMethod) !== strtoupper($method)) {
                    continue;
                }
                if ($filterName && !str_contains($info['name'], $filterName)) {
                    continue;
                }
                if ($filterPath && !str_contains($path, $filterPath)) {
                    continue;
                }

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

    private function renderTable(
        array $rows,
        string $project,
        ?string $filterMethod,
        ?string $filterName,
        ?string $filterPath
    ): void {
        $title = sprintf('Routes for %s (%d)', $project, count($rows));

        $filters = array_filter([
            $filterMethod ? 'method=' . strtoupper($filterMethod) : null,
            $filterName ? 'name~' . $filterName : null,
            $filterPath ? 'path~' . $filterPath : null,
        ]);

        if (!empty($filters)) {
            $title .= Output::colorize('  filters: ' . implode(', ', $filters), 'dim');
        }

        Output::title($title);

        foreach ($rows as $row) {
            $color = self::METHOD_COLORS[$row['method']] ?? 'white';
            $method = Output::colorize(str_pad($row['method'], 7), $color);
            $path = Output::colorize(str_pad($row['path'], 40), 'white');
            $name = Output::colorize(str_pad($row['name'], 35), 'dim');
            $ctrl = Output::colorize($row['controller'] . '::' . $row['action'], 'dim');

            echo "  {$method} {$path} {$name} {$ctrl}\n";
        }

        Output::newLine();
        Output::muted(count($rows) . ' route(s) listed.');
        Output::newLine();
    }

    public function getHelp(): string
    {
        Output::usage('debug:router', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('--method=<method>', 'Filter by HTTP method (GET, POST, PUT, PATCH, DELETE)');
        Output::option('--name=<name>', 'Filter by route name (partial match)');
        Output::option('--path=<path>', 'Filter by path (partial match)');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo debug:router --project=MyApp');
        Output::example('php bin/neo debug:router --project=MyApp --method=GET');
        Output::example('php bin/neo debug:router --project=MyApp --name=user');
        Output::example('php bin/neo debug:router');

        return '';
    }
}