# Gestion des Erreurs et Exceptions

Le module `Error` centralise la capture, la journalisation, la diffusion et le rendu de toutes les erreurs et exceptions survenant dans NeoPHP. Il distingue les environnements `dev` (trace complète) et `prod` (message générique sécurisé), et s'intègre avec les modules Logger, Event et View.

---

## Fichiers du module

| Fichier | Rôle |
|---|---|
| `ErrorManager.php` | Gestionnaire principal des erreurs et exceptions |
| `ErrorModule.php` | Module d'initialisation (enregistrement dans le conteneur) |
| `Exception/FrameworkException.php` | Exception de base enrichie du framework |

---

## FrameworkException

`Neo\Core\Error\Exception\FrameworkException` étend `\Exception` avec deux champs supplémentaires : un **titre** lisible et un **contexte** structuré.

### Constructeur

```php
new FrameworkException(
    title: 'Accès refusé',
    message: 'Vous n\'avez pas la permission d\'accéder à cette ressource.',
    code: 403,
    context: ['user_id' => 42, 'route' => '/admin'],
    previous: $previousException // optionnel
);
```

### Méthodes

```php
$e->getTitle();   // string : titre court (ex: "Accès refusé")
$e->getContext(); // array<string, mixed> : données de contexte
$e->getMessage(); // string : message détaillé (hérité de \Exception)
$e->getCode();    // int : code HTTP ou code d'erreur
```

### Conversion depuis un `Throwable` quelconque

```php
$frameworkException = FrameworkException::fromThrowable($e, 'Erreur personnalisée');
```

Le contexte inclut automatiquement `file`, `line`, `trace` et `previous` de l'exception d'origine.

---

## ErrorManager

`Neo\Core\Error\ErrorManager` est le gestionnaire central. Il s'enregistre comme handler PHP natif et orchestre : journalisation, diffusion d'événement, rendu de la réponse.

### Initialisation au bootstrap (avant le conteneur)

Pour les erreurs très précoces (avant que le conteneur soit disponible), un handler de secours statique peut être enregistré :

```php
ErrorManager::registerBootstrap();
```

Ce handler détecte automatiquement l'environnement (`dev` si `localhost` ou `127.0.0.1`, `prod` sinon) et rend une page HTML de secours.

### Initialisation complète (via le module)

```php
$errorManager = new ErrorManager($container);
$errorManager->setEnv('dev'); // ou 'prod'
$errorManager->register();    // installe set_exception_handler + set_error_handler
```

---

### Comportement de `handleException(Throwable $e)`

Le traitement suit quatre étapes dans l'ordre :

**1. Journalisation**

Tente d'écrire via le `LoggerModule` du conteneur dans le canal `framework`. En cas d'échec, écrit directement dans `storage/logs/framework.log`.

```
[2026-07-28 14:30:00] Neo\Core\Error\Exception\FrameworkException: Message ici
  in /app/src/Service/MyService.php:42
```

**2. Diffusion d'un événement**

Un `ExceptionEvent` est dispatché via l'`EventModule`, permettant à n'importe quel listener d'intercepter l'erreur.

**3. Rendu de la vue d'erreur**

Si un fichier `views/errors/{code}.html.twig` existe et que le `ViewModule` est disponible, la vue Twig est rendue avec les variables :

```twig
{# views/errors/404.html.twig #}
<h1>{{ title }}</h1>
<p>{{ message }}</p>
{% if context is not empty %}
    <pre>{{ context | json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>
{% endif %}
```

En `prod`, `message` est remplacé par un message générique selon le code HTTP, et `context` est vidé.

**4. Rendu de secours HTML**

Si aucune vue Twig n'est disponible, un HTML inline est généré.

---

### Rendu de secours HTML (inline)

La méthode statique `renderFallbackHtml()` produit une page HTML complète, auto-suffisante, sans dépendances externes.

**En mode `dev`** : affichage du nom de classe de l'exception, du fichier, de la ligne, de la stack trace (50 frames max), et du contexte.

**En mode `prod`** : message générique selon le code HTTP :

| Code | Message prod |
|---|---|
| 404 | The page you are looking for could not be found. |
| 403 | You do not have permission to access this resource. |
| 401 | You must be authenticated to access this resource. |
| 405 | The HTTP method used is not allowed for this route. |
| 419 | Your session has expired, please refresh the page. |
| 422 | The submitted data is invalid. |
| 429 | Too many requests, please try again in a few moments. |
| 5xx | An internal error has occurred, please try again later. |

La couleur de l'interface s'adapte au code :

| Plage | Couleur |
|---|---|
| 5xx | Orange (`#c2410c`) |
| 404 | Bleu (`#1d4ed8`) |
| 403 / 401 | Violet (`#7e22ce`) |
| Autres | Rouge (`#b91c1c`) |

---

### Gestion des erreurs PHP natives

`handleError()` convertit toute erreur PHP (`E_WARNING`, `E_NOTICE`, etc.) en `ErrorException`, puis délègue à `handleException()`. Les erreurs supprimées par `@` sont ignorées.

```php
// Toute erreur PHP native sera traitée comme une exception
trigger_error('Mon message', E_USER_WARNING);
```

---

## ErrorModule

`ErrorModule` implémente `ModuleInterface` et déclare les dépendances du module :

```php
// Dépendances déclarées
ConfigModule::class
EventModule::class
LoggerModule::class
ViewModule::class
```

Il enregistre `ErrorManager` dans le conteneur et l'initialise :

```php
// Enregistrement
$container->set(ErrorManager::class, fn(Container $c) => new ErrorManager($c));

// Initialisation : register() est appelé sauf en contexte de test (_NEO_TEST_PROJECT)
$errorHandler->register();
$errorHandler->setEnv($env); // lu depuis config app.environment
```

---

## Créer ses propres exceptions

Toutes les exceptions du framework héritent de `FrameworkException` :

```php
namespace App\Exception;

use Neo\Core\Error\Exception\FrameworkException;

class ValidationException extends FrameworkException
{
    public function __construct(array $errors)
    {
        parent::__construct(
            title: 'Données invalides',
            message: 'La validation a échoué.',
            code: 422,
            context: ['errors' => $errors]
        );
    }
}
```

```php
throw new ValidationException([
    'email' => 'Adresse email invalide.',
    'name'  => 'Le nom est requis.',
]);
```

L'`ErrorManager` capturera cette exception, la journalisera, et rendra la page `views/errors/422.html.twig` si elle existe.

---

## Intégration avec le Profiler

Si la constante `NEO_PROFILER_ENABLED` est définie et vraie, la toolbar du Profiler est injectée automatiquement dans le HTML de la page d'erreur, juste avant `</body>`.
