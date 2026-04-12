<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;

#[Command(
    name: 'make:event',
    description: 'Créer un Event pour un projet'
)]
final class MakeEventCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:event';
    }

    public function getDescription(): string
    {
        return 'Créer un Event pour un projet';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : make:event
Description : Crée un Event pour un projet.

Usage :
  php bin/neo make:event <EventName> [options] --project=NomDuProjet

Arguments :
  EventName                Nom de l'event à créer (ex : UserRegistered)
                           Le suffixe "Event" sera ajouté automatiquement si absent.

Options :
  --force                  Écraser les fichiers existants
  --project=NomDuProjet    Nom du projet dans ./src/ où générer l'event

Exemples :
  php bin/neo make:event UserRegistered --project=NeoAdmin
    Crée le fichier ./src/NeoAdmin/App/Event/UserRegisteredEvent.php

  php bin/neo make:event UserRegistered --force --project=NeoAdmin
    Crée ou écrase le fichier ./src/NeoAdmin/App/Event/UserRegisteredEvent.php

Notes :
- Les events doivent étendre AbstractEvent.
- Les données de l'event sont passées via le constructeur.
HELP;
    }

    public function execute(array $args): void
    {
        $event   = $args[0] ?? null;
        $project = $this->getOption($args, '--project');
        $force   = $this->hasFlag($args, '--force');

        if (!$event || !$project) {
            echo "Usage : php bin/neo make:event <EventName> [--force] --project=ProjectName\n";
            return;
        }

        $event    = $this->normalizeEventName($event);
        $basePath = ROOT_DIR . "/src/$project/App/Event";

        if (!is_dir($basePath) && !mkdir($basePath, 0777, true) && !is_dir($basePath)) {
            echo "Erreur : impossible de créer le répertoire '$basePath'.\n";
            return;
        }

        $path = "$basePath/$event.php";

        if (file_exists($path) && !$force) {
            echo "Event déjà existant (utilise --force pour écraser)\n";
            return;
        }

        $namespace = "Neo\\Src\\$project\\App\\Event";

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Neo\Core\Event\AbstractEvent;

final class $event extends AbstractEvent
{
    public function __construct(
        // TODO : Datas
    ) {}
}
PHP;

        if (file_put_contents($path, $content) === false) {
            echo "Erreur : impossible d'écrire le fichier '$path'.\n";
            return;
        }

        echo "Event '$event' généré avec succès pour le projet '$project'.\n";
    }

    private function normalizeEventName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));
        if (!str_ends_with($input, 'Event')) {
            $input .= 'Event';
        }
        return $input;
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

    private function hasFlag(array $args, string $flag): bool
    {
        return in_array($flag, $args, true);
    }
}