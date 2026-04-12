<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

#[Command(
    name: 'asset:reload',
    description: 'Supprimer complètement les builds d\'un projet'
)]
final class AssetReloadCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'asset:reload';
    }

    public function getDescription(): string
    {
        return 'Supprimer complètement les builds d\'un projet';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : asset:reload
Description : Supprime complètement le dossier de builds d'un projet.

Usage :
  php bin/neo asset:reload --project=NomDuProjet

Options :
  --project=NomDuProjet   Nom du projet dont vous voulez supprimer les builds.

Exemple :
  php bin/neo asset:reload --project=NeoAdmin
    Supprime complètement le dossier ./public/builds/NeoAdmin

Attention :
- Cette opération supprime tous les fichiers dans le dossier de builds du projet.
- Utilisez cette commande uniquement si vous souhaitez régénérer complètement vos assets.
HELP;
    }

    public function execute(array $args): void
    {
        $project = $this->getOption($args, '--project');

        if (!$project) {
            echo "Usage : php bin/neo asset:reload --project=ProjectName\n";
            return;
        }

        $buildDir = ROOT_DIR . "/public/builds/$project";

        if (!is_dir($buildDir)) {
            echo "Le dossier de builds pour le projet '$project' n'existe pas.\n";
            return;
        }

        $this->deleteDir($buildDir);

        echo "Dossier de builds supprimé pour le projet '$project'.\n";
    }

    private function deleteDir(string $dir): void
    {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($dir);
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
