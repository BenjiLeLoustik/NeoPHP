<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;

#[Command(name: 'sync:projects', description: 'Synchronise le composer.json racine avec tous les projets présents dans ./src/')]
final class SyncProjectsCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'sync:projects';
    }

    public function getDescription(): string
    {
        return 'Synchronise le composer.json racine avec tous les projets présents dans ./src/';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : sync:projects
Description : Relit tous les dossiers de ./src/ et s'assure que chacun est
              bien enregistré dans le composer.json racine (repository path + require @dev).
              Utile après un git pull qui remet le composer.json racine à zéro.

Usage :
  php bin/neo sync:projects

Options :
  --run-composer    Lance automatiquement `composer update` après la synchronisation

Exemples :
  php bin/neo sync:projects
  php bin/neo sync:projects --run-composer
HELP;
    }

    public function execute(array $args): void
    {
        $runComposer = in_array('--run-composer', $args, true);
        $srcDir = ROOT_DIR . '/src/';
        $rootComposerPath = ROOT_DIR . '/composer.json';

        if (!file_exists($rootComposerPath)) {
            echo "Erreur : composer.json racine introuvable à {$rootComposerPath}\n";
            return;
        }

        $projects = array_filter(
            glob($srcDir . '*', GLOB_ONLYDIR),
            fn(string $dir) => file_exists($dir . '/composer.json')
        );

        if (empty($projects)) {
            echo "Aucun projet trouvé dans ./src/ (ou aucun ne possède de composer.json).\n";
            return;
        }

        $synced = 0;
        $skipped = 0;

        foreach ($projects as $projectDir) {
            $name = basename($projectDir);

            $result = $this->registerInRootComposer($rootComposerPath, $name);

            if ($result) {
                echo "[OK]      $name ajouté au composer.json racine.\n";
                $synced++;
            } else {
                echo "[SKIP]    $name déjà présent, ignoré.\n";
                $skipped++;
            }
        }

        echo "\nSync terminé : $synced projet(s) ajouté(s), $skipped déjà présent(s).\n";

        if ($runComposer) {
            echo "\nLancement de composer update...\n";
            $output = shell_exec('composer update 2>&1');
            echo $output . "\n";
            echo "Composer update terminé.\n";
        } else {
            echo "Pensez à lancer : composer update\n";
            echo "Ou relancez avec : php bin/neo sync:projects --run-composer\n";
        }

    }

    private function registerInRootComposer(string $rootComposerPath, string $name): bool
    {
        $packageName = strtolower($name) . '/app';

        $rootComposer = json_decode(file_get_contents($rootComposerPath), true);

        $repositories = $rootComposer['repositories'] ?? [];
        $alreadyExists = array_filter(
            $repositories,
            fn($repo) => ($repo['url'] ?? '') === 'src/' . $name
        );

        if (!empty($alreadyExists)) {
            return false;
        }

        $rootComposer['minimum-stability'] = 'dev';
        $rootComposer['prefer-stable']     = true;

        $rootComposer['repositories'][] = [
            'type' => 'path',
            'url' => 'src/' . $name,
            'options' => ['symlink' => false],
        ];

        $rootComposer['require'][$packageName] = '@dev';

        file_put_contents(
            $rootComposerPath,
            json_encode($rootComposer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );

        return true;
    }
}