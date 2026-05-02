<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(name: 'serve', description: 'Lance un serveur PHP pour un projet NeoPHP')]
final class ServeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'serve';
    }

    public function getDescription(): string
    {
        return 'Lance un serveur PHP pour un projet NeoPHP';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : serve
Description : Lance un serveur PHP intégré pour un projet NeoPHP.

Usage :
  php bin/neo serve
  php bin/neo serve <ProjectName>

Comportement :
  - Sans argument : affiche la liste des projets disponibles
  - Avec argument : lance directement le serveur du projet

Pré-requis :
  - Chaque projet doit contenir : src/<Project>/app.config.php
  - La clé "access" doit exister dans ce fichier

Exemple app.config.php :
  return [
      'access' => '127.0.0.1:8000'
  ];
HELP;
    }

    public function execute(array $args): void
    {
        $projectArg = $args[0] ?? null;
        $projects = $this->getProjects();

        if (empty($projects)) {
            echo "Aucun projet trouvé dans ./src/\n";
            return;
        }

        if ($projectArg) {
            $this->runProject($projectArg, $projects);
            return;
        }

        echo "Projets disponibles :\n\n";

        $i = 1;
        $keys = [];

        foreach ($projects as $name => $config) {
            echo "[$i] $name\n";
            $keys[$i] = $name;
            $i++;
        }

        echo "\nChoisissez un projet : ";
        $choice = (int) trim(fgets(STDIN));

        if (!isset($keys[$choice])) {
            echo "Choix invalide.\n";
            return;
        }

        $this->runProject($keys[$choice], $projects);
    }

    private function runProject(string $project, array $projects): void
    {
        if (!isset($projects[$project])) {
            echo "Projet introuvable : $project\n";
            return;
        }

        $config = $projects[$project];

        if (!isset($config['access'])) {
            echo "Erreur : clé 'access' introuvable dans app.config.php\n";
            return;
        }

        $access = $config['access'];

        echo "Lancement du serveur pour $project...\n";
        echo "URL : http://$access\n\n";

        passthru("php -S $access -t public");
    }

    private function getProjects(): array
    {
        $src = ROOT_DIR . 'src/';
        $dirs = glob($src . '*', GLOB_ONLYDIR);

        $projects = [];

        foreach ($dirs as $dir) {
            $name = basename($dir);
            $configPath = $dir . '/Config/app.config.php';

            if (!file_exists($configPath)) {
                continue;
            }

            $config = include $configPath;

            if (!is_array($config)) {
                continue;
            }

            $projects[$name] = $config;
        }

        return $projects;
    }
}