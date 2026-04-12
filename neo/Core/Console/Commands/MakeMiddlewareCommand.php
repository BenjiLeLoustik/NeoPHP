<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;

#[Command(
    name: 'make:middleware',
    description: 'Créer un Middleware pour un projet'
)]
final class MakeMiddlewareCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:middleware';
    }

    public function getDescription(): string
    {
        return 'Créer un Middleware pour un projet';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : make:middleware
Description : Crée un Middleware simple pour un projet.

Usage :
  php bin/neo make:middleware <MiddlewareName> [options] --project=NomDuProjet

Arguments :
  MiddlewareName           Nom du middleware à créer (ex : Auth)
                           Le suffixe "Middleware" sera ajouté automatiquement si absent.

Options :
  -d, --dir Directory      Créer le middleware dans un sous-dossier (ex : Security/Auth)
  --force                  Écraser les fichiers existants
  --project=NomDuProjet    Nom du projet dans ./src/ où générer le middleware

Exemples :
  php bin/neo make:middleware AuthMiddleware --project=NeoAdmin
    Crée le fichier ./src/NeoAdmin/App/Middlewares/AuthMiddleware.php

  php bin/neo make:middleware Auth -d Security --force --project=NeoAdmin
    Crée ou écrase le fichier ./src/NeoAdmin/App/Middlewares/Security/AuthMiddleware.php

Notes :
- Les middlewares doivent implémenter MiddlewareInterface.
- La méthode handle() retourne un booléen pour indiquer si le middleware bloque la requête.
HELP;
    }

    public function execute(array $args): void
    {
        $middleware = $args[0] ?? null;
        $project    = $this->getOption($args, '--project');
        $directory  = $this->getOption($args, '-d') ?? $this->getOption($args, '--dir');
        $force      = $this->hasFlag($args, '--force');

        if (!$middleware || !$project) {
            echo "Usage : php bin/neo make:middleware <MiddlewareName> [-d Directory] [--force] --project=ProjectName\n";
            return;
        }

        $middleware = $this->normalizeMiddlewareName($middleware);
        $directory  = $directory ? $this->normalizeDirectory($directory) : null;

        $basePath = ROOT_DIR . "/src/$project/App/Middlewares";
        if ($directory) {
            $basePath .= "/$directory";
        }

        if (!is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }

        $path = "$basePath/$middleware.php";

        if (file_exists($path) && !$force) {
            echo "Middleware déjà existant (utilise --force pour écraser)\n";
            return;
        }

        $namespace = "Neo\\Src\\$project\\App\\Middlewares";
        if ($directory) {
            $namespace .= "\\" . str_replace('/', '\\', $directory);
        }

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\\Core\\Security\\Middleware\\Interface\\MiddlewareInterface;

final class $middleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        // TODO : Logique du middleware à implémenter
        return false;
    }
}
PHP;

        file_put_contents($path, $content);

        echo "Middleware '$middleware' généré avec succès pour le projet '$project'.\n";
    }

    private function normalizeMiddlewareName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));
        if (!str_ends_with($input, 'Middleware')) {
            $input .= 'Middleware';
        }
        return $input;
    }

    private function normalizeDirectory(string $dir): string
    {
        return trim($dir, '/');
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

    private function hasFlag(array $args, string $flag): bool
    {
        return in_array($flag, $args, true);
    }
}
