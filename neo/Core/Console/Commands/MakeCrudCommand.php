<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;

#[Command(
    name: 'make:crud',
    description: 'Créer ou mettre à jour un CRUD (Controller + vues Twig) pour une entité'
)]
final class MakeCrudCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:crud';
    }

    public function getDescription(): string
    {
        return 'Créer ou mettre à jour un CRUD (Controller + vues Twig) pour une entité';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : make:crud
Description : Crée ou met à jour un CRUD complet (Controller + vues Twig) pour une entité d'un projet.

Usage :
  php bin/neo make:crud <Entity> [options] --project=NomDuProjet

Arguments :
  Entity                   Nom de l'entité pour laquelle générer le CRUD (ex : User)

Options :
  -d, --dir Directory      Créer le CRUD dans un sous-dossier (ex : Admin/User)
  --force                  Écraser les fichiers existants (Controller ou vues)
  --project=NomDuProjet    Nom du projet dans ./src/ pour lequel générer le CRUD

Exemples :
  php bin/neo make:crud User --project=NeoAdmin
    Crée le Controller ./src/NeoAdmin/App/Controllers/UserController.php
    et les vues Twig dans ./src/NeoAdmin/App/Views/pages/user/

  php bin/neo make:crud User -d Admin --force --project=NeoAdmin
    Crée le Controller ./src/NeoAdmin/App/Controllers/Admin/UserController.php
    et les vues Twig correspondantes, en écrasant les fichiers existants si présents.

Notes :
- Les méthodes générées : index(), show(), create(), update(), delete()
- Les vues générées : index.html.twig, show.html.twig, create.html.twig, edit.html.twig
- Les routes sont automatiquement mappées en fonction du nom de l'entité et du sous-dossier
HELP;
    }

    public function execute(array $args): void
    {
        $entity    = $args[0] ?? null;
        $project   = $this->getOption($args, '--project');
        $directory = $this->getOption($args, '-d') ?? $this->getOption($args, '--dir');
        $force     = $this->hasFlag($args, '--force');

        if (!$entity || !$project) {
            echo "Usage : php bin/neo make:crud <Entity> [-d Directory] [--force] --project=ProjectName\n";
            return;
        }

        $entity    = $this->pascalCaseSlug($entity);
        $directory = $directory ? $this->normalizeDirectory($directory) : null;

        $basePath = ROOT_DIR . "/src/$project";

        if (!is_dir($basePath)) {
            echo "Le projet '$project' n'existe pas dans ./src/\n";
            return;
        }

        $this->generateController($basePath, $project, $entity, $directory, $force);
        $this->generateViews($basePath, $entity, $directory, $force);

        echo "CRUD '$entity' généré ou mis à jour avec succès pour le projet '$project'.\n";
    }

    private function generateController(
        string $basePath,
        string $projectNs,
        string $entity,
        ?string $directory,
        bool $force
    ): void {
        $controllerDir = "$basePath/App/Controllers";
        $namespace     = "Neo\\Src\\$projectNs\\App\\Controllers";

        if ($directory) {
            $controllerDir .= "/$directory";
            $namespace     .= "\\" . str_replace('/', '\\', $directory);
        }

        if (!is_dir($controllerDir)) {
            mkdir($controllerDir, 0777, true);
        }

        $controllerName = $entity . 'Controller';
        $path           = "$controllerDir/$controllerName.php";

        if (file_exists($path) && !$force) {
            echo "Controller déjà existant (utilise --force pour écraser)\n";
            return;
        }

        $routePath = $this->buildRoutePath($directory, $entity);
        $routeName = str_replace('/', '.', $routePath);

        $methods = [
            'index' => <<<PHP

    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): \Neo\Core\Http\Response\Response
    {
        return \$this->view('pages/$routePath/index.html.twig', []);
    }
PHP,
            'show' => <<<PHP

    #[Route(path: '/{id}', name: 'show', methods: ['GET'])]
    public function show(int \$id): \Neo\Core\Http\Response\Response
    {
        return \$this->view('pages/$routePath/show.html.twig', ['id' => \$id]);
    }
PHP,
            'create' => <<<PHP

    #[Route(path: '/create', name: 'create', methods: ['GET', 'POST'])]
    public function create(): \Neo\Core\Http\Response\Response
    {
        return \$this->view('pages/$routePath/create.html.twig');
    }
PHP,
            'update' => <<<PHP

    #[Route(path: '/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function update(int \$id): \Neo\Core\Http\Response\Response
    {
        return \$this->view('pages/$routePath/edit.html.twig', ['id' => \$id]);
    }
PHP,
            'delete' => <<<PHP

    #[Route(path: '/{id}/delete', name: 'delete', methods: ['GET', 'POST'])]
    public function delete(int \$id): \Neo\Core\Http\Response\Response
    {
        return \$this->redirectToRoute('$routeName.index');
    }
PHP,
        ];

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
PHP;

        foreach ($methods as $m) {
            $content .= $m;
        }

        $content .= "\n}\n";

        file_put_contents($path, $content);
        echo "Controller créé : $controllerName\n";
    }

    private function generateViews(
        string $basePath,
        string $entity,
        ?string $directory,
        bool $force
    ): void {
        $routePath = $this->buildRoutePath($directory, $entity);
        $dir       = "$basePath/App/Views/pages/$routePath";

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $views = [
            'index'  => "<h1>Liste des $entity</h1>",
            'show'   => "<h1>Détail $entity #{{ id }}</h1>",
            'create' => "<h1>Créer $entity</h1>",
            'edit'   => "<h1>Modifier $entity #{{ id }}</h1>",
        ];

        foreach ($views as $name => $body) {
            $file = "$dir/$name.html.twig";
            if (file_exists($file) && !$force) {
                continue;
            }

            $content = <<<TWIG
{% extends 'layouts/base_layout.html.twig' %}

{% block content %}
$body
{% endblock %}
TWIG;

            file_put_contents($file, $content);
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

    private function pascalCaseSlug(string $string): string
    {
        $string = preg_replace('/[^a-zA-Z0-9]+/', ' ', $string);
        $string = ucwords(strtolower(trim($string)));
        return str_replace(' ', '', $string);
    }

    private function normalizeDirectory(string $dir): string
    {
        return trim($dir, '/');
    }
}
