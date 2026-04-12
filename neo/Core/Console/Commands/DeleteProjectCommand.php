<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(name: 'delete:project', description: 'Supprime un projet NeoPHP')]
final class DeleteProjectCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'delete:project';
    }

    public function getDescription(): string
    {
        return 'Supprime un projet NeoPHP';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : delete:project
Description : Supprime un projet NeoPHP (sources, build et entrées composer).

Usage :
  php bin/neo delete:project --project=<NomDuProjet>

Options :
  --project=        Nom du projet cible dans ./src/ (requis)

Exemples :
  php bin/neo delete:project --project=MonProjet
  php bin/neo delete:project --project=TestComposer
HELP;
    }

    public function execute(array $args): void
    {
        $project = $this->getOption($args, '--project');

        if (!$project) {
            echo "Erreur : --project requis.\n";
            echo "Usage : php bin/neo delete:project --project=MonProjet\n";
            return;
        }

        echo "\nSuppression du projet '$project'...\n";

        // Confirmation
        echo "Attention : cette action est irreversible. Confirmer ? [oui/non] : ";
        $confirm = strtolower(trim(fgets(STDIN)));

        if ($confirm !== 'oui') {
            echo "Suppression annulee.\n";
            return;
        }

        $errors = 0;

        // --- [1/3] Suppression du build ---
        echo "\n[1/3] Suppression du build...\n";
        $buildDir = ROOT_DIR . "public/builds/$project";

        if (!is_dir($buildDir)) {
            echo "      [SKIP] Aucun build trouve : $buildDir\n";
        } else {
            $this->deleteDirectory($buildDir);
            echo "      Supprime : $buildDir\n";
        }

        // --- [2/3] Nettoyage du composer.json racine ---
        echo "[2/3] Nettoyage du composer.json...\n";
        $composerPath = ROOT_DIR . 'composer.json';

        if (!file_exists($composerPath)) {
            echo "      Erreur : composer.json introuvable : $composerPath\n";
            $errors++;
        } else {
            $composer = json_decode(file_get_contents($composerPath), true);

            if ($composer === null) {
                echo "      Erreur : composer.json invalide.\n";
                $errors++;
            } else {
                $changed = false;

                // Suppression dans require (cherche les clés dont la valeur pointe vers src/Project)
                // Le nom du package est dérivé du composer.json du projet
                $projectComposerPath = ROOT_DIR . "src/$project/composer.json";
                $packageName = null;

                if (file_exists($projectComposerPath)) {
                    $projectComposer = json_decode(file_get_contents($projectComposerPath), true);
                    $packageName = $projectComposer['name'] ?? null;
                }

                if ($packageName && isset($composer['require'][$packageName])) {
                    unset($composer['require'][$packageName]);
                    echo "      require : supprime '$packageName'\n";
                    $changed = true;
                } elseif ($packageName) {
                    echo "      [SKIP] '$packageName' absent de require.\n";
                } else {
                    echo "      [WARN] Impossible de determiner le nom du package (composer.json projet introuvable).\n";
                }

                // Suppression dans repositories (url = src/Project)
                $repoUrl = "src/$project";
                $before  = count($composer['repositories'] ?? []);
                $composer['repositories'] = array_values(array_filter(
                    $composer['repositories'] ?? [],
                    fn($r) => trim($r['url'] ?? '', '/') !== trim($repoUrl, '/')
                ));
                $after = count($composer['repositories']);

                if ($before !== $after) {
                    echo "      repositories : supprime entree '$repoUrl'\n";
                    $changed = true;
                } else {
                    echo "      [SKIP] Aucune entree repositories pour '$repoUrl'.\n";
                }

                if ($changed) {
                    file_put_contents(
                        $composerPath,
                        json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
                    );
                    echo "      composer.json mis a jour.\n";
                }
            }
        }

        // --- [3/3] Suppression du dossier src/Project ---
        echo "[3/3] Suppression de src/$project...\n";
        $srcDir = ROOT_DIR . "src/$project";

        if (!is_dir($srcDir)) {
            echo "      [SKIP] Dossier introuvable : $srcDir\n";
        } else {
            $this->deleteDirectory($srcDir);
            echo "      Supprime : $srcDir\n";
        }

        // --- Résumé ---
        echo "\n";
        if ($errors > 0) {
            echo "Projet '$project' supprime avec $errors erreur(s).\n";
        } else {

            echo "Projet '$project' supprime avec succes.\n";
            echo "Lancement de 'composer update'...\n";
            passthru('composer update --working-dir=' . escapeshellarg(ROOT_DIR) . ' --optimize-autoloader', $code);

            if ($code !== 0) {
                echo "Erreur : composer update a echoue.\n";
            } else {
                echo "composer update termine.\n";
            }

        }
    }

    private function deleteDirectory(string $dir): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($dir);
    }

    private function getOption(array $args, string $option): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "$option=")) {
                return explode('=', $arg, 2)[1];
            }
        }
        return null;
    }
}