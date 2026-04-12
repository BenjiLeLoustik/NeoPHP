<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;

#[Command(
    name: 'make:controller',
    description: 'Créer un Controller (web ou API) pour un projet'
)]
final class MakeControllerCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:controller';
    }

    public function getDescription(): string
    {
        return 'Créer un Controller (web ou API) pour un projet';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : make:controller
Description : Crée un Controller web ou API pour un projet, avec option de génération de vue et force.

Usage :
  php bin/neo make:controller <ControllerName> [options] --project=NomDuProjet

Arguments :
  ControllerName          Nom du controller à créer (ex: UserController)

Options :
  -d, --dir Directory     Créer le controller dans un sous-dossier (ex: User)
  --api                    Générer un controller API (JsonResponse)
  --force                  Écraser le controller existant si présent
  --project=NomDuProjet    Nom du projet dans ./src/

Exemples :
  php bin/neo make:controller UserController --project=NeoAdmin
    Crée ./src/NeoAdmin/App/Controllers/UserController.php et ./src/NeoAdmin/App/Views/pages/user/index.html.twig

  php bin/neo make:controller UserController -d User --force --project=NeoAdmin
    Crée ./src/NeoAdmin/App/Controllers/User/UserController.php et la vue correspondante,
    écrase les fichiers existants si présents.

Notes :
- Le nom de la vue et la route sont générés automatiquement depuis le nom du controller.
- L'option --api génère une méthode index() retournant une JsonResponse.
HELP;
    }

    public function execute(array $args): void
    {
        $controller = $args[0] ?? null;
        $project    = $this->getOption($args, '--project');
        $directory  = $this->getOption($args, '-d') ?? $this->getOption($args, '--dir');

        $isApi  = $this->hasFlag($args, '--api');
        $force  = $this->hasFlag($args, '--force');

        if (!$controller || !$project) {
            echo "Usage : php bin/neo make:controller <ControllerName> [-d Directory] [--api] [--force] --project=ProjectName\n";
            return;
        }

        $controller = $this->normalizeControllerName($controller);
        $directory  = $directory ? $this->normalizeDirectory($directory) : null;

        $basePath = ROOT_DIR . "/src/$project";

        if (!is_dir($basePath)) {
            echo "Le projet '$project' n'existe pas dans ./src/\n";
            return;
        }

        $this->generateController(
            $basePath,
            $project,
            $controller,
            $directory,
            $isApi,
            $force
        );

        if (!$isApi) {
            $this->generateView(
                $basePath,
                $controller,
                $directory,
                $force
            );
        }

        echo "Controller '$controller' généré avec succès pour le projet '$project'.\n";
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
            $namespace     .= "\\" . str_replace('/', '\\', $directory);
        }

        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0777, true);
        }

        $path = "$controllerDir/$controller.php";

        if (file_exists($path) && !$force) {
            echo "Controller déjà existant (utilise --force pour écraser)\n";
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

use Neo\\Core\\Controller\\AbstractController;
use Neo\\Core\\Routing\\Attribute\\MainRoute;
use Neo\\Core\\Routing\\Attribute\\Route;

#[MainRoute(path: '/$routePath', name: '$routeName')]
final class $controller extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    $methodBody
}
PHP;

        file_put_contents($path, $content);
    }

    /* ==========================================================
       View generation
       ========================================================== */

    private function generateView(
        string $basePath,
        string $controller,
        ?string $directory,
        bool $force
    ): void {
        $routePath = $this->buildRoutePath($directory, $controller);
        $dir = "$basePath/App/Views/pages/$routePath";

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

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

    /* ==========================================================
       Helpers
       ========================================================== */

    private function buildRoutePath(?string $directory, string $controller): string
    {
        $base = lcfirst(preg_replace('/Controller$/', '', $controller));

        if (!$directory) {
            return $base;
        }

        return strtolower(trim($directory . '/' . $base, '/'));
    }

    private function hasFlag(array $args, string $flag): bool
    {
        return in_array($flag, $args, true);
    }

    private function getOption(array $args, string $option): ?string
    {
        $count = count($args);

        for ($i = 0; $i < $count; $i++) {
            if (str_starts_with($args[$i], $option . '=')) {
                return explode('=', $args[$i], 2)[1];
            }

            if ($args[$i] === $option && isset($args[$i + 1])) {
                return $args[$i + 1];
            }
        }

        return null;
    }

    private function normalizeControllerName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));
        $input = preg_replace('/Controller$/i', '', $input);

        return $input . 'Controller';
    }

    private function normalizeDirectory(string $dir): string
    {
        return trim($dir, '/');
    }
}
