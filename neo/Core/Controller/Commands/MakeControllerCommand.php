<?php
declare(strict_types=1);

namespace Neo\Core\Controller\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(
    name: 'make:controller',
    description: 'Create a web or API Controller for a project'
)]
final class MakeControllerCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $controller = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $directory = Args::option($args, '-d') ?? Args::option($args, '--dir');
        $isApi = Args::flag($args, '--api');
        $force = Args::flag($args, '--force');

        if (!$controller || !$project) {
            Output::error('Missing arguments.');
            Output::muted('Usage: php bin/neo make:controller <ControllerName> --project=<name>');
            return;
        }

        $controller = $this->normalizeControllerName($controller);
        $directory = $directory ? Fs::normalizeDir($directory) : null;
        $basePath = ROOT_DIR . "/src/$project";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' does not exist inside ./src/");
            return;
        }

        $this->generateController($basePath, $project, $controller, $directory, $isApi, $force);

        if (!$isApi) {
            $this->generateView($basePath, $controller, $directory, $force);
        }

        Output::success("Controller '$controller' generated for project '$project'.");
    }

    private function generateController(
        string $basePath,
        string $projectNs,
        string $controller,
        ?string $directory,
        bool $isApi,
        bool $force
    ): void {
        $controllerDir = "$basePath/App/Controllers";
        $namespace = "Neo\\Src\\$projectNs\\App\\Controllers";

        if ($directory) {
            $controllerDir .= "/$directory";
            $namespace .= '\\' . str_replace('/', '\\', $directory);
        }

        Fs::ensureDir($controllerDir);

        $path = "$controllerDir/$controller.php";

        if (file_exists($path) && !$force) {
            Output::warning("Controller already exists. Use --force to overwrite.");
            return;
        }

        $routePath = $this->buildRoutePath($directory, $controller);
        $routeName = str_replace('/', '.', $routePath);

        $methodBody = $isApi
            ? <<<PHP
public function index(): \Neo\Core\Http\Response\JsonResponse
    {
        // return \$this->json(array|object \$data, int \$status = 200): JsonResponse;
        // return \$this->jsonError(string \$message, int \$status = 400, array \$extra = []): JsonResponse;
        // return \$this->jsonSuccess(array|object \$data = [], int \$status = 200): JsonResponse;
        return \$this->jsonSuccess(['success' => true], 200);
    }
PHP
            : <<<PHP
public function index(): \Neo\Core\Http\Response\Response
    {
        return \$this->render('pages/$routePath/index.html.twig', []);
    }
PHP;

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;

#[MainRoute(path: '/$routePath', name: '$routeName')]
final class $controller extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    $methodBody
}
PHP;

        file_put_contents($path, $content);
    }

    private function generateView(
        string $basePath,
        string $controller,
        ?string $directory,
        bool $force
    ): void {
        $routePath = $this->buildRoutePath($directory, $controller);
        $dir = "$basePath/App/Views/pages/$routePath";

        Fs::ensureDir($dir);

        $file = "$dir/index.html.twig";

        if (file_exists($file) && !$force) {
            return;
        }

        $content = <<<TWIG
{% extends 'layouts/base_layout.html.twig' %}

{% block content %}
<h1>$controller</h1>
{% endblock %}
TWIG;

        file_put_contents($file, $content);
    }

    private function buildRoutePath(?string $directory, string $controller): string
    {
        $base = lcfirst(preg_replace('/Controller$/', '', $controller));

        if (!$directory) {
            return $base;
        }

        return strtolower(trim($directory . '/' . $base, '/'));
    }

    private function normalizeControllerName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));
        $input = preg_replace('/Controller$/i', '', $input);
        return $input . 'Controller';
    }

    public function getName(): string
    {
        return 'make:controller';
    }

    public function getDescription(): string
    {
        return 'Create a web or API Controller for a project';
    }

    public function getHelp(): string
    {
        Output::usage('make:controller', $this->getDescription());
        Output::option('<ControllerName>',     'Controller class name (e.g. UserController)');
        Output::option('--project=<name>',     'Target project inside ./src/');
        Output::option('-d, --dir <directory>', 'Create inside a sub-folder (e.g. User)');
        Output::option('--api',                'Generate an API controller (JsonResponse)');
        Output::option('--force',              'Overwrite existing files');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:controller UserController --project=NeoAdmin');
        Output::example('php bin/neo make:controller UserController -d User --api --project=NeoAdmin');

        return '';
    }
}