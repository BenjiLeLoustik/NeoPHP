<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Interface\CommandInterface;
use Neo\Core\Console\Attribute\Command;

#[Command(
    name: 'make:event:listener',
    description: 'Créer un Listener pour un Event dans un projet'
)]
final class MakeEventListenerCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:event:listener';
    }

    public function getDescription(): string
    {
        return 'Créer un Listener pour un Event dans un projet';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : make:event:listener
Description : Crée un Listener associé à un Event pour un projet.

Usage :
  php bin/neo make:event:listener <ListenerName> --event=EventName [options] --project=NomDuProjet

Arguments :
  ListenerName             Nom du listener à créer (ex : SendWelcomeEmail)
                           Le suffixe "Listener" sera ajouté automatiquement si absent.

Options :
  --event=EventName        Nom de l'event écouté (ex : UserRegistered ou UserRegisteredEvent)
  --priority=N             Priorité du listener (défaut : 0, plus c'est haut plus c'est prioritaire)
  --force                  Écraser les fichiers existants
  --project=NomDuProjet    Nom du projet dans ./src/ où générer le listener

Exemples :
  php bin/neo make:event:listener SendWelcomeEmail --event=UserRegistered --project=NeoAdmin
    Crée le fichier ./src/NeoAdmin/App/Event/Listener/SendWelcomeEmailListener.php

  php bin/neo make:event:listener SendWelcomeEmail --event=UserRegistered --priority=10 --force --project=NeoAdmin
    Crée ou écrase avec une priorité de 10.

Notes :
- Le listener est automatiquement scanné au boot via l'attribut #[AsListener].
- La méthode handle() reçoit l'instance de l'event en paramètre.
- Plus la priorité est haute, plus le listener est exécuté tôt.
HELP;
    }

    public function execute(array $args): void
    {
        $listener = $args[0] ?? null;
        $project  = $this->getOption($args, '--project');
        $event    = $this->getOption($args, '--event');
        $priority = (int) ($this->getOption($args, '--priority') ?? 0);
        $force    = $this->hasFlag($args, '--force');

        if (!$listener || !$project || !$event) {
            echo "Usage : php bin/neo make:event:listener <ListenerName> --event=EventName [--priority=N] [--force] --project=ProjectName\n";
            return;
        }

        $listener  = $this->normalizeListenerName($listener);
        $event     = $this->normalizeEventName($event);
        $basePath  = ROOT_DIR . "/src/$project/App/Event/Listener";

        if (!is_dir($basePath) && !mkdir($basePath, 0777, true) && !is_dir($basePath)) {
            echo "Erreur : impossible de créer le répertoire '$basePath'.\n";
            return;
        }

        $path = "$basePath/$listener.php";

        if (file_exists($path) && !$force) {
            echo "Listener déjà existant (utilise --force pour écraser)\n";
            return;
        }

        $listenerNamespace = "Neo\\Src\\$project\\App\\Event\\Listener";
        $eventNamespace    = "Neo\\Src\\$project\\App\\Event";
        $eventClass        = $eventNamespace . '\\' . $event;

        $content = <<<PHP
<?php
declare(strict_types=1);

namespace $listenerNamespace;

use Neo\Core\DI\Container;
use Neo\Core\Event\Attribute\AsListener;
use $eventClass;

#[AsListener(event: $event::class, priority: $priority)]
final class $listener
{
    public function __construct(private Container \$container) {}

    public function handle($event \$event): void
    {
        // TODO : Logique du listener à implémenter
    }
}
PHP;

        if (file_put_contents($path, $content) === false) {
            echo "Erreur : impossible d'écrire le fichier '$path'.\n";
            return;
        }

        echo "Listener '$listener' généré avec succès pour l'event '$event' dans le projet '$project'.\n";
    }

    private function normalizeListenerName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));
        if (!str_ends_with($input, 'Listener')) {
            $input .= 'Listener';
        }
        return $input;
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