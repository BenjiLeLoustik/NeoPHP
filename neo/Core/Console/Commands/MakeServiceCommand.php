<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;

#[Command(
    name: 'make:service',
    description: 'Créer un Service pour un projet'
)]
final class MakeServiceCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:service';
    }

    public function getDescription(): string
    {
        return 'Créer un Service pour un projet';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : make:service
Description : Crée un Service pour un projet NeoPHP.

Usage :
  php bin/neo make:service <ServiceName> [options] --project=NomDuProjet

Arguments :
  ServiceName           Nom du service à créer (ex : Mail)
                        Le suffixe "Service" sera ajouté automatiquement si absent.

Options :
  -d, --dir Directory   Créer le service dans un sous-dossier (ex : Utils/Mail)
  --force               Écraser les fichiers existants
  --project=NomDuProjet Nom du projet dans ./src/ où générer le service

Exemples :
  php bin/neo make:service MailService --project=NeoAdmin
    Crée le fichier ./src/NeoAdmin/App/Services/MailService.php

  php bin/neo make:service Mail -d Utils --force --project=NeoAdmin
    Crée ou écrase le fichier ./src/NeoAdmin/App/Services/Utils/MailService.php

Notes :
- Les services sont des classes PHP simples, prêts à recevoir votre logique métier.
HELP;
    }

    public function execute(array $args): void
    {
        $service  = $args[0] ?? null;
        $project  = $this->getOption($args, '--project');
        $directory = $this->getOption($args, '-d') ?? $this->getOption($args, '--dir');
        $force     = $this->hasFlag($args, '--force');

        if (!$service || !$project) {
            echo "Usage : php bin/neo make:service <ServiceName> [-d Directory] [--force] --project=ProjectName\n";
            return;
        }

        $service   = $this->normalizeServiceName($service);
        $directory = $directory ? $this->normalizeDirectory($directory) : null;

        $basePath = ROOT_DIR . "/src/$project/App/Services";
        if ($directory) {
            $basePath .= "/$directory";
        }

        if (!is_dir($basePath)) {
            mkdir($basePath, 0777, true);
        }

        $path = "$basePath/$service.php";

        if (file_exists($path) && !$force) {
            echo "Service déjà existant (utilise --force pour écraser)\n";
            return;
        }

        $namespace = "Neo\\Src\\$project\\App\\Services";
        if ($directory) {
            $namespace .= "\\" . str_replace('/', '\\', $directory);
        }

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

final class $service
{
    // TODO: Ajouter la logique du service
}
PHP;

        file_put_contents($path, $content);

        echo "Service '$service' généré avec succès pour le projet '$project'.\n";
    }

    private function normalizeServiceName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));
        if (!str_ends_with($input, 'Service')) {
            $input .= 'Service';
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
