# Système d'Événements

Le module `Event` implémente un système d'événements (pub/sub) pour NeoPHP. Il permet de découpler les composants en émettant des événements que des listeners peuvent intercepter, avec gestion des priorités, arrêt de propagation, découverte automatique par attribut PHP 8, interface subscriber, et cache en production.

---

## Fichiers du module

| Fichier | Rôle |
|---|---|
| `EventManager.php` | Dispatcher central, découverte et exécution des listeners |
| `Abstract/AbstractEvent.php` | Classe de base pour tous les événements |
| `Attribute/AsListener.php` | Attribut PHP 8 pour déclarer un listener |
| `Interface/EventSubscriberInterface.php` | Interface pour les subscribers multi-événements |
| `Event/RequestEvent.php` | Exemple d'événement fourni par le framework |

---

## Créer un événement

Tout événement doit étendre `AbstractEvent`, qui implémente `EventInterface` et gère l'arrêt de propagation.

```php
namespace App\Event;

use Neo\Core\Event\Abstract\AbstractEvent;

class UserRegisteredEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $email,
        private readonly int $userId
    ) {}

    public function getEmail(): string  { return $this->email; }
    public function getUserId(): int    { return $this->userId; }
}
```

### Arrêt de propagation

```php
// Dans un listener, pour empêcher les listeners suivants (priorité inférieure) d'être appelés :
$event->stopPropagation();

// Vérifier l'état
if ($event->isPropagationStopped()) { /* ... */ }
```

---

## Créer un listener avec l'attribut `#[AsListener]`

Le listener doit être placé dans le dossier `listenersPath` configuré dans le conteneur. Il est découvert automatiquement par scan récursif au démarrage.

```php
namespace App\Listener;

use Neo\Core\Event\Attribute\AsListener;
use App\Event\UserRegisteredEvent;

#[AsListener(event: UserRegisteredEvent::class, priority: 10)]
class SendWelcomeEmailListener
{
    public function __construct(
        private readonly Mailer $mailer
    ) {}

    public function handle(UserRegisteredEvent $event): void
    {
        $this->mailer->send($event->getEmail(), 'Bienvenue !');
    }
}
```

**Paramètres de `#[AsListener]` :**

| Paramètre | Type | Description |
|---|---|---|
| `event` | `class-string` | FQCN de la classe d'événement écoutée |
| `priority` | `int` | Priorité (plus grande = exécutée en premier) |

La méthode appelée par défaut est `handle()`.

---

## Créer un subscriber (multi-événements)

Un subscriber implémente `EventSubscriberInterface` et déclare une map statique `événement → méthode`.

```php
namespace App\Listener;

use Neo\Core\Event\Interface\EventSubscriberInterface;
use App\Event\UserRegisteredEvent;
use App\Event\UserLoggedInEvent;

class UserSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserRegisteredEvent::class => 'onRegister',
            UserLoggedInEvent::class   => 'onLogin',
        ];
    }

    public function onRegister(UserRegisteredEvent $event): void
    {
        // ...
    }

    public function onLogin(UserLoggedInEvent $event): void
    {
        // ...
    }
}
```

Les subscribers sont également découverts automatiquement au scan si la classe implémente `EventSubscriberInterface`.

---

## Dispatcher un événement

```php
use App\Event\UserRegisteredEvent;

$event = new UserRegisteredEvent('alice@example.com', 1);
$dispatcher = $container->get(EventManager::class);

$returnedEvent = $dispatcher->dispatch($event);
```

Le dispatcher retourne l'événement après exécution, ce qui permet de récupérer des données que les listeners auraient pu y écrire.

---

## Enregistrer des listeners manuellement (runtime)

En complément de la découverte automatique, on peut enregistrer des listeners programmatiquement :

```php
// Par nom de classe (résolu par le conteneur à l'exécution)
$dispatcher->addListener(
    eventClass: UserRegisteredEvent::class,
    listenerClass: SendWelcomeEmailListener::class,
    priority: 5,
    method: 'handle' // optionnel, défaut: 'handle'
);

// Par instance directe
$dispatcher->addListenerInstance(
    eventClass: UserRegisteredEvent::class,
    instance: new AuditLogger(),
    method: 'handle',
    priority: 0
);

// Par subscriber
$dispatcher->addSubscriber(new UserSubscriber());
```

---

## Inspecter les listeners enregistrés

```php
// Tous les listeners, groupés par événement
$all = $dispatcher->getListeners();

// Listeners pour un événement spécifique
$list = $dispatcher->getListeners(UserRegisteredEvent::class);
// Retourne : [['class' => '...', 'priority' => 10, 'method' => 'handle'], ...]
```

---

## Découverte automatique et cache

Au démarrage, `EventManager` scanne récursivement le dossier `listenersPath`. Pour chaque fichier `.php`, il :

1. Extrait le FQCN (namespace + nom de classe)
2. Cherche l'attribut `#[AsListener]` sur la classe
3. Vérifie si la classe implémente `EventSubscriberInterface`
4. Trie les listeners par priorité décroissante

**En production** (`environment !== 'dev'`), le résultat est mis en cache dans :

```
storage/var/cache/events/listeners.php
```

Ce fichier est lu directement aux démarrages suivants, sans re-scan. En mode `dev`, le scan est toujours effectué à chaque requête.

---

## Événements fournis par le framework

### `RequestEvent`

Dispatché lors de la réception d'une requête HTTP. Contient l'objet `Request`.

```php
use Neo\Core\Event\Event\RequestEvent;
use Neo\Core\Event\Attribute\AsListener;

#[AsListener(event: RequestEvent::class, priority: 0)]
class RequestLoggerListener
{
    public function handle(RequestEvent $event): void
    {
        $request = $event->getRequest();
        error_log($request->getMethod() . ' ' . $request->getPath());
    }
}
```

### `ExceptionEvent`

Dispatché par `ErrorManager` lors de la capture d'une exception. Permet d'intercepter les erreurs applicatives (ex. : notification Slack, envoi d'email).

---

## Intégration Profiler

Si `NEO_PROFILER_ENABLED` est activé, chaque appel à `dispatch()` est chronométré et enregistré dans le collecteur `events` du Profiler, avec :
- le nom de la classe d'événement
- la liste des listeners appelés
- le temps d'exécution en millisecondes
