<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;

#[Command(
    name: 'composer:require',
    description: 'Ajouter une dépendance composer dans un projet spécifique'
)]
final class ComposerRequireCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'composer:require';
    }

    public function getDescription(): string
    {
        return 'Ajouter une dépendance composer dans un projet spécifique';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : composer:require
Description : Ajoute une dépendance composer dans un projet spécifique et met à jour le composer racine.

Usage :
  php bin/neo composer:require <package/name> [version] --project=<NomDuProjet>

Arguments :
  package/name      Nom du package composer à installer (ex : stripe/stripe-php)
  version           Version du package (optionnel, ex : ^20.0, ~1.0, 2.0.*)

Options :
  --project=        Nom du projet cible dans ./src/

Exemples :
  php bin/neo composer:require stripe/stripe-php --project=MonProjet
  php bin/neo composer:require stripe/stripe-php ^20.0 --project=MonProjet
  php bin/neo composer:require symfony/mailer ~6.0 --project=TestComposer
HELP;
    }

    public function execute(array $args): void
    {
        $package = $args[0] ?? null;
        if (!$package) {
            echo "Usage : php bin/neo composer:require <package/name> [version] --project=<NomDuProjet>\n";
            return;
        }

        $version = '*';
        $projectName = null;

        foreach ($args as $index => $arg) {
            if ($index === 0) continue;

            if (str_starts_with($arg, '--project=')) {
                $projectName = substr($arg, strlen('--project='));
                continue;
            }

            if (!str_starts_with($arg, '--')) {
                $version = $arg;
            }
        }

        if (!$projectName) {
            echo "Erreur : l'option --project=<NomDuProjet> est obligatoire.\n";
            return;
        }

        $projectPath  = ROOT_DIR . '/src/' . $projectName;
        $composerPath = $projectPath . '/composer.json';

        if (!is_dir($projectPath)) {
            echo "Erreur : le projet '$projectName' n'existe pas dans ./src/\n";
            return;
        }

        if (!file_exists($composerPath)) {
            echo "Erreur : aucun composer.json trouvé dans ./src/$projectName/\n";
            return;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        if (isset($composer['require'][$package])) {
            echo "Le package '$package' est déjà présent dans le projet '$projectName' en version {$composer['require'][$package]}.\n";
            return;
        }

        $composer['require'][$package] = $version;

        file_put_contents(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        echo "Package '$package' ($version) ajouté dans ./src/$projectName/composer.json\n";

        echo "Lancement de composer update...\n";
        $output = shell_exec('composer update 2>&1');
        echo $output . "\n";
        echo "Composer update terminé.\n";
    }
}