<?php
declare(strict_types=1);

namespace Neo\Core\Database\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Input;
use Neo\Core\Console\Helper\Output;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(
    name: 'make:crud',
    description: 'Create a full CRUD (Controller + Twig views) for an entity',
    category: 'Database'
)]
final class MakeCrudCommand implements CommandInterface
{
    public function execute(array $args): void
    {
        $entity = Args::positional($args, 0);
        $project = Args::option($args, '--project');
        $directory = Args::option($args, '-d') ?? Args::option($args, '--dir');
        $force = Args::flag($args, '--force');

        if (!$entity) {
            $entity = Input::ask('Entity name ?');
            if (!$entity) {
                Output::error('Entity name is required.');
                return;
            }
        }

        if (!$project) {
            $projects = $this->getAvailableProjects();

            if (empty($projects)) {
                Output::error('No projects found in ./src/');
                return;
            }

            $project = Input::choice('Target project ?', $projects);
        }

        if (!$directory) {
            $raw = Input::ask('Sub-folder ? (leave empty to skip)', '');
            $directory = $raw !== '' ? $raw : null;
        }

        $entity = Fs::pascalCase($entity);
        $directory = $directory ? Fs::normalizeDir($directory) : null;
        $basePath = ROOT_DIR . "/src/$project";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' does not exist inside ./src/");
            return;
        }

        Output::newLine();
        Output::title("Generating CRUD for '$entity'");
        Output::label('Project', $project);
        Output::label('Entity', $entity);
        Output::label('Sub-folder', $directory ?? '—');
        Output::newLine();

        $this->generateController($basePath, $project, $entity, $directory, $force);
        $this->generateViews($basePath, $entity, $directory, $force);

        Output::newLine();
        Output::success("CRUD '$entity' generated for project '$project'.");
    }

    private function getAvailableProjects(): array
    {
        $srcDir = ROOT_DIR . '/src/';

        if (!is_dir($srcDir)) {
            return [];
        }

        return array_map(
            fn(string $dir) => basename($dir),
            glob($srcDir . '*', GLOB_ONLYDIR) ?: []
        );
    }

    private function generateController(
        string $basePath,
        string $projectNs,
        string $entity,
        ?string $directory,
        bool $force
    ): void {
        $controllerDir = "$basePath/App/Controllers";
        $namespace = "Neo\\Src\\$projectNs\\App\\Controllers";

        if ($directory) {
            $controllerDir .= "/$directory";
            $namespace .= '\\' . str_replace('/', '\\', $directory);
        }

        Fs::ensureDir($controllerDir);

        $controllerName = $entity . 'Controller';
        $path = "$controllerDir/$controllerName.php";

        if (file_exists($path) && !$force) {
            if (!Input::confirm("Controller '$controllerName' already exists. Overwrite ?", false)) {
                Output::skip("Controller skipped.");
                return;
            }
        }

        $routePath = $this->buildRoutePath($directory, $entity);
        $routeName = str_replace('/', '.', $routePath);

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;

#[MainRoute(path: '/$routePath', name: '$routeName')]
final class $controllerName extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): \Neo\Core\Http\Response\Response
    {
        return \$this->render('pages/$routePath/index.html.twig', []);
    }

    #[Route(path: '/{id}', name: 'show', methods: ['GET'])]
    public function show(int \$id): \Neo\Core\Http\Response\Response
    {
        return \$this->render('pages/$routePath/show.html.twig', ['id' => \$id]);
    }

    #[Route(path: '/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(): \Neo\Core\Http\Response\Response
    {
        return \$this->render('pages/$routePath/create.html.twig');
    }

    #[Route(path: '/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function update(int \$id): \Neo\Core\Http\Response\Response
    {
        return \$this->render('pages/$routePath/edit.html.twig', ['id' => \$id]);
    }

    #[Route(path: '/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int \$id): \Neo\Core\Http\Response\Response
    {
        return \$this->redirectToRoute('$routeName.index');
    }
}
PHP;

        file_put_contents($path, $content);
        Output::muted("Controller created : $controllerName");
    }

    private function generateViews(
        string $basePath,
        string $entity,
        ?string $directory,
        bool $force
    ): void {
        $routePath = $this->buildRoutePath($directory, $entity);
        $dir = "$basePath/App/Views/pages/$routePath";

        Fs::ensureDir($dir);

        $views = [
            'index' => "<h1>List of $entity</h1>",
            'show' => "<h1>Detail $entity #{{ id }}</h1>",
            'create' => "<h1>Create $entity</h1>",
            'edit' => "<h1>Edit $entity #{{ id }}</h1>",
        ];

        foreach ($views as $name => $body) {
            $file = "$dir/$name.html.twig";

            if (file_exists($file) && !$force) {
                Output::skip("View already exists : $name.html.twig");
                continue;
            }

            file_put_contents($file, <<<TWIG
{% extends 'layouts/base_layout.html.twig' %}

{% block content %}
$body
{% endblock %}
TWIG);
            Output::muted("View created : $name.html.twig");
        }
    }

    private function buildRoutePath(?string $directory, string $entity): string
    {
        $base = lcfirst($entity);

        if (!$directory) {
            return $base;
        }

        return strtolower(trim($directory . '/' . $base, '/'));
    }

    public function getName(): string
    {
        return 'make:crud';
    }

    public function getDescription(): string
    {
        return 'Create a full CRUD (Controller + Twig views) for an entity';
    }

    public function getHelp(): string
    {
        Output::usage('make:crud', $this->getDescription());
        Output::option('<Entity>', 'Entity name (e.g. User)');
        Output::option('--project=<name>', 'Target project inside ./src/ (interactive selection if omitted)');
        Output::option('-d, --dir <directory>', 'Create inside a sub-folder (e.g. Admin)');
        Output::option('--force', 'Overwrite existing files');
        Output::newLine();
        echo "  Generated:\n";
        Output::muted('    Controllers/<Entity>Controller.php  (index, show, create, update, delete)');
        Output::muted('    Views/pages/<entity>/               (index, show, create, edit)');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:crud User --project=NeoAdmin');
        Output::example('php bin/neo make:crud User -d Admin --force --project=NeoAdmin');
        Output::example('php bin/neo make:crud');

        return '';
    }
}