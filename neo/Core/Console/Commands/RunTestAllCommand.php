<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(
    name: 'run:test:all',
    description: 'Lancer tous les tests PHPUnit d\'un projet'
)]
final class RunTestAllCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'run:test:all';
    }

    public function getDescription(): string
    {
        return 'Lancer tous les tests PHPUnit d\'un projet';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : run:test:all
Description : Lance l'intégralité des tests PHPUnit d'un projet.

Usage :
  php bin/neo run:test:all --project=NomDuProjet [options]

Options :
  --format=console       Affichage console uniquement (défaut)
  --format=html          Génère un rapport HTML dans Storage/reports/
  --format=both          Affichage console + rapport HTML
  --coverage             Génère un rapport de couverture de code (nécessite Xdebug ou PCOV)
  --stop-on-failure      Arrête à la première erreur
  --project=NomDuProjet  Nom du projet dans ./src/

Exemples :
  php bin/neo run:test:all --project=Blog
  php bin/neo run:test:all --project=Blog --format=html
  php bin/neo run:test:all --project=Blog --format=both --coverage
  php bin/neo run:test:all --project=Blog --stop-on-failure
HELP;
    }

    public function execute(array $args): void
    {
        $project = $this->getOption($args, '--project');
        $format = strtolower($this->getOption($args, '--format') ?? 'console');
        $withCoverage = $this->hasFlag($args, '--coverage');
        $stopOnFailure = $this->hasFlag($args, '--stop-on-failure');

        if (!$project) {
            echo "Usage : php bin/neo run:test:all --project=NomDuProjet\n";
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

        $xmlConfig  = "$testsPath/phpunit.xml";

        if (!file_exists($xmlConfig)) {
            echo "Fichier phpunit.xml introuvable dans src/$project/Tests/. Lancez d'abord make:test.\n";
            return;
        }

        $reportsPath = "$basePath/Storage/reports";
        if (in_array($format, ['html', 'both'], true) || $withCoverage) {
            if (!is_dir($reportsPath)) {
                mkdir($reportsPath, 0777, true);
            }
        }

        $phpunitBin = ROOT_DIR . '/vendor/bin/phpunit';

        $cmd = escapeshellarg($phpunitBin);
        $cmd .= ' --configuration ' . escapeshellarg($xmlConfig);
        $cmd .= ' --colors=always';
        $cmd .= ' --testdox';

        if ($stopOnFailure) {
            $cmd .= ' --stop-on-failure';
        }

        if (in_array($format, ['html', 'both'], true)) {
            $cmd .= ' --log-junit ' . escapeshellarg("$reportsPath/junit.xml");
        }

        if ($withCoverage) {
            if ($this->hasCoverageDriver()) {
                $cmd .= ' --coverage-html ' . escapeshellarg("$reportsPath/coverage");
                $cmd .= ' --coverage-text';
            } else {
                echo "Avertissement : Xdebug ou PCOV requis pour la couverture de code. Option --coverage ignorée.\n";
            }
        }

        echo "Lancement des tests du projet '$project'...\n";
        echo str_repeat('=', 60) . "\n";

        $startTime = microtime(true);
        passthru($cmd, $exitCode);
        $duration = round(microtime(true) - $startTime, 2);

        echo str_repeat('=', 60) . "\n";
        echo "Durée totale : {$duration}s\n";

        if (in_array($format, ['html', 'both'], true)) {
            $this->generateHtmlSummary($reportsPath, $project, $exitCode, $duration);
        }

        echo match(true) {
            $exitCode === 0 => "Tous les tests sont passés.\n",
            $exitCode === 1 => "Terminé avec avertissements (warnings/deprecations).\n",
            default => "Des tests ont échoué (code $exitCode).\n",
        };
    }

    private function generateHtmlSummary(
        string $reportsPath,
        string $project,
        int $exitCode,
        float $duration
    ): void {
        $junitFile = "$reportsPath/junit.xml";

        if (!file_exists($junitFile)) {
            echo "Rapport HTML : fichier junit.xml absent, impossible de générer le rapport.\n";
            return;
        }

        $xml = simplexml_load_file($junitFile);
        $suite = $xml->testsuite ?? null;

        $tests = (int) ($suite['tests']    ?? 0);
        $failures = (int) ($suite['failures'] ?? 0);
        $errors = (int) ($suite['errors']   ?? 0);
        $skipped = (int) ($suite['skipped']  ?? 0);
        $passed = $tests - $failures - $errors - $skipped;

        $statusColor = match(true) {
            $exitCode === 0 => '#22c55e',
            $exitCode === 1 => '#f59e0b',
            default => '#ef4444',
        };

        $statusLabel = match(true) {
            $exitCode === 0 => 'SUCCÈS',
            $exitCode === 1 => 'AVERTISSEMENTS',
            default => 'ÉCHEC',
        };

        $date = date('d/m/Y H:i:s');

        $failuresList = '';
        foreach ($xml->xpath('//testcase[failure or error]') as $tc) {
            $name = (string) ($tc['name'] ?? '');
            $className = (string) ($tc['classname'] ?? '');
            $message = (string) ($tc->failure ?? $tc->error ?? '');
            $message = htmlspecialchars(substr($message, 0, 300));
            $failuresList .= "<div class='failure'><strong>{$className}::{$name}</strong><pre>{$message}</pre></div>";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Rapport de tests — {$project}</title>
        <style>
              body { font-family: system-ui, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 2rem; }
              h1 { font-size: 1.5rem; margin-bottom: 0.25rem; }
              .meta { color: #64748b; font-size: 0.9rem; margin-bottom: 2rem; }
              .badge { display: inline-block; padding: 0.3rem 1rem; border-radius: 999px; color: #fff; font-weight: 600; background: {$statusColor}; }
              .stats { display: flex; gap: 1.5rem; margin: 1.5rem 0; flex-wrap: wrap; }
              .stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.5rem; min-width: 100px; text-align: center; }
              .stat strong { display: block; font-size: 1.8rem; }
              .stat span { font-size: 0.8rem; color: #64748b; }
              .failures h2 { margin-top: 2rem; font-size: 1.1rem; }
              .failure { background: #fef2f2; border-left: 4px solid #ef4444; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; }
              .failure strong { display: block; margin-bottom: 0.5rem; }
              pre { white-space: pre-wrap; font-size: 0.8rem; color: #7f1d1d; margin: 0; }
              footer { margin-top: 3rem; font-size: 0.8rem; color: #94a3b8; }
        </style>
    </head>
    <body>
          <h1>Rapport de tests — {$project}</h1>
          <p class="meta">Généré le {$date} &bull; Durée : {$duration}s</p>
          <span class="badge">{$statusLabel}</span>
        
          <div class="stats">
            <div class="stat"><strong>{$tests}</strong><span>Total</span></div>
            <div class="stat" style="border-color:#86efac"><strong style="color:#16a34a">{$passed}</strong><span>Réussis</span></div>
            <div class="stat" style="border-color:#fca5a5"><strong style="color:#dc2626">{$failures}</strong><span>Échoués</span></div>
            <div class="stat" style="border-color:#fca5a5"><strong style="color:#dc2626">{$errors}</strong><span>Erreurs</span></div>
            <div class="stat" style="border-color:#fde68a"><strong style="color:#d97706">{$skipped}</strong><span>Ignorés</span></div>
          </div>
        
          <div class="failures">
                {$failuresList}
          </div>
        
          <footer>NeoPHP Testing — PHPUnit</footer>
    </body>
</html>
HTML;

        $reportFile = "$reportsPath/index.html";
        file_put_contents($reportFile, $html);
        echo "Rapport HTML généré : src/{$project}/Storage/reports/index.html\n";
    }

    private function hasCoverageDriver(): bool
    {
        return extension_loaded('xdebug') || extension_loaded('pcov');
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