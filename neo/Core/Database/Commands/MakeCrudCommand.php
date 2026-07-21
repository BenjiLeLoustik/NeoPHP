<?php
declare(strict_types=1);

namespace Neo\Core\Database\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'make:crud',
    description: 'Create a full CRUD for an entity',
    category: 'Database',
)]
final class MakeCrudCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'entity',
            description: 'Entity name',
            mode: InputArgument::OPTIONAL,
        );

        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );

        $this->addOption(
            name: 'dir',
            shortcut: 'd',
            mode: InputOption::REQUIRED,
            description: 'Sub-folder',
        );

        $this->addOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Overwrite files',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $entity = $input->getArgument('entity') ?? Input::ask('Entity name ?');
        if (!$entity) return ExitCode::INVALID;

        $project = $input->getOption('project');
        $directory = $input->getOption('dir') ?? Input::ask('Sub-folder ?');
        $force = (bool) $input->getOption('force');

        $entity = Fs::pascalCase($entity);
        $directory = $directory !== '' ? Fs::normalizeDir($directory) : null;
        $basePath = ROOT_DIR . "/src/$project";

        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        $this->generateController($basePath, $project, $entity, $directory, $force);
        $this->generateViews($basePath, $entity, $directory, $force);

        Output::success("CRUD '$entity' generated.");
        return ExitCode::SUCCESS;
    }

    private function generateController(string $basePath, string $projectNs, string $entity, ?string $directory, bool $force): void
    {
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
            if (!Input::confirm("Controller '$controllerName' exists. Overwrite ?", false)) return;
        }

        $routePath = $this->buildRoutePath($directory, $entity);
        $routeName = str_replace('/', '.', $routePath);

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\Core\Http\Response\Types\Response;use Neo\Core\Routing\Attribute\MainRoute;

#[MainRoute(path: '/$routePath', name: '$routeName')]
final class $controllerName extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return \$this->render('pages/$routePath/index.html.twig', []);
    }

    #[Route(path: '/{id}', name: 'show', methods: ['GET'])]
    public function show(int \$id): Response
    {
        return \$this->render('pages/$routePath/show.html.twig', ['id' => \$id]);
    }

    #[Route(path: '/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(): Response
    {
        return \$this->render('pages/$routePath/create.html.twig');
    }

    #[Route(path: '/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function update(int \$id): Response
    {
        return \$this->render('pages/$routePath/edit.html.twig', ['id' => \$id]);
    }

    #[Route(path: '/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int \$id): Response
    {
        return \$this->redirectToRoute('$routeName.index');
    }
}
PHP;
        file_put_contents($path, $content);
    }

    private function generateViews(string $basePath, string $entity, ?string $directory, bool $force): void
    {
        $routePath = $this->buildRoutePath($directory, $entity);
        $dir = "$basePath/Templates/pages/$routePath";
        Fs::ensureDir($dir);

        $views = [
            'index' => "<h1>List of $entity</h1>",
            'show' => "<h1>Detail $entity #{{ id }}</h1>",
            'create' => "<h1>Create $entity</h1>",
            'edit' => "<h1>Edit $entity #{{ id }}</h1>",
        ];

        foreach ($views as $name => $body) {
            $file = "$dir/$name.html.twig";
            if (file_exists($file) && !$force) continue;

            file_put_contents($file, "{% extends 'layouts/base_layout.html.twig' %}\n\n{% block content %}\n$body\n{% endblock %}");
        }
    }

    private function buildRoutePath(?string $directory, string $entity): string
    {
        $base = lcfirst($entity);
        return $directory ? strtolower(trim($directory . '/' . $base, '/')) : $base;
    }

    protected function getAvailableProjects(): array
    {
        return array_map('basename', glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR));
    }
}