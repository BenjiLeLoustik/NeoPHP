# NeoPHP

Framework PHP 8.5 centré sur :

- un noyau applicatif dans `neo/`
- une CLI interne dans `bin/neo`
- des projets applicatifs isolés dans `src/<Projet>/`

NeoPHP vise un autre point d'équilibre que Symfony ou Laravel.
L'objectif n'est pas d'empiler des couches, des bundles ou un ecosysteme très large, mais de fournir un noyau PHP lisible, compact et directement exploitable pour construire une application complete sans sortir du depot.
Le framework mise sur une structure simple, une CLI integrée, des modules coeur autodétéctes et un workflow multi-projets qui reste explicite.

En pratique, NeoPHP s'adresse surtout aux projets qui veulent aller vite sans adopter toute la complexite organisationnelle des gros frameworks généralistes.
Par rapport a Symfony, il réduit fortement la cérémonie de configuration et la fragmentation entre composants.
Par rapport a Laravel, il se montre plus minimal, plus direct dans son architecture, et moins dépendant d'une couche "magique" ou d'un ensemble d'outils externes.
Si le besoin est un framework plus petit, plus prévisible et plus facile a suivre de bout en bout dans le code source, c'est précisement le terrain de NeoPHP.

## Sommaire

- [Vue d'ensemble](#vue-densemble)
- [Architecture du depot](#architecture-du-dépôt)
- [Cartographie du coeur](#cartographie-du-coeur)
- [Cycle d'execution](#cycle-déxécution)
- [Structure d'un projet](#structure-dun-projet)
- [Conteneur DI et configuration](#conteneur-di-et-configuration)
- [Couche HTTP](#couche-http)
- [Routing et controleurs](#routing-et-contrôleurs)
- [Vues Twig, assets et traductions](#vues-twig-assets-et-traductions)
- [Base de donnees et QueryBuilder](#base-de-donnees-et-querybuilder)
- [ORM et repositories](#orm-et-repositories)
- [ORM Data Mapper (entités)](#orm-data-mapper-entités)
- [Seeding](#seeding)
- [Formulaires, upload et validation](#formulaires-upload-et-validation)
- [Securite: auth, mot de passe, middlewares, csrf](#securite-auth-mot-de-passe-middlewares-csrf)
- [Events](#events)
- [Crons](#crons)
- [Cache, logs, mailer, profiler et erreurs](#cache-logs-mailer-profiler-et-erreurs)
- [Markdown](#markdown)
- [CLI et generateurs](#cli-et-generateurs)
- [Tests PHPUnit](#tests-phpunit)
- [Deploiement](#deploiement)
- [Dependances et prerequis](#dépendances-et-prérequis)

## Vue d'ensemble

NeoPHP repose sur deux points d'entrée :

- `public/index.php` pour le runtime HTTP
- `bin/neo` pour la CLI

Le coeur passe par `Neo\App`, qui :

- détecte le projet courant
- initialise le conteneur
- enregistre les chemins applicatifs du projet courant
- découvre automatiquement les modules `*Module.php` dans `neo/Core/`
- ordonne ces modules selon leurs dépendances puis éxécute `register()` / `boot()`
- active Twig, la BDD, les assets, la traduction, l'auth, le cache, les crons, le mailer et le profiler
- scanne les contrôleurs, routes, listeners et crons applicatifs
- éxécute la requête HTTP ou la commande CLI
- centralise la gestion des erreurs

## Architecture du dépôt

```text
.
|-- bin/
|   `-- neo
|-- neo/
|   |-- App.php
|   `-- Core/
|       |-- Asset/
|       |-- Console/
|       |-- Controller/
|       |-- Cron/
|       |-- Database/
|       |-- DI/
|       |-- Error/
|       |-- Event/
|       |-- Extension/
|       |-- Http/
|       |-- Module/
|       |-- Profiler/
|       |-- Routing/
|       |-- Security/
|       |-- Testing/
|       |-- Translation/
|       |-- Tools/
|       |   `-- Markdown/
|       |-- Utils/
|       |-- Validator/
|       `-- View/
|-- public/
|   |-- index.php
|   `-- builds/
|-- src/
|   `-- <Projet>/
|       |-- App/
|       |-- Assets/
|       |-- Config/
|       |-- Database/
|       |-- Storage/
|       |-- Tests/
|       `-- Translations/
|-- composer.json
`-- vendor/
```

Le projet d'éxemple présent dans le dépôt est `src/Test/`.

## Cartographie du coeur

Le noyau `neo/Core/` est structuré par sous-système :

| Module | Description | Complexité | Avancement | Doc |
|--------|-------------|:----------:|:----------:|-----|
| `Application/` | Détection du projet courant (HTTP/CLI), résolution des chemins, commandes `project:*` | 🟢 Faible | ✅ Stable | [README](neo/Core/Application/README.md) |
| `Asset/` | Compilation CSS / JS / Less, manifest versioning, helper Twig `asset()` | 🟡 Moyenne | ✅ Stable | [README](neo/Core/Asset/README.md) |
| `Console/` | Framework CLI : scan des commandes, `AbstractCommand`, Input/Output colorisé | 🟡 Moyenne | ✅ Stable | [README](neo/Core/Console/README.md) |
| `Controller/` | `AbstractController` avec helpers HTTP, auth, events, upload, extensions dynamiques | 🟢 Faible | ✅ Stable | [README](neo/Core/Controller/README.md) |
| `Cron/` | Attribut `#[Cron]`, scanner, runner avec lock, expressions cron standard | 🟡 Moyenne | ✅ Stable | [README](neo/Core/Cron/README.md) |
| `Database/` | ORM Data Mapper complet, QueryBuilder, migrations diff, formulaires, seeding | 🔴 Haute | ✅ Stable | [README](neo/Core/Database/README.md) |
| `DI/` | Conteneur PSR-11, autowiring par réflexion, détection des dépendances circulaires | 🟡 Moyenne | ✅ Stable | [README](neo/Core/DI/README.md) |
| `Error/` | `ErrorHandler`, `FrameworkException`, comportement dev/prod différencié | 🟢 Faible | ✅ Stable | [README](neo/Core/Error/README.md) |
| `Event/` | Dispatcher, `#[AsListener]`, subscribers, priorités, cache JSON en prod | 🟡 Moyenne | ✅ Stable | [README](neo/Core/Event/README.md) |
| `Extension/` | Extensions utilitaires (Array, Date, File, Html, Json, Number, Path, String, Url) | 🟢 Faible | ✅ Stable | [README](neo/Core/Extension/README.md) |
| `Http/` | Request, Response, JsonResponse, RedirectResponse, Session, Flash, Cookie, Upload | 🟡 Moyenne | ✅ Stable | [README](neo/Core/Http/README.md) |
| `Module/` | Découverte des `*Module.php`, tri topologique des dépendances, cycle `register()`/`boot()` | 🟡 Moyenne | ✅ Stable | [README](neo/Core/Module/README.md) |
| `Profiler/` | Barre de debug dev, collecteurs pluggables (SQL, router, events, logs…) | 🟡 Moyenne | ✅ Stable | [README](neo/Core/Profiler/README.md) |
| `Routing/` | Attributs `#[Route]`/`#[MainRoute]`, cache JSON prod, injection de paramètres, `debug:router` | 🟡 Moyenne | ✅ Stable | [README](neo/Core/Routing/README.md) |
| `Security/` | Auth session/token, JWT, `#[IsGranted]`, middlewares, CSRF | 🔴 Haute | ✅ Stable | [README](neo/Core/Security/README.md) |
| `Testing/` | `TestCase`, `DatabaseTestCase`, `FeatureTestCase`, scaffold auto via `#[Test]` | 🟡 Moyenne | 🔧 En cours | [README](neo/Core/Testing/README.md) |
| `Tools/Markdown/` | Parseur Markdown sans dépendance, tableau de blocs, fonction Twig `markdown_blocks()` et filtre `md_inline` | 🟢 Faible | ✅ Stable | [README](neo/Core/Tools/Markdown/README.md) |
| `Translation/` | Domaines, `LocaleManager`, cache, Twig, `translation:sync` | 🟡 Moyenne | ✅ Stable | [README](neo/Core/Translation/README.md) |
| `Utils/` | Cache (File/Redis/Array), Config, Logger, Notifications (Email/Slack/SMS), Scanner | 🟡 Moyenne | ✅ Stable | [README](neo/Core/Utils/README.md) |
| `Validator/` | Contraintes attributs + validators séparés, `ValidatorManager`, 11 contraintes | 🟡 Moyenne | ✅ Stable | [README](neo/Core/Validator/README.md) |
| `View/` | Intégration Twig 3.x, extensions, variable globale `app`, cache templates | 🟢 Faible | ✅ Stable | [README](neo/Core/View/README.md) |

Sous-dossiers notables dans `neo/Core/` :

```text
Asset/      -> Commands/, Compiler/, Exception/
Console/    -> Attribute/, Commands/, Helper/, Interface/
Controller/ -> Commands/, Exception/, Interface/
Cron/       -> Attribute/, Commands/, Exception/
Database/   -> Builder/, Commands/, Exception/, Form/, Migration/, ORM/
DI/         -> Exception/
Error/      -> Exception/
Event/      -> Attribute/, Commands/, Interface/, Event/, Exception/
Extension/  -> Array/, Date/, File/, Html/, Json/, Number/, Path/, String/, Url/
Http/       -> Client/, File/, Response/
Module/     -> Exception/, Interface/
Profiler/   -> Collector/, Toolbar/
Routing/    -> Attribute/, Commands/, Exception/
Security/   -> Auth/, Csrf/, Middleware/
Testing/    -> Attribute/, Commands/, Context/, Enum/, Exception/, Generator/, Scaffold/, Scanner/, Template/
Tools/      -> Markdown/
Translation/-> Commands/, Exception/, Helper/, Interface/
Utils/      -> Cache/, Config/, Logger/, Mailer/
Validator/  -> Assert/
View/       -> Exception/, Interface/
```

## Cycle d'éxécution

### En HTTP

`Neo\App` cherche un projet en lisant `src/*/Config/app.config.php` et compare la clé `access` a `HTTP_HOST` / `SERVER_NAME`.

Si un seul projet existe dans `src/`, il est sélectionné automatiquement.

### En CLI

Les commandes qui opèrent sur un projet existant attendent en général `--project=NomDuProjet`.

Exceptions notables :

- `project:create`
- `project:sync`
- `app:serve`

Exemple :

```bash
php bin/neo cache:clear --project=Test
```

## Structure d'un projet

Un projet generé par `app:make:project` contient d'abord :

```text
src/Blog/
|-- .gitignore
|-- composer.json
|-- App/
|   |-- Controllers/
|   |-- Middlewares/
|   |-- Services/
|   `-- Views/
|-- Assets/
|-- Config/
|   |-- api.config.php
|   |-- app.config.php
|   |-- cache.config.php
|   |-- database.config.php
|   |-- deploy.config.php
|   |-- logger.config.php
|   |-- mailer.config.php
|   |-- session.config.php
|   `-- twig.config.php
|-- Database/
|   |-- Entity/
|   |-- Migrations/
|   |-- Repository/
|-- Storage/
`-- Translations/
```

Sans l'option `--skeleton`, le générateur ajoute aussi :

```text
src/Blog/
|-- Assets/
|   |-- css/
|   `-- js/
|-- App/Views/
|   |-- errors/
|   |-- layouts/
|   |-- pages/default/
|   `-- partials/
`-- Translations/
    |-- fr.php
    `-- en.php
```

Certains dossiers sont créés plus tard, quand la fonctionnalité est activée :

- `App/Crons/` via `make:cron`
- `App/Event/Listener/` via `make:event` et `make:event:listener`
- `Database/Entity/` via `make:entity`
- `Database/Migrations/` au premier `database:orm:diff` ou `database:migration:migrate`
- `Tests/` via `make:test` ou `make:test:auto`

Les configs sensibles `database.config.php`, `deploy.config.php`, `api.config.php` et `mailer.config.php` sont prévues pour être ignorées par Git dans le `.gitignore` généré.
Le générateur ignore aussi `Storage/`.

## Conteneur DI et configuration

Le conteneur `Neo\Core\DI\Container` fournit :

- `set()` pour enregistrer un service ou une factory
- `get()` pour résoudre un service
- `bind()` pour mapper une abstraction vers une implémentation
- `make()` pour instancier une classe avec des paramêtres runtime
- autowiring via reflexion
- support des constructeurs de contrôleurs et de services

Exemple :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Services;

use Neo\Core\Utils\Cache\CacheManager;
use Neo\Core\Utils\Logger\LoggerManager;

final class ReportService
{
    public function __construct(
        private CacheManager $cache,
        private LoggerManager $logger
    ) {
    }

    public function build(): array
    {
        $this->logger->info('Génération du rapport');

        return $this->cache->get('report.latest', []);
    }
}
```

### Configuration

Le service `Config` charge tous les fichiers `*.config.php` du projet et peut merger les fichiers `*.config.test.php` pendant les tests.

Exemple :

```php
$appName = $this->getConfig()->from('app')->get('general.name');
$timezone = $this->getConfig()->from('app')->get('date.timezone', 'UTC');
$twigOptions = $this->getConfig()->from('twig')->all();
```

Exemple de `app.config.php` :

```php
<?php
declare(strict_types=1);

return [
    'general' => [
        'name' => 'Blog',
        'description' => 'Mon projet NeoPHP',
    ],
    'environment' => 'dev',
    'access' => 'localhost:8000',
    'date' => [
        'timezone' => 'Europe/Paris',
    ],
];
```

## Couche HTTP

La couche HTTP est composée principalement de :

- `Request`
- `Response`
- `JsonResponse`
- `RedirectResponse`
- `Session`
- `Cookie`
- `Flash`

### Request

`Request` expose notamment :

- `getMethod()`
- `getPath()`
- `query()`
- `body()`
- `header()`
- `file()`
- `getIp()`
- `getUserAgent()`
- `getPreviousUrl()`

Exemple :

```php
#[Route(path: '/search', name: 'search', methods: ['GET'])]
public function search(): Response
{
    $term = (string) $this->request->query('q', '');

    return $this->render('pages/search/index.html.twig', [
        'term' => $term,
        'ip' => $this->request->getIp(),
    ]);
}
```

### Response

`Response` sert a construire les réponses HTTP de base.

Exemple :

```php
$response = new Response();
$response->setStatusCode(200);
$response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
$response->setContent('OK');
return $response;
```

Exemples de raccourcis via `AbstractController` :

```php
return $this->jsonSuccess(['saved' => true], 201);
return $this->jsonError('Not found', 404);
return $this->redirectToRoute('posts.index');
return $this->redirectToPath('/maintenance', 302);
```

### Session, cookie et flash

Le framework configure automatiquement la session depuis `session.config.php`.

Exemple dans un contrôleur :

```php
$this->getSession()->set('wizard.step', 2);
$step = $this->getSession()->get('wizard.step', 1);

$this->getCookie()->set('theme', 'dark');
$theme = $this->getCookie()->get('theme', 'light');

$this->getFlash()->add('success', 'Operation terminee');
```

Twig expose les messages flash via `flashes()` :

```twig
{{ flashes() }}
```

## Routing et contrôleurs

Le routing repose sur des attributs PHP scannes dans `src/<Projet>/App/Controllers`.

Fonctionnalités confirmées :

- prefix de route via `#[MainRoute(...)]`
- routes multi-méthodes via `methods: [...]`
- paramêtres dynamiques `{id}`
- paramêtres optionnels `{slug?}`
- contraintes regex via `requirements`
- cache des routes hors environnement `dev`
- injection des arguments types via le conteneur

Exemple simple :

```php
#[MainRoute(path: '/posts', name: 'posts')]
final class PostController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/posts/index.html.twig');
    }
}
```

Exemple plus complet :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Controllers;

use Neo\Core\Controller\AbstractController;use Neo\Core\Http\Response\Types\Response;use Neo\Core\Routing\Attribute\MainRoute;use Neo\Core\Routing\Attribute\Route;use Neo\Src\Blog\Database\Repository\PostRepository;

#[MainRoute(path: '/posts', name: 'posts')]
final class PostController extends AbstractController
{
    public function __construct(private PostRepository $postRepository)
    {
    }

    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/posts/index.html.twig', [
            'posts' => $this->postRepository->findAll(),
        ]);
    }

    #[Route(path: '/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        return $this->render('pages/posts/show.html.twig', [
            'post' => $this->postRepository->find($id),
        ]);
    }
}
```

Helpers exposés par `AbstractController` :

- `render()`
- `template()`
- `redirectToRoute()`
- `redirectToPath()`
- `redirectBack()`
- `json()`
- `jsonSuccess()`
- `jsonError()`
- `auth()`
- `dispatch()`
- `upload()`
- `getSession()`
- `getFlash()`
- `getCookie()`
- accès à `Logger`, `Cache`, `Config`

Twig expose aussi :

- `path()`
- `currentRoute()`

## Vues Twig, assets et traductions

### Vues Twig

Les vues sont chargées depuis `src/<Projet>/App/Views`.

Twig est initialisé avec :

- cache optionnel
- debug optionnel
- `twig/intl-extra`
- global `app`
- fonctions ajoutées par le framework

Exemple :

```twig
{% extends 'layouts/base_layout.html.twig' %}

{% block title %}Liste des posts{% endblock %}

{% block content %}
    <h1>Posts</h1>

    <ul>
        {% for post in posts %}
            <li>
                <a href="{{ path('posts.show', {id: post.getId()}) }}">
                    {{ post.getTitle() }}
                </a>
            </li>
        {% endfor %}
    </ul>
{% endblock %}
```

### Assets

Les assets sources vivent dans `src/<Projet>/Assets/`.

Le composant `AssetHandler` :

- expose `asset()`
- compile `css`, `js` et `less`
- minifie CSS et JS
- génère des noms avec hash
- écrit `public/builds/<Projet>/manifest.json`
- sert les fichiers compiles depuis `public/builds/<Projet>/assets/`

Exemple Twig :

```twig
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script src="{{ asset('js/app.js') }}"></script>
```

Arborescence source :

```text
src/Blog/Assets/
|-- css/
|   `-- app.css
`-- js/
    `-- app.js
```

### Traductions

Les traductions sont chargées depuis `src/<Projet>/Translations/<locale>.php`.

Fonctions Twig disponibles :

- `translate()`
- `trans()`
- `getLocales()`
- `getLocale()`
- `isEnabledTranslation()`

Comportement notable :

- la locale est résolue depuis la config et les cookies
- `setLocale()` persiste la langue dans un cookie `lang`
- en environnement `dev`, une clé manquante est auto-enregistrée dans le fichier de la locale courante
- `translation:sync` permet de synchroniser les clés de tous les fichiers de locale

Exemple de fichier `src/Blog/Translations/fr.php` :

```php
<?php

return [
    'Bienvenue sur le blog' => 'Bienvenue sur le blog',
    'Enregistrer' => 'Enregistrer',
];
```

```php
// en.php
return [
    'Bienvenue sur le blog' => 'Welcome to the blog',
    'Enregistrer' => 'Save',
];
```

Exemple Twig :

```twig
<h1>{{ trans('Bienvenue sur le blog') }}</h1>
<button>{{ trans('Enregistrer') }}</button>

{{-- Avec paramètres --}}
{{ trans('Bonjour :name !', {'name': user.getName()}) }}
{{ 'Bonjour :name !'|trans({'name': user.getName()}) }}
```

Exemple dans un contrôleur :

```php
#[Route(path: '/change-locale/{locale}', name: 'change.locale', methods: ['GET'])]
public function changeLocale(string $locale, TranslationManager $translator): Response
{
    $translator->setLocale($locale);
    return $this->redirectBack('home.index');
}
```

### Extensions utilitaires

Le dossier `neo/Core/Extension/` expose des helpers réutilisables à deux niveaux :

- dans les contrôleurs via `getString()`, `getDate()`, `getFile()`, `getHtml()`, `getJson()`, `getNumber()`, `getPath()`, `getUrl()` et `getArray()`
- dans Twig via des fonctions et filtres enregistrés automatiquement

Familles disponibles :

- `StringExtension`
  `slugify()`, `camelCase()`, `snakeCase()`, `pascalCase()`, `truncate()`, `excerpt()`
- `DateExtension`
  `date_now()`, `date_format()`, `human_diff()`, `date_age()`, `is_past()`, `is_future()`, `is_today()`
- `NumberExtension`
  `currency()`, `percent()`, `human_size()`, `ordinal()`, `to_roman()`
- `FileExtension`
  `file_extension()`, `file_size()`, `file_mime()`, `is_image()`
- `HtmlExtension`
  `html_escape()`, `html_strip()`, `html_truncate()`, `html_tag()`
- `JsonExtension`
  `json_encode_ext()`, `json_decode_ext()`, `json_is_valid()`
- `UrlExtension`
  `url_is_valid()`, `url_host()`, `url_params()`, `url_add_params()`
- `PathExtension`
  `path_join()`, `path_normalize()`, `path_extension()`, `path_filename()`
- `ArrayExtension`
  `array_flatten()`, `array_pluck()`, `array_only()`, `array_except()`, `array_group_by()`

Exemples :

```php
$slug = $this->getString()->slugify('Mon Titre Exemple');
$price = $this->getNumber()->currency(19.99, 'EUR');
```

```twig
{{ 'Mon Titre Exemple'|slugify }}
{{ currency(19.99, 'EUR') }}
{{ date_format(post.created_at, 'd/m/Y H:i') }}
{{ path_join('uploads', user.avatar) }}
```

## Base de donnees et QueryBuilder

La connexion PDO est pilotée par `Config/database.config.php` via `DatabaseConnection`.

Exemple minimal :

```php
return [
    'enabled' => true,
    'use' => 'default',
    'connections' => [
        'default' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'blog',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4',
        ],
    ],
];
```

### Outils de schéma

Le framework embarque une CLI dédiée à la base de données :

- `database:create`
  crée la base déclarée dans `database.config.php`
- `make:entity`
  génère une entité Data Mapper (POPO) et son repository dans `Database/Entity/` et `Database/Repository/`
- `database:orm:diff`
  compare les entités avec la base de données courante et génère un fichier de migration dans `Database/Migrations/`
- `database:migration:migrate`
  applique toutes les migrations en attente
- `database:migration:rollback`
  annule le dernier batch appliqué
- `database:migration:status`
  affiche l'état des migrations et signale un écart entre le schéma courant et le dernier snapshot

Comportements notables :

- `make:entity` est interactif : il demande le nom de l'entité, ses propriétés et leurs types
- `make:entity --no-repository` ignore la génération du repository
- `database:orm:diff --dry-run` affiche le diff sans écrire de fichier
- `database:orm:diff --connection=<nom>` cible une connexion spécifique pour les projets multi-base
- les tables internes `neo_migrations` et `neo_schema_snapshots` sont exclues de l'introspection
- les migrations générées sont écrites dans `src/<Projet>/Database/Migrations/`

Exemples :

```bash
php bin/neo database:create --project=Blog
php bin/neo make:entity Post --project=Blog
php bin/neo database:orm:diff --project=Blog --name=add_posts_table
php bin/neo database:orm:diff --project=Blog --name=add_posts_table --dry-run
```

### QueryBuilder

Le `QueryBuilder` couvre notamment :

- `table()`
- `select()`
- `where()`, `orWhere()`
- `whereLike()`, `whereIn()`, `whereNull()`, `whereNotNull()`
- `between()`
- `join()`, `leftJoin()`
- `orderBy()`, `groupBy()`
- `limit()`, `offset()`
- `get()`, `first()`, `count()`
- `insert()`, `insertGetId()`, `update()`, `delete()`
- `paginate()`
- transactions via `transaction()`

Exemple :

```php
<?php
declare(strict_types=1);

use Neo\Core\Database\Builder\QueryBuilder;

$qb = (new QueryBuilder())
    ->table('posts')
    ->select(['posts.id', 'posts.title'])
    ->where('posts.user_id', '=', 1)
    ->whereLike('posts.title', 'neo')
    ->orderBy('posts.id', 'DESC')
    ->limit(10);

$rows = $qb->get();
```

Exemple avec transaction :

```php
(new QueryBuilder())
    ->table('posts')
    ->transaction(function (QueryBuilder $qb): void {
        $qb->table('posts')->insert([
            'user_id' => 1,
            'title' => 'Post transactionnel',
            'content' => 'Contenu',
        ]);
    });
```

### Migrations

Les migrations vivent dans `src/<Projet>/Database/Migrations/`.

Chaque migration expose :

- `up(DatabaseManager $db): void`
- `down(DatabaseManager $db): void`

Le runner maintient deux tables techniques :

- `neo_migrations` pour l'historique des migrations appliquées
- `neo_schema_snapshots` pour mémoriser un hash du schéma après exécution

Le snapshot permet à `database:migration:status` de prévenir quand le schéma courant a changé depuis la dernière migration générée ou appliquée.

Exemple de workflow :

```bash
php bin/neo make:entity Post --project=Blog
php bin/neo database:orm:diff --project=Blog --name=initial_schema
php bin/neo database:migration:status --project=Blog
php bin/neo database:migration:migrate --project=Blog
php bin/neo database:migration:rollback --project=Blog
```

Exemple minimal de migration :

```php
<?php
declare(strict_types=1);

final class MigrationVersion_20260606120000
{
    public function up(DatabaseManager $db): void
    {
        $db->execute('CREATE TABLE posts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY)');
    }

    public function down(DatabaseManager $db): void
    {
        $db->execute('DROP TABLE posts');
    }
}
```

## ORM et repositories

L'ORM de NeoPHP est un Data Mapper. Les entités sont des POPOs annotés avec des attributs de mapping. Aucune classe mère n'est requise. La persistance passe par l'`EntityManager`.

### EntityManager

`EntityManager` est le point d'entrée pour toutes les opérations de persistance.

API principale :

- `persist(object $entity)` — enregistre une entité pour insertion ou mise à jour
- `remove(object $entity)` — marque une entité pour suppression
- `flush()` — écrit tous les changements en base de données
- `find(string $class, mixed $id)` — recherche par clé primaire
- `getRepository(string $class)` — retourne le repository de l'entité
- `wrapInTransaction(callable $callback)` — exécute un callback dans une transaction
- `contains(object $entity)` — vérifie si une entité est gérée par l'UnitOfWork
- `clear()` — vide l'identity map

Dans un contrôleur, `EntityManager` est accessible via `$this->entityManager` (enregistré par `DatabaseControllerExtension`).

Exemple :

```php
#[Route(path: '/', name: 'store', methods: ['POST'])]
public function store(): Response
{
    $post = new Post();
    $post->setTitle((string) $this->request->body('title'));

    $this->entityManager->persist($post);
    $this->entityManager->flush();

    return $this->jsonSuccess(['id' => $post->getId()], 201);
}
```

### EntityRepository

`EntityRepository` est la classe de base générée par `make:entity`.

API disponible :

- `find($id)` — recherche par clé primaire
- `findAll()` — retourne toutes les entités
- `findBy(array $criteria, array $orderBy, ?int $limit, ?int $offset)` — recherche avec critères
- `findOneBy(array $criteria, array $orderBy)` — retourne un seul résultat
- `count(array $criteria)` — compte les entités correspondant aux critères

Exemple de repository :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Database\Repository;

use Neo\Core\Database\ORM\Persistence\EntityRepository;
use Neo\Src\Blog\Database\Entity\Post;

/**
 * @extends EntityRepository<Post>
 */
final class PostRepository extends EntityRepository
{
}
```

Utilisation dans un contrôleur :

```php
public function __construct(private PostRepository $posts) {}

public function index(): Response
{
    return $this->render('pages/posts/index.html.twig', [
        'posts' => $this->posts->findAll(),
    ]);
}

public function show(int $id): Response
{
    $post = $this->posts->find($id);

    return $this->render('pages/posts/show.html.twig', ['post' => $post]);
}
```

### Relations

Relations disponibles par attributs :

- `#[OneToOne(targetEntity: ..., inversedBy: ...)]` avec `#[JoinColumn]`
- `#[ManyToOne(targetEntity: ..., inversedBy: ...)]` avec `#[JoinColumn]`
- `#[OneToMany(targetEntity: ..., mappedBy: ...)]`
- `#[ManyToMany(targetEntity: ..., inversedBy: ...)]` avec `#[JoinTable]`

Les collections (`OneToMany`, `ManyToMany`) utilisent la classe `Collection`. Le chargement est lazy par défaut, géré via des proxies transparents.

Exemple d'entité avec relations :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Database\Entity;

use Neo\Core\Database\ORM\Collection\Collection;
use Neo\Core\Database\ORM\Mapping\Attribute\Column;
use Neo\Core\Database\ORM\Mapping\Attribute\Entity;
use Neo\Core\Database\ORM\Mapping\Attribute\GeneratedValue;
use Neo\Core\Database\ORM\Mapping\Attribute\Id;
use Neo\Core\Database\ORM\Mapping\Attribute\JoinColumn;
use Neo\Core\Database\ORM\Mapping\Attribute\ManyToOne;
use Neo\Core\Database\ORM\Mapping\Attribute\OneToMany;
use Neo\Core\Database\ORM\Mapping\Attribute\Table;
use Neo\Src\Blog\Database\Repository\PostRepository;

#[Entity(repositoryClass: PostRepository::class)]
#[Table(name: 'posts')]
final class Post
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer', unsigned: true)]
    private ?int $id = null;

    #[Column(type: 'string', length: 255)]
    private string $title;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    #[JoinColumn(name: 'user_id', nullable: false)]
    private User $author;

    /** @var Collection<Comment> */
    #[OneToMany(targetEntity: Comment::class, mappedBy: 'post')]
    private Collection $comments;

    public function __construct()
    {
        $this->comments = new Collection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getAuthor(): User { return $this->author; }
    public function setAuthor(User $author): static { $this->author = $author; return $this; }

    /** @return Collection<Comment> */
    public function getComments(): Collection { return $this->comments; }
}
```

Voir la section [ORM Data Mapper (entités)](#orm-data-mapper-entités) pour la création des entités via la CLI et le workflow de migration.

## ORM Data Mapper (entités)

L'ORM de NeoPHP repose sur le Data Mapper. `make:entity` crée une entité et son repository. `database:orm:diff` génère la migration à partir de la différence entre les entités et la base de données.

### Créer une entité

```bash
php bin/neo make:entity Post --project=Blog
```

Le générateur est interactif : il demande le nom, puis les propriétés et leurs types.

Exemple d'entité générée dans `Database/Entity/Post.php` :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Database\Entity;

use Neo\Core\Database\ORM\Mapping\Attribute\Column;
use Neo\Core\Database\ORM\Mapping\Attribute\Entity;
use Neo\Core\Database\ORM\Mapping\Attribute\GeneratedValue;
use Neo\Core\Database\ORM\Mapping\Attribute\Id;
use Neo\Core\Database\ORM\Mapping\Attribute\Table;
use Neo\Src\Blog\Database\Repository\PostRepository;

#[Entity(repositoryClass: PostRepository::class)]
#[Table(name: 'posts')]
final class Post
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer', unsigned: true)]
    private ?int $id = null;

    #[Column(type: 'string', length: 255)]
    private string $title;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }
}
```

Types scalaires disponibles :

- `string`, `text`
- `integer`, `bigint`, `smallint`
- `boolean`, `float`, `decimal`
- `datetime`, `date`, `time`
- `json`

Relations disponibles :

- `#[OneToOne(...)]` avec `#[JoinColumn(...)]`
- `#[ManyToOne(...)]` avec `#[JoinColumn(...)]`
- `#[OneToMany(...)]`
- `#[ManyToMany(...)]` avec `#[JoinTable(...)]`

Les côtés `OneToMany` et `ManyToMany` utilisent `Collection` pour gérer les collections d'objets liés.

> **Note ManyToMany — persistence automatique au flush :** Les collections ManyToMany sont désormais persistées automatiquement lors du `flush()`. Un snapshot de la collection est pris au chargement de l'entité ; au moment du flush, l'UoW calcule le diff (ajouts/suppressions) et synchronise la table de jointure sans action manuelle.

```php
$article = $em->find(Article::class, 1);
$article->getTags()->add($em->find(Tag::class, 5)); // Ajout
$article->getTags()->remove($existingTag);           // Suppression
$em->flush(); // Synchronise automatiquement la table article_tag
```

### Repository Data Mapper

Le repository généré étend `EntityRepository` :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Database\Repository;

use Neo\Core\Database\ORM\Persistence\EntityRepository;
use Neo\Src\Blog\Database\Entity\Post;

/**
 * @extends EntityRepository<Post>
 */
final class PostRepository extends EntityRepository
{
}
```

Option `--no-repository` disponible pour ignorer la génération du repository.

### Générer la migration depuis les entités

```bash
# Comparer les entités avec la base courante et générer la migration
php bin/neo database:orm:diff --project=Blog --name=add_posts_table

# Prévisualiser le diff sans écrire de fichier
php bin/neo database:orm:diff --project=Blog --name=add_posts_table --dry-run

# Appliquer
php bin/neo database:migration:migrate --project=Blog
```

Sur un projet multi-base, l'option `--connection=<nom>` cible une connexion spécifique.

Les migrations générées par `database:orm:diff` suivent le même format `up()` / `down()` que les migrations manuelles et sont stockées dans `Database/Migrations/`.

## Formulaires, upload et validation

### Formulaires

NeoPHP embarque :

- `FormFactory` — point d'entrée pour créer des formulaires
- `FormBuilder` — API fluide de construction
- `Form` — objet formulaire
- `FieldType` — enum des types de champs disponibles
- rendu Twig
- CSRF intégré
- validation par contraintes

Types de champs disponibles via `FieldType` :

- `text`, `textarea`
- `email`, `password`
- `number`
- `hidden`
- `checkbox`
- `select`
- `date`, `datetime-local`

Helpers Twig disponibles :

- `form_start()`
- `form_end()`
- `form_row()`
- `form_widget()`
- `form_label()`
- `form_error()`
- `form_errors()`
- `form_csrf()`

Exemple de construction via `FormFactory` :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Services;

use Neo\Core\Database\Form\Form;
use Neo\Core\Database\Form\FormFactory;
use Neo\Src\Blog\Database\Entity\User;

final class UserFormService
{
    public function __construct(private FormFactory $factory) {}

    public function build(?User $user = null): Form
    {
        $user ??= new User();

        return $this->factory->createFor($user)
            ->add('firstname', 'text', ['label' => 'Prénom', 'required' => true])
            ->add('email', 'email', ['label' => 'Email'])
            ->getForm();
    }
}
```

Exemple Twig :

```twig
{{ form_start(form) }}
{{ form_row(form, 'firstname') }}
{{ form_row(form, 'email') }}
{{ form_end(form) }}
```

### Upload dans un controleur

Le point d'entrée applicatif est `AbstractController::upload()`.

Signature :

```php
$filename = $this->upload(
    string $field,
    string $name,
    array $extensions,
    string $directory
);
```

Ce helper :

- récupère le fichier via `Request::file()`
- verifie l'upload PHP
- lit l'extension d'origine
- refuse `php`, `phtml`, `exe`, `sh`, `js`
- vérifie la whitelist fournie
- crée le dossier cible dans `src/<Projet>/Assets/<directory>`
- déplace le fichier
- renvoie le nom final du fichier

Exemple :

```php
#[Route(path: '/profile/avatar', name: 'avatar.upload', methods: ['POST'])]
public function uploadAvatar(): Response
{
    $filename = $this->upload(
        field: 'avatar',
        name: 'user_' . (string) $this->auth()->user()?->getId(),
        extensions: ['jpg', 'jpeg', 'png', 'webp'],
        directory: 'uploads/avatars'
    );

    return $this->jsonSuccess([
        'filename' => $filename,
        'path' => 'uploads/avatars/' . $filename,
    ]);
}
```

Affichage ensuite :

```twig
<img src="{{ asset('uploads/avatars/' ~ user.getAvatar()) }}" alt="Avatar">
```

### Validation

Le validateur repose sur des attributs de contraintes posés sur les propriétés de n'importe quelle classe (entité, DTO, etc.).

Depuis la refactorisation, chaque contrainte est **scindée en deux fichiers** : un attribut PHP dans `Assert/` (qui déclare les paramètres) et un validator dans `Validator/` (qui contient la logique). Le `ValidatorManager` résout le validator via le conteneur DI grâce à la méthode `validatedBy()` de la contrainte.

Contraintes présentes dans le framework :

- `NotBlank`
- `Length`
- `Email`
- `Date`
- `Choice`
- `Range`
- `Regex`
- `Url`
- `Unique`
- `Exists` — vérifie qu'une valeur existe en base de données (utile pour valider une clé étrangère)
- `EqualToField`

Exemple sur un DTO :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Dto;

use Neo\Core\Validator\Assert\Email;
use Neo\Core\Validator\Assert\EqualToField;
use Neo\Core\Validator\Assert\Length;
use Neo\Core\Validator\Assert\NotBlank;

final class RegisterDto
{
    #[NotBlank(message: 'Le prenom est obligatoire.')]
    public string $firstname = '';

    #[NotBlank(message: 'L email est obligatoire.')]
    #[Email(message: 'L email est invalide.')]
    public string $email = '';

    #[Length(min: 8, message: 'Le mot de passe doit faire au moins 8 caracteres.')]
    public string $password = '';

    #[EqualToField(field: 'password', message: 'Les mots de passe doivent etre identiques.')]
    public string $password_confirm = '';
}
```

## Seeding

Le module Seeder permet de peupler la base de données avec des données de référence ou de démonstration.

Un seeder est une classe annotée `#[Seeder]` qui implémente `SeedInterface::run(EntityManager $em)` :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Database\Seeder;

use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\Database\Seeder\Attribute\Seeder;
use Neo\Core\Database\Seeder\Interface\SeedInterface;
use Neo\Src\Blog\Database\Entity\Country;

#[Seeder(order: 10, group: 'reference')]
final class CountrySeeder implements SeedInterface
{
    public function run(EntityManager $entityManager): void
    {
        $country = new Country();
        $country->setCode('FR')->setName('France');
        $entityManager->persist($country);
        $entityManager->flush();
    }
}
```

L'attribut `#[Seeder]` configure deux paramètres :

| Paramètre | Défaut | Description |
|-----------|--------|-------------|
| `order` | `0` | Ordre d'exécution croissant |
| `group` | `'reference'` | `'reference'` pour les données stables, `'demo'` pour les données de développement |

Commandes disponibles :

```bash
# Générer un seeder
php bin/neo database:make:seed CountrySeeder --project=Blog --order=10 --group=reference

# Prévisualiser sans exécuter
php bin/neo database:run:seed --project=Blog --dry-run

# Exécuter les seeders 'reference' (défaut)
php bin/neo database:run:seed --project=Blog

# Inclure les seeders de développement
php bin/neo database:run:seed --project=Blog --dev

# Filtrer par groupe
php bin/neo database:run:seed --project=Blog --group=demo
```

## Securite: auth, mot de passe, middlewares, csrf

### Authentification

L'auth est pilotée depuis `app.config.php`.

Le framework supporte deux guards :

- `session`
- `token`

Le guard `token` s'appuie sur `JwtManager`.

Configuration type :

```php
'auth' => [
    'enabled' => true,
    'model' => User::class,
    'identifier' => 'email',
    'password' => 'password',
    'guard' => 'session',
    'role' => [
        'model' => Role::class,
        'foreign_key' => 'role_id',
        'field' => 'slug',
    ],
    'options' => [
        'secret' => 'change-me',
        'expiration' => 3600,
        'algorithm' => 'HS256',
    ],
],
```

API de `AuthManager` :

- `attempt()`
- `login()`
- `logout()`
- `check()`
- `user()`
- `hasRole()`
- `generateToken()`

Exemple de login session :

```php
#[MainRoute(path: '/login', name: 'login')]
final class LoginController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET', 'POST'])]
    public function index(): Response
    {
        if ($this->request->getMethod() === 'GET') {
            return $this->render('pages/auth/login.html.twig');
        }

        $ok = $this->auth()->attempt([
            'email' => (string) $this->request->body('email'),
            'password' => (string) $this->request->body('password'),
        ]);

        if (!$ok) {
            return $this->jsonError('Identifiants invalides', 401);
        }

        return $this->redirectToRoute('dashboard.index');
    }
}
```

Exemple de login token :

```php
#[MainRoute(path: '/api', name: 'api')]
final class ApiAuthController extends AbstractController
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    #[Route(path: '/login', name: 'login', methods: ['POST'])]
    public function login(): Response
    {
        $email = (string) $this->request->body('email');
        $password = (string) $this->request->body('password');

        $ok = $this->auth()->attempt([
            'email' => $email,
            'password' => $password,
        ]);

        if (!$ok) {
            return $this->jsonError('Identifiants invalides', 401);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user === null) {
            return $this->jsonError('Utilisateur introuvable', 401);
        }

        return $this->jsonSuccess([
            'token' => $this->auth()->generateToken($user),
        ]);
    }
}
```

Twig expose :

- `auth_check()`
- `auth_user()`
- `auth_has_role()`
- `csrf_token()`

### PasswordManager

Le service `PasswordManager` fournit :

- `hash()`
- `verify()`
- `needsRehash()`
- `generate()`
- `getInfo()`

Exemple :

```php
$hash = $this->getPasswordManager()->hash('secret123');
$ok = $this->getPasswordManager()->verify('secret123', $hash);
```

### Middlewares

Attributs supportés :

- `#[Middleware(...)]` — attache un middleware à une classe ou méthode
- `#[RateLimit(...)]` — limite de débit sur une route
- `#[Maintenance(...)]` — mode maintenance
- `#[IsGranted(roles: [...])]` — accès par rôle(s), raccourci de `RoleMiddleware`

Middlewares coeur :

- `AuthMiddleware` — vérifie que l'utilisateur est authentifié
- `GuestMiddleware` — vérifie que l'utilisateur n'est pas connecté
- `RoleMiddleware` — vérifie un rôle spécifique
- `IsGrantedMiddleware` — vérifie un ou plusieurs rôles via `#[IsGranted]`
- `RateLimitMiddleware` — limite de débit générale
- `AuthRateLimitMiddleware` — limite de débit sur l'authentification
- `CsrfMiddleware` — validation CSRF sur les requêtes POST/PUT/PATCH/DELETE

Exemple de middleware applicatif :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Middlewares;

use Neo\Core\DI\Container;
use Neo\Core\Security\Auth\AuthManager;
use Neo\Core\Security\Middleware\Interface\MiddlewareInterface;

final class AdminAccessMiddleware implements MiddlewareInterface
{
    private AuthManager $auth;

    public function __construct(Container $container)
    {
        $this->auth = $container->get(AuthManager::class);
    }

    public function handle(): bool
    {
        return $this->auth->check() && $this->auth->hasRole('admin');
    }
}
```

Exemple d'utilisation avec `#[Middleware]` :

```php
#[MainRoute(path: '/admin', name: 'admin')]
#[Middleware(use: AuthMiddleware::class, redirect: 'login.index')]
#[Middleware(use: RoleMiddleware::class, params: ['role' => 'admin'])]
final class DashboardController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    #[RateLimit(maxAttempts: 20, decaySeconds: 60)]
    public function index(): Response
    {
        return $this->render('pages/admin/index.html.twig');
    }
}
```

Exemple avec `#[IsGranted]` :

```php
#[MainRoute(path: '/admin', name: 'admin')]
#[IsGranted(roles: ['admin'])]
final class DashboardController extends AbstractController
{
    #[Route(path: '/users', name: 'users', methods: ['GET'])]
    #[IsGranted(roles: ['admin', 'superadmin'])]
    public function users(): Response
    {
        return $this->render('pages/admin/users.html.twig');
    }
}
```

### CSRF

Le manager CSRF stocke les tokens en session sous `_csrf_tokens`.

Comportement :

- génération via `generateToken()`
- expiration par défaut a 3600 secondes
- validation via `validateToken()`
- intégration dans les formulaires via `form_csrf()` et `csrf_token()`

## Events

NeoPHP embarque un event dispatcher et plusieurs évènements coeur :

- `RequestEvent`
- `ResponseEvent`
- `ExceptionEvent`

Les listeners applicatifs sont attendus dans `src/<Projet>/App/Event/Listener`.

Ils peuvent être déclarés :

- via `#[AsListener(event: ..., priority: ...)]`
- via `EventSubscriberInterface`

Exemple complet :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Event;

use Neo\Core\Event\Abstract\AbstractEvent;

final class UserRegisteredEvent extends AbstractEvent
{
    public function __construct(public readonly int $userId)
    {
    }
}
```

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Event\Listener;

use Neo\Core\Event\Attribute\AsListener;
use Neo\Src\Blog\App\Event\UserRegisteredEvent;

#[AsListener(event: UserRegisteredEvent::class, priority: 0)]
final class SendWelcomeEmailListener
{
    public function handle(UserRegisteredEvent $event): void
    {
        $userId = $event->userId;
    }
}
```

Exemple dans un contrôleur :

```php
#[Route(path: '/register', name: 'register', methods: ['POST'])]
public function register(): Response
{
    $user = new \Neo\Src\Blog\Database\Entity\User();
    $user->setFirstname((string) $this->request->body('firstname'));
    $user->setEmail((string) $this->request->body('email'));
    $user->setPassword($this->getPasswordManager()->hash(
        (string) $this->request->body('password')
    ));

    $em = $this->entityManager();
    $em->persist($user);
    $em->flush();

    $this->dispatch(new \Neo\Src\Blog\App\Event\UserRegisteredEvent((int) $user->getId()));

    return $this->jsonSuccess([
        'id' => $user->getId(),
    ], 201);
}
```

## Crons

NeoPHP embarque un système de tâches planifiées éxécutables via la CLI.

Les crons applicatifs sont attendus dans le projet courant et peuvent être lancés manuellement ou automatiquement via
le système d'exploitation.

### Créer un cron

Pour générer un nouveau cron :

```bash
php bin/neo make:cron <NomDuCron> --project=Blog
```

Exemple :

```bash
php bin/neo make:cron CleanupTempFiles --project=Blog
```

Le générateur crée automatiquement le fichier du cron dans le projet cible.

### Lister les crons

Pour afficher tous les crons disponibles d'un projet :

```bash
php bin/neo cron:list --project=Blog
```

Cette commande affiche notamment :

- le nom du cron
- sa description
- sa fréquence
- son statut

### Exécuter les crons

Pour éxécuter tous les crons du projet :

```bash
php bin/neo cron:run --project=Blog
```

Cette commande est celle qui doit être planifiée automatiquement par le systeme d'exploitation.

### Exécution automatique des crons

#### Linux

Sous Linux, les crons sont généralement pilotés via `crontab`.

Ouvrir la configuration cron :

```bash
crontab -e
```

Exécuter les crons NeoPHP toutes les minutes :

```bash
* * * * * php /path/to/project/bin/neo cron:run --project=Blog
```

Exemple concret :

```bash
* * * * * php /var/www/neophp/bin/neo cron:run --project=Blog
```

Vérifier les logs cron :

```bash
grep CRON /var/log/syslog
```

#### macOS

macOS supporte également `crontab`.

Ouvrir la configuration :

```bash
crontab -e
```

Ajouter :

```bash
* * * * * php /path/to/project/bin/neo cron:run --project=Blog
```

Exemple :

```bash
* * * * * php /Users/benjamin/Sites/neophp/bin/neo cron:run --project=Blog
```

Vérifier les tâches :

```bash
crontab -l
```

#### Windows

Sous Windows, utiliser le Planificateur de tâches.

Commande a éxécuter :

```bash
php C:\path\to\project\bin\neo cron:run --project=Blog
```

Exemple :

```bash
php C:\Sites\NeoPHP\bin\neo cron:run --project=Blog
```

Configuration conseillée :

- déclencheur : toutes les minutes
- programme : `php.exe`
- arguments :

```bash
C:\Sites\NeoPHP\bin\neo cron:run --project=Blog
```

Le Planificateur de tâches peut être ouvert avec :

```text
Win + R -> taskschd.msc
```

#### Docker

Exemple avec une boucle simple :

```bash
while true; do
    php bin/neo cron:run --project=Blog
    sleep 60
done
```

Exemple via `docker-compose` :

```yaml
services:
  cron:
    command: sh -c "while true; do php bin/neo cron:run --project=Blog; sleep 60; done"
```

### Conseils

En production, il est recommandé :

- d'éxécuter `cron:run` toutes les minutes
- de journaliser les erreurs via le `Logger`
- d'eviter les traitements bloquants trop longs
- d'utiliser des files d'attente pour les traitements lourds
- de surveiller les éxécutions via les logs applicatifs ou système

## Cache, logs, mailer, profiler et erreurs

### Cache

Le service `Cache` est piloté par `cache.config.php`.

Drivers disponibles :

- `files`
  stockage dans `src/<Projet>/Storage/<path>`
- `redis`
  via `predis/predis`
- `array`
  stockage mémoire pour usage court ou test

API :

- `set()`
- `get()`
- `delete()`
- `clear()`
- `has()`
- `remember()`

Exemple :

```php
$this->getCache()->set('homepage.posts', $posts, 600);
$posts = $this->getCache()->get('homepage.posts', []);
$stats = $this->getCache()->remember('stats.daily', 300, fn() => $service->buildStats());
```

### Logger

Le service `Logger` lit `logger.config.php` et gère :

- niveaux de logs
- channels
- rotation
- archivage zip

Niveaux supportés :

- `debug`
- `info`
- `notice`
- `warning`
- `error`
- `critical`
- `alert`
- `emergency`

Exemple :

```php
$this->getLogger()->channel('framework')->error(
    'Erreur metier',
    ['post_id' => 12],
    'PostController::show'
);
```

### Mailer

Le dossier `neo/Core/Utils/Mailer/` enregistre un service `Mailer` basé sur `PHPMailer`.

Configuration :

- `src/<Projet>/Config/mailer.config.php`
- driver courant via `default`
- expéditeur via `from`
- SMTP via `drivers.smtp`

API principale :

- `to()`
- `subject()`
- `body()`
- `template()`
- `cc()`
- `bcc()`
- `attach()`
- `send()`
- `getSentMails()`

Dans un contrôleur, `getMailer()` est disponible via l'extension de contrôleur.

Exemple :

```php
$sent = $this->getMailer()
    ->to('user@example.com', 'John Doe')
    ->subject('Bienvenue')
    ->template('emails/welcome.html.twig', [
        'user' => $user,
    ])
    ->send();
```

Si le mailer est désactivé, l'envoi est ignoré et un warning est journalisé.

### Profiler

Le dossier `neo/Core/Profiler/` active une barre de debug uniquement en HTTP et uniquement quand `app.config.php` definit `environment = dev`.

Collecteurs exposes :

- requête HTTP
- route et paramètres resolvés
- requêtes SQL
- évènements dispatchés
- logs
- utilisateur authentifié
- traductions résolues et cléfs manquantes
- mails envoyés

Le toolbar est injecté dans les réponses HTML.
Il est ignoré pour les `JsonResponse`, `RedirectResponse` et les contenus non HTML.

### Gestion des erreurs

`ErrorHandler` :

- intercepte exceptions et erreurs PHP
- loggue les erreurs
- dispatch un `ExceptionEvent`
- rend `errors/<code>.html.twig` si présent
- fournit un fallback HTML sinon
- affiche plus de détails en `dev`

Exemple de vues d'erreur :

```text
src/Blog/App/Views/errors/404.html.twig
src/Blog/App/Views/errors/500.html.twig
```

Exemple `404.html.twig` :

```twig
{% extends 'layouts/base_layout.html.twig' %}

{% block content %}
    <h1>404</h1>
    <p>{{ message }}</p>
{% endblock %}
```

## Markdown

Le module `Tools/Markdown` fournit un parseur Markdown sans dépendance externe. Il convertit du texte Markdown ou un fichier `.md` en tableau de blocs structurés, rendus via Twig.

### Utilisation depuis un template

La fonction `markdown_blocks()` est disponible dans tous les templates Twig :

```twig
{# Depuis un fichier .md (chemin relatif à ROOT_DIR) #}
{% include 'markdown/document.html.twig'
    with { blocks: markdown_blocks('neo/Core/Asset/README.md') } %}

{# Depuis une variable contenant du Markdown brut #}
{% set blocks = markdown_blocks(article.content) %}
```

Le filtre `md_inline` applique la mise en forme inline (**gras**, *italique*, `code`, liens) :

```twig
{% for block in blocks %}
    {% if block.type == 'heading' %}
        <h{{ block.level }}>{{ block.text|md_inline|raw }}</h{{ block.level }}>
    {% elseif block.type == 'paragraph' %}
        <p>{{ block.text|md_inline|raw }}</p>
    {% endif %}
{% endfor %}
```

### Utilisation depuis PHP

```php
$manager = $container->get(MarkdownManager::class);

// Depuis un fichier
$blocks = $manager->blocks('docs/guide.md');

// Depuis une chaîne
$blocks = $manager->parse("## Titre\n\nContenu.");
```

Types de blocs retournés : `heading`, `paragraph`, `code`, `list`, `table`, `quote`, `hr`.

## CLI et generateurs

Afficher l'aide globale :

```bash
php bin/neo
```

Afficher l'aide d'une commande :

```bash
php bin/neo <commande> --help
```

La console charge automatiquement :

- les commandes natives du framework dans `neo/**/Commands/`
- les commandes applicatives dans `src/<Projet>/App/Commands/`

Commandes natives disponibles :

- `project:create`
- `project:delete`
- `project:sync`
- `app:serve`
- `app:make:command`
- `app:make:service`
- `app:composer:require`
- `asset:reload`
- `cache:clear`
- `cron:list`
- `cron:run`
- `database:create`
- `database:orm:diff`
- `database:migration:migrate`
- `database:migration:rollback`
- `database:migration:status`
- `debug:router`
- `generate:default:config`
- `make:config`
- `make:controller`
- `make:cron`
- `make:entity`
- `make:middleware`
- `make:event`
- `make:event:listener`
- `make:test`
- `make:test:auto`
- `run:test`
- `run:test:all`
- `translation:sync`

### Générateurs principaux

Exemples :

```bash
php bin/neo project:create Blog
php bin/neo make:controller PostController --project=Blog
php bin/neo make:controller ApiPostController --api --project=Blog
php bin/neo app:make:command CleanupLogs --name=logs:clean --project=Blog
php bin/neo app:make:service Mail --project=Blog
php bin/neo make:middleware AdminAccess --project=Blog
php bin/neo make:event UserRegistered --project=Blog
php bin/neo make:event:listener SendWelcomeEmail --event=UserRegistered --project=Blog
php bin/neo make:cron CleanupTempFiles --project=Blog
php bin/neo make:entity Post --project=Blog
php bin/neo make:config mail --project=Blog
php bin/neo database:create --project=Blog
php bin/neo database:orm:diff --project=Blog --name=initial_schema
```

Exemple de commande intéractive de config :

```bash
php bin/neo make:config mail --project=Blog
```

Tu peux ensuite saisir par exemple :

- `smtp.host`
- `smtp.port`
- `smtp.user`
- `smtp.pass`

Le générateur écrira un tableau PHP imbrique.

### Commandes applicatives

`app:make:command` permet de générer une commande dans le projet cible. Une fois créée dans `src/<Projet>/App/Commands/`, elle est détectée automatiquement par la console au même titre que les commandes natives.

Exemple :

```bash
php bin/neo app:make:command CleanupLogs --name=logs:clean --category=maintenance --project=Blog
php bin/neo logs:clean --project=Blog
```

### Maintenance de projet

Exemples :

```bash
php bin/neo generate:default:config --project=Blog
php bin/neo app:composer:require league/flysystem --project=Blog
php bin/neo project:sync
php bin/neo app:serve Blog
php bin/neo debug:router --project=Blog
php bin/neo cache:clear --project=Blog
php bin/neo asset:reload --project=Blog
php bin/neo database:migration:status --project=Blog
php bin/neo database:migration:migrate --project=Blog
php bin/neo translation:sync --project=Blog
php bin/neo translation:sync --project=Blog --dry-run
```

## Tests PHPUnit

Le framework embarque une couche de test par projet avec PHPUnit 13.2.

Commandes disponibles :

- `make:test`
- `make:test:auto`
- `run:test`
- `run:test:all`

Au premier `make:test` ou `make:test:auto`, NeoPHP génère :

- `src/<Projet>/Tests/bootstrap.php`
- `src/<Projet>/Tests/phpunit.xml`
- `src/<Projet>/Tests/Config/database.config.test.php`
- les dossiers `Unit`, `Feature`, `Database`, `Middleware`

Classes de base :

- `TestCase`
- `FeatureTestCase`
- `DatabaseTestCase`
- `MiddlewareTestCase`

Fonctionnalités confirmées :

- simulation de requêtes HTTP pour les tests feature
- transactions et rollback automatique pour les tests database
- surcharge de config via `*.config.test.php`
- synchronisation du schema dev vers la base de test
- rapports `junit.xml` et couverture HTML

### Tests manuels

Exemples :

```bash
php bin/neo make:test UserServiceTest --type=unit --project=Blog
php bin/neo make:test UserControllerTest --type=feature --project=Blog
php bin/neo make:test UserRepositoryTest --type=database --project=Blog
php bin/neo make:test AuthMiddlewareTest --type=middleware --project=Blog
```

### Génération automatique avec `#[Test]`

Le système automatique repose sur l'attribut `Neo\Core\Testing\Attribute\Test`.

Il peut être posé :

- sur une classe
- sur une méthode publique

Signature actuelle :

```php
#[Test(
    type: 'auto',
    cases: [],
    route: null,
    httpMethod: 'GET',
    dataset: [],
    skip: false,
    extends: null
)]
```

Ce que fait `make:test:auto` :

- prépare le scaffold PHPUnit si besoin
- scanne tous les fichiers PHP du projet
- charge les classes qui contiennent `#[Test]`
- lit l'attribut au niveau classe et méthode
- déduit un type de test
- choisit un template
- génère le fichier dans `Tests/<Type>/`

Inférence du type si `type = auto` :

- `Repository` => `database`
- `Controller` => `feature`
- `Middleware` => `middleware`
- sinon => `unit`

Exemple sur une classe de service :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Services;

use Neo\Core\Testing\Attribute\Test;

#[Test(type: 'unit', cases: ['it_works', 'returns_slug'])]
final class SlugService
{
    public function slugify(string $value): string
    {
        return strtolower(trim(str_replace(' ', '-', $value)));
    }
}
```

Exemple sur un repository :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Database\Repository;

use Neo\Core\Database\ORM\Persistence\EntityRepository;
use Neo\Core\Testing\Attribute\Test;
use Neo\Src\Blog\Database\Entity\User;

#[Test(
    type: 'database',
    cases: ['find_by_email', 'create'],
    dataset: [
        'table' => 'users',
        'data' => [
            'firstname' => 'John',
            'email' => 'john@example.com',
        ],
    ],
)]
final class UserRepository extends EntityRepository
{
}
```

Exemple sur une méthode de contrôleur :

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\App\Controllers;

use Neo\Core\Controller\AbstractController;use Neo\Core\Http\Response\Types\Response;use Neo\Core\Routing\Attribute\MainRoute;use Neo\Core\Testing\Attribute\Test;

#[MainRoute(path: '/login', name: 'login')]
final class AuthController extends AbstractController
{
    #[Test(
        route: '/login',
        httpMethod: 'POST',
        cases: ['returns_success', 'rejects_invalid_credentials']
    )]
    public function login(): Response
    {
        return $this->jsonSuccess();
    }
}
```

Options utiles :

```bash
php bin/neo make:test:auto --project=Blog
php bin/neo make:test:auto --project=Blog --only=database
php bin/neo make:test:auto --project=Blog --dry-run
php bin/neo make:test:auto --project=Blog --force
php bin/neo run:test:all --project=Blog --coverage
```

## Deploiement

La commande `app:make:deployment` prépare un deploiement FTP a partir de `src/<Projet>/Config/deploy.config.php`.

Le flux implémente :

- patch temporaire de `app.config.php` en `prod`
- patch temporaire de `public/index.php`
- fusion du `composer.json` racine et du `composer.json` projet
- installation des dépendances en `--no-dev`
- compression de `vendor/`
- upload FTP du framework, du projet et du dossier public
- upload de `vendor.zip`
- éxécution d'un script temporaire de dézippage côté serveur

Clés attendues dans `deploy.config.php` :

- `ftp.host`
- `ftp.user`
- `ftp.pass`
- `remote.domain`
- `remote.framework_dir`
- `remote.public_dir`

Exemple :

```php
<?php
declare(strict_types=1);

return [
    'ftp' => [
        'host' => 'ftp.example.com',
        'user' => 'my-user',
        'pass' => 'my-pass',
    ],
    'remote' => [
        'domain' => 'example.com',
        'framework_dir' => 'domains/example.com/neo',
        'public_dir' => 'domains/example.com/public_html',
    ],
];
```

## Dépendances et prérequis

### PHP

- PHP `>= 8.5`

### Extensions PHP requises

- `ext-pdo`
- `ext-zip`
- `ext-libxml`
- `ext-dom`
- `ext-ftp`
- `ext-iconv`
- `ext-curl`
- `ext-simplexml`
- `ext-fileinfo`

### Dépendances principales

- `twig/twig`
- `twig/intl-extra`
- `psr/container`
- `matthiasmullie/minify`
- `wikimedia/less.php`
- `phpmailer/phpmailer`
- `predis/predis`

### Dépendances de développement

- `phpunit/phpunit`
- `phpstan/phpstan`

## Résume

NeoPHP couvre aujourd'hui :

- noyau applicatif multi-projets
- conteneur DI avec autowiring
- configuration par fichiers PHP
- couche HTTP, responses, sessions, cookies et flash
- routing par attributs
- contrôleurs et vues Twig
- pipeline d'assets CSS, JS et Less
- traduction par chaînes, un fichier par locale, synchronisation via CLI
- ORM Data Mapper : entités POPO annotées (`#[Entity]`, `#[Column]`, relations), `EntityManager`, `EntityRepository`
- migrations pilotées depuis les entités via `database:orm:diff`
- migrations de base de données et suivi des snapshots de schéma
- formulaires via `FormFactory` / `FormBuilder`, validation, upload et CSRF
- auth session / token, mot de passe, middlewares et `#[IsGranted]`
- events et crons
- cache, logs, mailer, profiler et gestion des erreurs
- CLI de génération et d'administration (`project:create`, `make:entity`, `database:orm:diff`, etc.)
- testing manuel et génération automatique via `#[Test]`
- déploiement FTP intégré

Le point clé du dépôt reste le même :

- `neo/` contient le moteur
- `src/` contient les applications
- `php bin/neo ...` pilote l'essentiel du workflow