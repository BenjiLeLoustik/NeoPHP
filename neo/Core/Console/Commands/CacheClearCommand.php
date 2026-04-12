<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

#[Command(
    name: 'cache:clear',
    description: 'Vider le cache d\'un projet'
)]
final class CacheClearCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'cache:clear';
    }

    public function getDescription(): string
    {
        return 'Vider le cache d\'un projet';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : cache:clear
Description : Vide complètement le cache d'un projet.

Usage :
  php bin/neo cache:clear --project=NomDuProjet

Options :
  --project=NomDuProjet   Nom du projet dont vous voulez vider le cache.

Exemple :
  php bin/neo cache:clear --project=NeoAdmin
    Vide tout le contenu du dossier ./src/NeoAdmin/Storage/var/cache

Attention :
- Cette opération supprime tous les fichiers du cache.
- Utilisez cette commande uniquement si vous souhaitez rafraîchir le cache.
HELP;
    }

    public function execute(array $args): void
    {
        $project = $this->getOption($args, '--project');

        if (!$project) {
            echo "Usage : php bin/neo cache:clear --project=ProjectName\n";
            return;
        }

        $cacheDir = ROOT_DIR . "/src/$project/Storage/var/cache";

        if (!is_dir($cacheDir)) {
            echo "Le cache pour le projet '$project' n'existe pas.\n";
            return;
        }

        $this->deleteDirContents($cacheDir);

        echo "Cache vidé pour le projet '$project'.\n";
    }

    private function deleteDirContents(string $dir): void
    {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
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
}
