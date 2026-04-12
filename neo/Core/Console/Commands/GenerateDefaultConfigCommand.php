<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;

#[Command(
    name: 'generate:default:config',
    description: 'Générer les fichiers de configuration sensibles d\'un projet (deploy, database, api)'
)]
final class GenerateDefaultConfigCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'generate:default:config';
    }

    public function getDescription(): string
    {
        return 'Générer les fichiers de configuration sensibles d\'un projet (deploy, database, api)';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : generate:default:config
Description : Génère les fichiers de configuration sensibles absents du dépôt Git.

Usage :
  php bin/neo generate:default:config [--project=NomDuProjet]

Options :
  --project=NomDuProjet     Nom du projet cible dans ./src/ (optionnel)

Fichiers générés :
  - Config/deploy.config.php
  - Config/database.config.php
  - Config/api.config.php

Exemples :
  php bin/neo generate:default:config
    Liste les projets disponibles et demande lequel cibler.

  php bin/neo generate:default:config --project=NeoAdmin
    Génère les configs manquantes pour le projet NeoAdmin.

Notes :
- Ces fichiers sont listés dans .gitignore par défaut.
- Si un fichier existe déjà, une confirmation est demandée avant écrasement.
HELP;
    }

    public function execute(array $args): void
    {
        $projectName = $this->extractOption($args, 'project');

        if (!$projectName) {
            $projectName = $this->pickProjectInteractively();
            if (!$projectName) {
                return;
            }
        }

        $projectName = $this->pascalCaseSlug($projectName);
        $projectPath = ROOT_DIR . "/src/{$projectName}";

        if (!is_dir($projectPath)) {
            echo "Erreur : le projet '{$projectName}' n'existe pas dans ./src/\n";
            return;
        }

        $configPath = "{$projectPath}/Config/";

        if (!is_dir($configPath)) {
            mkdir($configPath, 0777, true);
        }

        echo "\nGénération des configs sensibles pour le projet : {$projectName}\n";
        echo str_repeat('-', 50) . "\n";

        $generated = 0;
        $skipped   = 0;

        $files = [
            'database.config.php' => fn() => $this->generateDatabaseConfig($configPath, $projectName),
            'deploy.config.php'   => fn() => $this->generateDeployConfig($configPath, $projectName),
            'api.config.php'      => fn() => $this->generateAPIConfig($configPath, $projectName),
        ];

        foreach ($files as $filename => $generator) {
            $filePath = $configPath . $filename;

            if (file_exists($filePath)) {
                echo "[!] {$filename} existe déjà. Écraser ? (o/N) : ";
                $answer = strtolower(trim(fgets(STDIN)));

                if ($answer !== 'o' && $answer !== 'oui') {
                    echo "    → Ignoré.\n";
                    $skipped++;
                    continue;
                }
            }

            $generator();
            echo "    [✓] {$filename} généré.\n";
            $generated++;
        }

        echo str_repeat('-', 50) . "\n";
        echo "Terminé : {$generated} fichier(s) généré(s), {$skipped} ignoré(s).\n\n";
    }

    private function pickProjectInteractively(): ?string
    {
        $srcDir  = ROOT_DIR . '/src/';
        $projects = [];

        foreach (glob($srcDir . '*', GLOB_ONLYDIR) as $dir) {
            $projects[] = basename($dir);
        }

        if (empty($projects)) {
            echo "Aucun projet trouvé dans ./src/\n";
            return null;
        }

        echo "\nProjets disponibles :\n";
        foreach ($projects as $i => $project) {
            echo "  [{$i}] {$project}\n";
        }

        echo "\nEntrez le numéro ou le nom du projet : ";
        $input = trim(fgets(STDIN));

        if (is_numeric($input) && isset($projects[(int)$input])) {
            return $projects[(int)$input];
        }

        if (in_array($input, $projects, true)) {
            return $input;
        }

        echo "Projet introuvable : '{$input}'\n";
        return null;
    }

    private function generateDatabaseConfig(string $path, string $name): void
    {
        $filename = 'database.config.php';
        $content  = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/database.config.php

return [

    'enabled' => false,
    'use' => "default",

    'connections' => [

        'default' => [
            'driver' => "mysql",
            'host' => "localhost",
            'port' => 3306,
            'user' => "",
            'pass' => "",
            'dbname' => "",
            'charset' => "utf8mb4",
            'prefix' => ""
        ]

    ]

];
PHP;
        file_put_contents($path . $filename, $content);
    }

    private function generateDeployConfig(string $path, string $name): void
    {
        $filename = 'deploy.config.php';
        $content  = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/deploy.config.php

return [
    'ftp' => [
        'host' => '',
        'user' => '',
        'pass' => ''
    ],
    'remote' => [
        'domain' => '', // exemple : your-app.fr
        'framework_dir' => '', // exemple : domains/your-app.fr/neo/
        'public_dir' => '' // exemple : domains/your-app.fr/public_html
    ]
];
PHP;
        file_put_contents($path . $filename, $content);
    }

    private function generateAPIConfig(string $path, string $name): void
    {
        $filename = 'api.config.php';
        $content  = <<<PHP
<?php
declare(strict_types=1);

// ./src/$name/Config/api.config.php

return [

    // Exemple :
    // 'stripe' => [
    //     'key' => '',
    //     'secret' => ''
    // ],

];
PHP;
        file_put_contents($path . $filename, $content);
    }

    private function extractOption(array $args, string $key): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "--{$key}=")) {
                return substr($arg, strlen("--{$key}="));
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
}