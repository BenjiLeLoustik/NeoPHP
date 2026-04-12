<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;

#[Command(
    name: 'make:config',
    description: 'Créer un fichier de configuration pour un projet'
)]
final class MakeConfigCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:config';
    }

    public function getDescription(): string
    {
        return 'Créer un fichier de configuration pour un projet';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : make:config
Description : Génère un fichier de configuration interactif pour un projet.

Usage :
  php bin/neo make:config <ConfigName> --project=NomDuProjet

Arguments :
  ConfigName              Nom du fichier de config à créer (ex: mail → mail.config.php)

Options :
  --force                  Écraser le fichier existant si présent
  --project=NomDuProjet    Nom du projet dans ./src/

Exemples :
  php bin/neo make:config mail --project=NeoAdmin
    Crée ./src/NeoAdmin/Config/mail.config.php

Notes :
  - Le fichier sera nommé <ConfigName>.config.php
  - Les clés et valeurs sont saisies interactivement
  - Si aucune clé n'est saisie, génère un simple return []
HELP;
    }

    public function execute(array $args): void
    {
        $configName = $args[0] ?? null;
        $project    = $this->getOption($args, '--project');
        $force      = $this->hasFlag($args, '--force');

        if (!$configName || !$project) {
            echo "Usage : php bin/neo make:config <ConfigName> --project=ProjectName\n";
            return;
        }

        $configName = strtolower($configName);
        $basePath   = ROOT_DIR . "/src/$project";

        if (!is_dir($basePath)) {
            echo "Le projet '$project' n'existe pas dans ./src/\n";
            return;
        }

        $configDir  = "$basePath/Config";
        $configFile = "$configDir/$configName.config.php";

        if (file_exists($configFile) && !$force) {
            echo "Le fichier '$configName.config.php' existe déjà (utilise --force pour écraser).\n";
            return;
        }

        echo "Génération de '$configName.config.php' pour le projet '$project'.\n";
        echo "Entrez vos clés/valeurs (laissez le nom de la clé vide pour terminer) :\n\n";

        $entries = $this->collectEntries();

        if (!is_dir($configDir)) {
            mkdir($configDir, 0777, true);
        }

        $content = $this->buildFileContent($configName, $project, $entries);
        file_put_contents($configFile, $content);

        echo "\nFichier '$configName.config.php' généré avec succès.\n";
    }

    private function collectEntries(): array
    {
        $flat = [];

        echo "  (Notation pointée supportée : ftp.host, ftp.user, remote.domain…)\n\n";

        while (true) {
            $key = $this->prompt('  Nom de la clé (vide pour terminer) : ');

            if ($key === '') {
                break;
            }

            $value = $this->prompt("  Valeur pour '$key' : ");

            $flat[$key] = $value;

            echo "\n";
        }

        return $this->expandDotKeys($flat);
    }

    private function expandDotKeys(array $flat): array
    {
        $result = [];

        foreach ($flat as $key => $value) {
            $parts   = explode('.', $key);
            $current = &$result;

            foreach ($parts as $part) {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }

            $current = $value;
        }

        return $result;
    }

    private function buildFileContent(string $configName, string $project, array $entries): string
    {
        $filePath = "./src/$project/Config/$configName.config.php";
        $body     = $this->buildArray($entries, 1);

        return <<<PHP
<?php
declare(strict_types=1);

// $filePath

return $body;
PHP;
    }

    private function buildArray(array $entries, int $depth): string
    {
        if (empty($entries)) {
            return '[]';
        }

        $indent      = str_repeat('    ', $depth);
        $indentClose = str_repeat('    ', $depth - 1);
        $lines       = [];

        foreach ($entries as $key => $value) {
            $formattedKey   = $this->formatKey($key);
            $formattedValue = is_array($value)
                ? $this->buildArray($value, $depth + 1)
                : $this->formatValue($value);
            $lines[]        = "{$indent}{$formattedKey} => {$formattedValue},";
        }

        $body = implode("\n", $lines);

        return "[\n{$body}\n{$indentClose}]";
    }

    private function formatKey(string $key): string
    {
        return "'" . addslashes($key) . "'";
    }

    private function formatValue(string $value): string
    {
        if ($value === '') {
            return "''";
        }

        if (in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value);
        }

        if (is_numeric($value)) {
            return $value;
        }

        return "'" . addslashes($value) . "'";
    }

    private function prompt(string $message): string
    {
        echo $message;
        return trim(fgets(STDIN));
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