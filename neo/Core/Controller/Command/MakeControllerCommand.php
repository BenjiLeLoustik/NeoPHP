<?php
declare(strict_types=1);

namespace Neo\Core\Controller\Command;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;

#[Command(
    name: 'make:controller',
    description: 'Create a web or API Controller for a project',
    category: 'Controller',
)]
final class MakeControllerCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'controller',
            description: 'Controller class name',
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
            name: 'api',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'API controller',
        );

        $this->addOption(
            name: 'force',
            shortcut: null,
            mode: InputOption::NONE,
            description: 'Overwrite file',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $controller = $input->getArgument('controller') ?? Input::ask('Controller name ?');

        if (!$controller) {
            Output::error('Controller name is required.');
            return ExitCode::INVALID;
        }

        $project = $input->getOption('project');
        $directory = $input->getOption('dir') ?? Input::ask('Sub-folder ?');
        $isApi = (bool) $input->getOption('api');
        $force = (bool) $input->getOption('force');

        $controller = $this->normalizeControllerName($controller);
        $basePath = ROOT_DIR . 'src/' . $project;

        if (!is_dir($basePath)) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        $directory = $directory !== '' ? $directory : null;

        $this->generateController($basePath, $project, $controller, $directory, $isApi, $force);

        if (!$isApi) {
            $this->generateView($basePath, $controller, $directory, $force);
        }

        Output::success("Controller '$controller' generated.");
        return ExitCode::SUCCESS;
    }

    private function generateController(
        string $basePath,
        string $projectNs,
        string $controller,
        ?string $directory,
        bool $isApi,
        bool $force
    ): void {
        $controllerDir = $basePath . '/App/Controller' . ($directory ? '/' . Fs::normalizeDir($directory) : '');
        $namespace = 'Neo\\Src\\' . $projectNs . '\\App\\Controller' . ($directory ? '\\' . str_replace('/', '\\', Fs::normalizeDir($directory)) : '');

        Fs::ensureDir($controllerDir);
        $path = $controllerDir . '/' . $controller . '.php';

        if (file_exists($path) && !$force) {
            return;
        }

        $routePath = $this->buildRoutePath($directory, $controller);
        $routeName = str_replace('/', '.', $routePath);

        $methodBody = $isApi
            ? "return \$this->jsonSuccess(['success' => true]);"
            : "return \$this->render('pages/$routePath/index.html.twig', []);";

        $returnType = $isApi ? 'JsonResponse' : 'Response';
        $useStatement = $isApi
            ? 'use Neo\Core\Http\Response\Types\JsonResponse;'
            : 'use Neo\Core\Http\Response\Types\Response;';

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;
$useStatement

#[MainRoute(path: '/$routePath', name: '$routeName')]
final class $controller extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): $returnType
    {
        $methodBody
    }
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
        $controllerDir = $basePath . '/App/Controller' . ($directory ? '/' . Fs::normalizeDir($directory) : '');
        $routePath = $this->buildRoutePath($directory, $controller);
        $viewDir = $basePath . '/Templates/pages/' . $routePath;

        Fs::ensureDir($viewDir);
        $path = $viewDir . '/index.html.twig';

        if (file_exists($path) && !$force) {
            return;
        }

        $content = <<<TWIG
<h1>Welcome to our $controller() !</h1>
<p><b>Controller :</b> $controllerDir/$controller.php</p>
<p><b>Template :</b> $path</p>
TWIG;

        file_put_contents($path, $content);
    }

    private function buildRoutePath(?string $directory, string $controller): string
    {
        $base = $controller
                |> (fn (string $c): string => preg_replace('/Controller$/', '', $c) ?? '')
                |> lcfirst(...);
        return $directory
            ? (
                ($directory . '/' . $base)
                    |> (fn (string $s): string => trim($s, '/'))
                    |> strtolower(...)
            )
            : $base;
    }

    private function normalizeControllerName(string $input): string
    {
        $input = $input
                |> (fn (string $s): string => preg_replace('/[^a-zA-Z0-9]+/', ' ', $s) ?? '')
                |> ucwords(...)
                |> (fn (string $s): string => str_replace(' ', '', $s));

        return (
            $input
                |> (fn (string $s): string => preg_replace('/Controller$/i', '', $s) ?? '')
            ) . 'Controller';
    }

    protected function getAvailableProjects(): array
    {
        return glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR)
                |> (fn (array $d): array => array_map(basename(...), $d));
    }
}