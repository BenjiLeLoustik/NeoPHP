# Profiler

Le module `Profiler` est un outil de débogage visuel intégré à NeoPHP. Il collecte des métriques de requête (durée, mémoire, requêtes SQL, événements, routes, logs, utilisateur authentifié...) et les affiche dans une barre de débogage flottante injectée automatiquement dans le HTML des réponses, uniquement en environnement `dev`.

---

## Sommaire

1. [Vue d'ensemble](#vue-densemble)
2. [ProfilerModule](#profilermodule)
3. [ProfilerManager](#profilermanager)
4. [CollectorInterface](#collectorinterface)
5. [La barre de débogage (Toolbar)](#la-barre-de-débogage-toolbar)
6. [ProfilerResponseListener](#profilerresponselistener)
7. [Créer un collecteur personnalisé](#créer-un-collecteur-personnalisé)
8. [Activation et conditions](#activation-et-conditions)

---

## Vue d'ensemble

```
Requête HTTP
     │
     ▼
ProfilerModule.init()
     ├── ProfilerManager::getInstance()
     ├── registerCollectors()   ← scan auto tous les *Collector.php
     ├── new Toolbar($profiler)
     └── EventDispatcher → ProfilerResponseListener

Réponse HTML
     │
     ▼
ProfilerResponseListener.onResponse()
     └── Toolbar.render()  →  injection avant </body>
```

---

## ProfilerModule

Fichier : `ProfilerModule.php`

### Dépendances déclarées

```php
public function dependencies(): array
{
    return [
        ResponseModule::class,
        EventModule::class,
        RouterModule::class,
        AuthModule::class,
        TranslationModule::class,
        ConfigModule::class,
    ];
}
```

### Conditions d'activation

Le profiler ne s'active que si **toutes** ces conditions sont remplies :

1. L'exécution n'est pas en CLI (`php_sapi_name() !== 'cli'`).
2. La clé `environment` dans `config/app.php` vaut `'dev'`.

```php
// Dans ProfilerModule::init()
$env = $container->get('profiler.configModule')->from('app')->get('environment') ?? 'prod';
if ($env !== 'dev') {
    return $profiler; // retour immédiat sans barre de débogage
}
```

Quand activé, la constante `NEO_PROFILER_ENABLED` est définie à `true`.

### Découverte automatique des collecteurs

Le module scanne récursivement tout le répertoire `neo/Core/` à la recherche des fichiers dont le nom se termine par `Collector.php`. Chaque classe trouvée qui implémente `CollectorInterface` est instanciée via le conteneur DI et ajoutée au `ProfilerManager`.

```php
// Pattern de recherche
'/^.+Collector\.php$/i'
```

Si le collecteur implémente également `CollectorAwareInterface`, sa méthode `boot(Container $container)` est appelée pour permettre une initialisation avancée (ex. attacher des listeners d'événements).

---

## ProfilerManager

Fichier : `ProfilerManager.php`

Le `ProfilerManager` est un **singleton** qui centralise la collecte des métriques. Il est accessible globalement via `ProfilerManager::getInstance()`.

### Initialisation du temps et de la mémoire

À la construction, le manager utilise les constantes globales du framework si elles sont définies, sinon il prend les valeurs courantes :

```php
$this->startTime = defined('NEO_START_TIME')
    ? NEO_START_TIME
    : microtime(true);

$this->startMemory = defined('NEO_START_MEMORY')
    ? NEO_START_MEMORY
    : memory_get_usage(true);
```

### API publique

```php
// Singleton
$profiler = ProfilerManager::getInstance();
ProfilerManager::reset(); // réinitialise pour les tests

// Collecteurs
$profiler->addCollector(CollectorInterface $collector): void;
$profiler->getCollector('sql'): ?CollectorInterface;
$profiler->getCollectors(): array; // ['sql' => ..., 'router' => ...]

// Métriques globales
$profiler->getTotalDuration(): float;  // en millisecondes
$profiler->getPeakMemory(): int;       // en octets (peak)
$profiler->getStartTime(): float;
$profiler->getStartMemory(): int;

// Collecte complète
$data = $profiler->collect();
// Retourne : ['duration' => 42.3, 'memory' => 2097152, 'sql' => [...], 'router' => [...], ...]
```

### Collecte des données

```php
public function collect(): array
{
    $data = [
        'duration' => round($this->getTotalDuration(), 2), // ms
        'memory'   => $this->getPeakMemory(),              // octets
    ];

    foreach ($this->collectors as $name => $collector) {
        $data[$name] = $collector->collect();
    }

    return $data;
}
```

---

## CollectorInterface

Fichier : `Interface/CollectorInterface.php`

Tout collecteur de métriques doit implémenter cette interface :

```php
namespace Neo\Core\Profiler\Interface;

interface CollectorInterface
{
    /**
     * Identifiant unique du collecteur (utilisé comme clé dans les données).
     */
    public function getName(): string;

    /**
     * Collecte et retourne les données brutes.
     * @return array<string, mixed>
     */
    public function collect(): array;

    /**
     * Rendu HTML de l'onglet dans la barre de débogage.
     * @param array<string, mixed> $data
     */
    public function renderTab(array $data): string;

    /**
     * Rendu HTML du panneau déroulant (détails).
     * @param array<string, mixed> $data
     */
    public function renderPanel(array $data): string;
}
```

### Interface optionnelle : CollectorAwareInterface

Si un collecteur a besoin d'accéder au conteneur DI à l'initialisation (pour attacher des listeners, etc.), il peut implémenter `CollectorAwareInterface` :

```php
interface CollectorAwareInterface
{
    public function boot(Container $container): void;
}
```

---

## La barre de débogage (Toolbar)

Fichier : `Toolbar/Toolbar.php`

La `Toolbar` est une classe `readonly` qui génère le HTML complet de la barre de débogage à partir des données collectées par le `ProfilerManager`.

### Structure visuelle

```
┌─────────────────────────────────────────────────────────────────────┐
│ [Neo] │ Response: 42ms │ Memory: 8.2 MB │ [SQL: 3] │ [Router] │ ... │
└─────────────────────────────────────────────────────────────────────┘
                      ↕ clic sur un onglet
┌─────────────────────────────────────────────────────────────────────┐
│ [SQL] [Router] [Auth] [Events] [Logs]                         [✕]  │
│                                                                     │
│  (contenu du panneau sélectionné)                                   │
└─────────────────────────────────────────────────────────────────────┘
```

### Couleur de durée

La durée d'exécution est colorisée selon des seuils :

| Durée | Couleur |
|---|---|
| < 200 ms | Vert (`#4ade80`) |
| 200 - 499 ms | Orange (`#fb923c`) |
| >= 500 ms | Rouge (`#f87171`) |

### Persistance de l'état

La barre mémorise son état ouvert/fermé dans le `localStorage` du navigateur sous la clé `neo_bar_visible`. L'état est restauré à chaque chargement de page.

### Rendu

```php
$toolbar = new Toolbar($profiler);
$html = $toolbar->render(); // retourne tout le HTML + CSS + JS inline
```

---

## ProfilerResponseListener

Fichier : `Listener/ProfilerResponseListener.php`

Ce listener écoute l'événement `ResponseEvent` et injecte la barre de débogage dans la réponse HTML.

### Conditions d'injection

Le listener **ne modifie pas** la réponse si :

- La réponse est un `RedirectResponse`.
- La réponse est un `JsonResponse`.
- Le `Content-Type` ne contient pas `text/html`.

### Stratégie d'injection

```php
public function onResponse(ResponseEvent $event): void
{
    // ...vérifications...

    $toolbar = $this->toolbar->render();

    if (str_contains($content, '</body>')) {
        // Injection propre avant la fermeture du body
        $content = str_replace('</body>', $toolbar . '</body>', $content);
    } else {
        // Fallback : ajout en fin de contenu
        $content .= $toolbar;
    }

    $response->setContent($content);
    $event->setResponse($response);
}
```

---

## Créer un collecteur personnalisé

Pour ajouter un collecteur au profiler, il suffit de créer une classe dont le nom se termine par `Collector.php` dans n'importe quel sous-dossier de `neo/Core/`. Elle sera découverte et enregistrée automatiquement.

```php
<?php
declare(strict_types=1);

namespace Neo\Core\MyFeature\Collector;

use Neo\Core\Profiler\Interface\CollectorInterface;

class MyFeatureCollector implements CollectorInterface
{
    private array $events = [];

    public function record(string $message): void
    {
        $this->events[] = $message;
    }

    public function getName(): string
    {
        return 'myfeature'; // clé dans les données collectées
    }

    public function collect(): array
    {
        return [
            'count'  => count($this->events),
            'events' => $this->events,
        ];
    }

    public function renderTab(array $data): string
    {
        $count = $data['count'] ?? 0;
        return sprintf(
            '<div class="n-tab" onclick="neoSwitch(\'myfeature\')">
                <span class="n-label">MyFeature</span>
                <span class="n-badge">%d</span>
            </div>',
            $count
        );
    }

    public function renderPanel(array $data): string
    {
        if (empty($data['events'])) {
            return '<div class="n-empty">Aucun événement.</div>';
        }

        $rows = '';
        foreach ($data['events'] as $event) {
            $rows .= '<tr><td>' . htmlspecialchars($event) . '</td></tr>';
        }

        return '<table><thead><tr><th>Message</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }
}
```

### Utiliser le collecteur depuis un autre service

Puisque le `ProfilerManager` est un singleton, n'importe quel service peut y accéder pour enregistrer des données :

```php
if (defined('NEO_PROFILER_ENABLED') && NEO_PROFILER_ENABLED) {
    $collector = ProfilerManager::getInstance()->getCollector('myfeature');
    $collector?->record('Quelque chose est arrivé');
}
```

---

## Activation et conditions

| Condition | Comportement |
|---|---|
| `environment = prod` | Profiler désactivé, `ProfilerManager` retourné sans collecteurs |
| `environment = dev` | Profiler activé, barre injectée |
| Exécution CLI | Profiler désactivé inconditionnellement |
| Réponse JSON ou redirect | Barre non injectée même en `dev` |
| Classe dans `\Tests\` ou `\Fixture\` | Ignorée par le scan de collecteurs |
