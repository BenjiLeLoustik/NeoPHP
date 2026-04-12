<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(
    name: 'run:test',
    description: 'Lancer un test PHPUnit ciblé pour un projet'
)]
final class RunTestCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'run:test';
    }

    public function getDescription(): string
    {
        return 'Lancer un test PHPUnit ciblé pour un projet';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : run:test
Description : Lance un fichier de test PHPUnit précis pour un projet.

Usage :
  php bin/neo run:test <TestName> --project=NomDuProjet [options]

Arguments :
  TestName               Nom de la classe de test (ex: UserServiceTest)

Options :
  --filter=methodName    Filtre sur une méthode précise dans la classe
  --type=unit            Cherche dans Tests/Unit/ (défaut : cherche dans tous les dossiers)
  --project=NomDuProjet  Nom du projet dans ./src/

Exemples :
  php bin/neo run:test UserServiceTest --project=Blog
  php bin/neo run:test UserServiceTest --filter=test_example --project=Blog
  php bin/neo run:test UserRepositoryTest --type=database --project=Blog
HELP;
    }

    public function execute(array $args): void
    {
        $testName = $args[0] ?? null;
        $project = $this->getOption($args, '--project');
        $filter = $this->getOption($args, '--filter');
        $type = $this->getOption($args, '--type');

        if (!$testName || !$project) {
            echo "Usage : php bin/neo run:test <TestName> --project=NomDuProjet\n";
            return;
        }

        $basePath = ROOT_DIR . "/src/$project";
        $testsPath = "$basePath/Tests";

        if (!is_dir($basePath)) {
            echo "Le projet '$project' n'existe pas dans ./src/\n";
            return;
        }

        if (!is_dir($testsPath)) {
            echo "Aucun dossier Tests/ trouvé dans src/$project/. Lancez d'abord make:test.\n";
            return;
        }

        if (!$this->checkPhpUnit()) {
            return;
        }

        if (!str_ends_with($testName, 'Test')) {
            $testName .= 'Test';
        }

        $testFile = $this->findTestFile($testsPath, $testName, $type);

        if ($testFile === null) {
            echo "Fichier de test '$testName.php' introuvable dans src/$project/Tests/\n";
            return;
        }

        $phpunitBin = ROOT_DIR . '/vendor/bin/phpunit';
        $xmlConfig = "$testsPath/phpunit.xml";
        $relTestFile = $testFile;

        $cmd = escapeshellarg($phpunitBin);
        $cmd .= ' --configuration ' . escapeshellarg($xmlConfig);
        $cmd .= ' ' . escapeshellarg($relTestFile);

        if ($filter) {
            $cmd .= ' --filter ' . escapeshellarg($filter);
        }

        $cmd .= ' --colors=always';

        echo "Lancement du test : $testName\n";
        echo str_repeat('-', 60) . "\n";

        passthru($cmd, $exitCode);

        echo str_repeat('-', 60) . "\n";
        echo $exitCode === 0 ? "Terminé avec succès.\n" : "Des tests ont échoué (code $exitCode).\n";
    }

    private function findTestFile(string $testsPath, string $testName, ?string $type): ?string
    {
        $searchDirs = $type
            ? ["$testsPath/" . ucfirst(strtolower($type))]
            : ["$testsPath/Unit", "$testsPath/Feature", "$testsPath/Database", "$testsPath/Middleware"];

        foreach ($searchDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

            foreach ($iterator as $file) {
                if ($file->isFile()
                    && $file->getExtension() === 'php'
                    && $file->getBasename('.php') === $testName
                ) {
                    return $file->getRealPath();
                }
            }
        }

        return null;
    }

    private function checkPhpUnit(): bool
    {
        $phpunitBin = ROOT_DIR . '/vendor/bin/phpunit';

        if (!file_exists($phpunitBin)) {
            echo "PHPUnit introuvable. Lancez : composer require --dev phpunit/phpunit\n";
            return false;
        }

        return true;
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
}