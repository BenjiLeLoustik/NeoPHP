<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\DI\Container;
use Neo\Core\Testing\Generator\TestGenerator;

#[Command(
    name: 'make:test:auto',
    description: 'Génère automatiquement les fichiers de tests depuis les attributs #[Test]'
)]
final class MakeTestAutoCommand implements CommandInterface
{
    public function __construct(private Container $container) {}

    public function getName(): string
    {
        return 'make:test:auto';
    }

    public function getDescription(): string
    {
        return 'Génère automatiquement les fichiers de tests depuis les attributs #[Test]';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : make:test:auto
Description : Scanne les classes annotées avec #[Test] et génère les fichiers de tests correspondants.

Usage :
  php bin/neo make:test:auto --project=NomDuProjet [options]

Options :
  --force               Écrase les fichiers de test existants
  --only=database       Génère uniquement un type (unit|feature|database|middleware)
  --dry-run             Affiche les fichiers qui seraient générés sans les créer

Exemples :
  php bin/neo make:test:auto --project=Blog
  php bin/neo make:test:auto --project=Blog --force
  php bin/neo make:test:auto --project=Blog --only=database
  php bin/neo make:test:auto --project=Blog --dry-run
HELP;
    }

    public function execute(array $args): void
    {
        $project = $this->getOption($args, '--project');
        $force = $this->hasFlag($args, '--force');
        $onlyType = $this->getOption($args, '--only');
        $dryRun = $this->hasFlag($args, '--dry-run');

        if (!$project) {
            echo "Utilisation : php bin/neo make:test:auto --project=NomDuProjet\n";
            return;
        }

        echo "Analyse des attributs #[Test] dans le projet '{$project}'...\n";
        echo str_repeat('=', 60) . "\n";

        $generator = new TestGenerator($this->container);
        $result = $generator->generate(
            force: $force,
            onlyType: $onlyType,
            dryRun: $dryRun,
        );

        if (!empty($result['generated'])) {
            echo ($dryRun ? "[SIMULATION] Fichiers qui seraient générés :\n" : "Fichiers générés :\n");
            foreach ($result['generated'] as $file) {
                echo "  + {$file}\n";
            }
        }

        if (!empty($result['skipped'])) {
            echo "\nIgnorés :\n";
            foreach ($result['skipped'] as $item) {
                echo "  ~ {$item}\n";
            }
        }

        if (empty($result['generated']) && empty($result['skipped'])) {
            echo "Aucun attribut #[Test] trouvé. Ajoutez #[Test] à vos classes ou méthodes.\n";
        }

        echo str_repeat('=', 60) . "\n";
        echo "Terminé.\n";
    }

    private function hasFlag(array $args, string $flag): bool
    {
        return in_array($flag, $args, true);
    }

    private function getOption(array $args, string $option): ?string
    {
        foreach ($args as $i => $arg) {
            if (str_starts_with($arg, $option . '=')) {
                return explode('=', $arg, 2)[1];
            }
            if ($arg === $option && isset($args[$i + 1])) {
                return $args[$i + 1];
            }
        }
        return null;
    }
}