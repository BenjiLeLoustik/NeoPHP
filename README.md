# NeoPHP

PHP 8.5 framework centered around:

- an application core in `neo/`
- an internal CLI in `bin/neo`
- isolated application projects in `src/<Project>/`

NeoPHP aims for a different balance than Symfony or Laravel.
The goal is not to stack layers, bundles, or a very large ecosystem, but to provide a readable, compact PHP core that can be used directly to build a complete application without leaving the repository.
The framework relies on a simple structure, an integrated CLI, auto-discovered core modules, and a multi-project workflow that stays explicit.

In practice, NeoPHP is aimed mainly at projects that want to move fast without adopting all the organizational complexity of large general-purpose frameworks.
Compared to Symfony, it greatly reduces configuration ceremony and fragmentation between components.
Compared to Laravel, it is more minimal, more direct in its architecture, and less dependent on a "magic" layer or a set of external tools.
If what you need is a smaller, more predictable framework that's easier to follow end-to-end in the source code, that's exactly where NeoPHP fits.

## Table of contents

- [Overview](#overview)
- [Repository architecture](#repository-architecture)
- [Core map](#core-map)
- [Execution cycle](#execution-cycle)
- [Project structure](#project-structure)
- [DI container and configuration](#di-container-and-configuration)
- [HTTP layer](#http-layer)
- [Routing and controllers](#routing-and-controllers)
- [Twig views, assets, and translations](#twig-views-assets-and-translations)
- [Database and QueryBuilder](#database-and-querybuilder)
- [ORM and repositories](#orm-and-repositories)
- [Data Mapper ORM (entities)](#data-mapper-orm-entities)
- [Seeding](#seeding)
- [Forms, upload, and validation](#forms-upload-and-validation)
- [Security: auth, password, middlewares, csrf](#security-auth-password-middlewares-csrf)
- [Events](#events)
- [Crons](#crons)
- [Cache, logs, mailer, profiler, and errors](#cache-logs-mailer-profiler-and-errors)
- [Markdown](#markdown)
- [CLI and generators](#cli-and-generators)
- [PHPUnit tests](#phpunit-tests)
- [Deployment](#deployment)
- [Dependencies and requirements](#dependencies-and-requirements)

## Overview

NeoPHP relies on two entry points:

- `public/index.php` for the HTTP runtime
- `bin/neo` for the CLI

The core goes through `Neo\App`, which:

- detects the current project
- initializes the container
- registers the current project's application paths
- automatically discovers `*Module.php` modules in `neo/Core/`
- orders these modules according to their dependencies, then runs `register()` / `boot()`
- activates Twig, the DB, assets, translation, auth, cache, crons, mailer, and profiler
- scans application controllers, routes, listeners, and crons
- executes the HTTP request or CLI command
- centralizes error handling

## Repository architecture

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
|   `-- <Project>/
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

The example project present in the repository is `src/Test/`.

## Core map

The `neo/Core/` core is organized by subsystem:

| Module | Description | Complexity | Progress | Doc |
|--------|-------------|:----------:|:----------:|-----|
| `Application/` | Current project detection (HTTP/CLI), path resolution, `project:*` commands | 🟢 Low | ✅ Stable | [README](neo/Core/Application/README.md) |
| `Asset/` | CSS / JS / Less compilation, manifest versioning, `asset()` Twig helper | 🟡 Medium | ✅ Stable | [README](neo/Core/Asset/README.md) |
| `Console/` | CLI framework: command scanning, `AbstractCommand`, colorized Input/Output | 🟡 Medium | ✅ Stable | [README](neo/Core/Console/README.md) |
| `Controller/` | `AbstractController` with HTTP helpers, auth, events, upload, dynamic extensions | 🟢 Low | ✅ Stable | [README](neo/Core/Controller/README.md) |
| `Cron/` | `#[Cron]` attribute, scanner, runner with lock, standard cron expressions | 🟡 Medium | ✅ Stable | [README](neo/Core/Cron/README.md) |
| `Database/` | Full Data Mapper ORM, QueryBuilder, diff migrations, forms, seeding | 🔴 High | ✅ Stable | [README](neo/Core/Database/README.md) |
| `DI/` | PSR-11 container, reflection-based autowiring, circular dependency detection | 🟡 Medium | ✅ Stable | [README](neo/Core/DI/README.md) |
| `Error/` | `ErrorHandler`, `FrameworkException`, differentiated dev/prod behavior | 🟢 Low | ✅ Stable | [README](neo/Core/Error/README.md) |
| `Event/` | Dispatcher, `#[AsListener]`, subscribers, priorities, JSON cache in prod | 🟡 Medium | ✅ Stable | [README](neo/Core/Event/README.md) |
| `Extension/` | Utility extensions (Array, Date, File, Html, Json, Number, Path, String, Url) | 🟢 Low | ✅ Stable | [README](neo/Core/Extension/README.md) |
| `Http/` | Request, Response, JsonResponse, RedirectResponse, HttpClient, Session, Flash, Cookie, Upload | 🟡 Medium | ✅ Stable | [README](neo/Core/Http/README.md) |
| `Module/` | Discovery of `*Module.php`, topological sort of dependencies, `register()`/`boot()` cycle | 🟡 Medium | ✅ Stable | [README](neo/Core/Module/README.md) |
| `Profiler/` | Dev debug bar, pluggable collectors (SQL, router, events, logs…) | 🟡 Medium | ✅ Stable | [README](neo/Core/Profiler/README.md) |
| `Routing/` | `#[Route]`/`#[MainRoute]` attributes, prod JSON cache, parameter injection, `debug:router` | 🟡 Medium | ✅ Stable | [README](neo/Core/Routing/README.md) |
| `Security/` | Session/token auth, JWT, `#[IsGranted]`, middlewares, CSRF | 🔴 High | ✅ Stable | [README](neo/Core/Security/README.md) |
| `Testing/` | `TestCase`, `DatabaseTestCase`, `FeatureTestCase`, auto scaffold via `#[Test]` | 🟡 Medium | 🔧 In progress | [README](neo/Core/Testing/README.md) |
| `Tools/Markdown/` | Dependency-free Markdown parser, block array, `markdown_blocks()` Twig function and `md_inline` filter | 🟢 Low | ✅ Stable | [README](neo/Core/Tools/Markdown/README.md) |
| `Translation/` | Domains, `LocaleManager`, cache, Twig, `translation:sync` | 🟡 Medium | ✅ Stable | [README](neo/Core/Translation/README.md) |
| `Utils/` | Cache (File/Redis/Array), Config, Logger, Notifications (Email/Slack/SMS), Scanner | 🟡 Medium | ✅ Stable | [README](neo/Core/Utils/README.md) |
| `Validator/` | Attribute constraints + separate validators, `ValidatorManager`, 11 constraints | 🟡 Medium | ✅ Stable | [README](neo/Core/Validator/README.md) |
| `View/` | Twig 3.x integration, extensions, `app` global variable, template cache | 🟢 Low | ✅ Stable | [README](neo/Core/View/README.md) |

Notable subfolders in `neo/Core/`:

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
Http/       -> Client/, File/, HttpClient/, Request/, Response/
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

## Execution cycle

### Over HTTP

`Neo\App` looks for a project by reading `src/*/Config/app.config.php` and compares the `access` key to `HTTP_HOST` / `SERVER_NAME`.

If only one project exists in `src/`, it is selected automatically.

### In the CLI

Commands that operate on an existing project generally expect `--project=ProjectName`.

Notable exceptions:

- `project:create`
- `project:sync`
- `app:serve`

Example:

```bash
php bin/neo cache:clear --project=Test
```

## Project structure

A project generated by `app:make:project` first contains:

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

Without the `--skeleton` option, the generator also adds:

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

Some folders are created later, when the feature is enabled:

- `App/Crons/` via `make:cron`
- `App/Event/Listener/` via `make:event` and `make:event:listener`
- `Database/Entity/` via `make:entity`
- `Database/Migrations/` on the first `database:orm:diff` or `database:migration:migrate`
- `Tests/` via `make:test` or `make:test:auto`

The sensitive configs `database.config.php`, `deploy.config.php`, `api.config.php`, and `mailer.config.php` are meant to be ignored by Git in the generated `.gitignore`.
The generator also ignores `Storage/`.

## DI container and configuration

The `Neo\Core\DI\Container` container provides:

- `set()` to register a service or a factory
- `get()` to resolve a service
- `bind()` to map an abstraction to an implementation
- `make()` to instantiate a class with runtime parameters
- reflection-based autowiring
- support for controller and service constructors

Example:

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
        $this->logger->info('Generating the report');

        return $this->cache->get('report.latest', []);
    }
}
```

### Configuration

The `Config` service loads every `*.config.php` file in the project and can merge `*.config.test.php` files during tests.

Example:

```php
$appName = $this->getConfig()->from('app')->get('general.name');
$timezone = $this->getConfig()->from('app')->get('date.timezone', 'UTC');
$twigOptions = $this->getConfig()->from('twig')->all();
```

Example `app.config.php`:

```php
<?php
declare(strict_types=1);

return [
    'general' => [
        'name' => 'Blog',
        'description' => 'My NeoPHP project',
    ],
    'environment' => 'dev',
    'access' => 'localhost:8000',
    'date' => [
        'timezone' => 'Europe/Paris',
    ],
];
```

## HTTP layer

The HTTP layer is made up mainly of:

- `Request` — incoming request
- `Response` / `JsonResponse` / `RedirectResponse` — HTTP responses
- `HttpClient` — cURL HTTP client for outgoing requests
- `Session` / `Cookie` / `Flash` — client state

### Request

`Request` notably exposes:

- `getMethod()`
- `getPath()`
- `query()`
- `body()`
- `header()`
- `file()`
- `getIp()`
- `getUserAgent()`
- `getPreviousUrl()`

Example:

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

`Response` is used to build basic HTTP responses.

Example:

```php
$response = new Response();
$response->setStatusCode(200);
$response->setHeader('Content-Type', 'text/plain; charset=UTF-8');
$response->setContent('OK');
return $response;
```

Shortcut examples via `AbstractController`:

```php
return $this->jsonSuccess(['saved' => true], 201);
return $this->jsonError('Not found', 404);
return $this->redirectToRoute('posts.index');
return $this->redirectToPath('/maintenance', 302);
```

### HttpClient

`HttpClientManager` lets you make outgoing HTTP requests via cURL. It returns a standard `Response` object.

```php
$client = $container->get(HttpClientManager::class);

// Simple GET request
$data = $client->request('GET', 'https://api.example.com/users')->toArray();

// JSON POST request with Bearer token
$response = $client->request('POST', '/api/articles', [
    'base_uri' => 'https://api.example.com',
    'bearer'   => $token,
    'json'     => ['title' => 'My article'],
]);
$response->getStatusCode(); // 201
$response->toArray();       // ['id' => 42, ...]
```

Common options: `base_uri`, `query`, `headers`, `bearer`, `json`, `body`, `auth_basic`, `timeout`, `max_redirects`.

### Session, cookie, and flash

The framework automatically configures the session from `session.config.php`.

Example in a controller:

```php
$this->getSession()->set('wizard.step', 2);
$step = $this->getSession()->get('wizard.step', 1);

$this->getCookie()->set('theme', 'dark');
$theme = $this->getCookie()->get('theme', 'light');

$this->getFlash()->add('success', 'Operation completed');
```

Twig exposes flash messages via `flashes()`:

```twig
{{ flashes() }}
```

## Routing and controllers

Routing is based on PHP attributes scanned in `src/<Project>/App/Controllers`.

Confirmed features:

- route prefix via `#[MainRoute(...)]`
- multi-method routes via `methods: [...]`
- dynamic parameters `{id}`
- optional parameters `{slug?}`
- regex constraints via `requirements`
- route caching outside the `dev` environment
- typed argument injection via the container

Simple example:

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

More complete example:

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

Helpers exposed by `AbstractController`:

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
- access to `Logger`, `Cache`, `Config`

Twig also exposes:

- `path()`
- `currentRoute()`

## Twig views, assets, and translations

### Twig views

Views are loaded from `src/<Project>/App/Views`.

Twig is initialized with:

- optional cache
- optional debug
- `twig/intl-extra`
- `app` global
- functions added by the framework

Example:

```twig
{% extends 'layouts/base_layout.html.twig' %}

{% block title %}Post list{% endblock %}

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

Source assets live in `src/<Project>/Assets/`.

The `AssetHandler` component:

- exposes `asset()`
- compiles `css`, `js`, and `less`
- minifies CSS and JS
- generates hashed file names
- writes `public/builds/<Project>/manifest.json`
- serves compiled files from `public/builds/<Project>/assets/`

Twig example:

```twig
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<script src="{{ asset('js/app.js') }}"></script>
```

Source tree:

```text
src/Blog/Assets/
|-- css/
|   `-- app.css
`-- js/
    `-- app.js
```

### Translations

Translations are loaded from `src/<Project>/Translations/<locale>.php`.

Available Twig functions:

- `translate()`
- `trans()`
- `getLocales()`
- `getLocale()`
- `isEnabledTranslation()`

Notable behavior:

- the locale is resolved from the config and cookies
- `setLocale()` persists the language in a `lang` cookie
- in the `dev` environment, a missing key is automatically registered in the current locale's file
- `translation:sync` lets you sync the keys across all locale files

Example `src/Blog/Translations/fr.php` file:

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

Twig example:

```twig
<h1>{{ trans('Bienvenue sur le blog') }}</h1>
<button>{{ trans('Enregistrer') }}</button>

{{-- With parameters --}}
{{ trans('Bonjour :name !', {'name': user.getName()}) }}
{{ 'Bonjour :name !'|trans({'name': user.getName()}) }}
```

Example in a controller:

```php
#[Route(path: '/change-locale/{locale}', name: 'change.locale', methods: ['GET'])]
public function changeLocale(string $locale, TranslationManager $translator): Response
{
    $translator->setLocale($locale);
    return $this->redirectBack('home.index');
}
```

### Utility extensions

The `neo/Core/Extension/` folder exposes reusable helpers at two levels:

- in controllers via `getString()`, `getDate()`, `getFile()`, `getHtml()`, `getJson()`, `getNumber()`, `getPath()`, `getUrl()`, and `getArray()`
- in Twig via automatically registered functions and filters

Available families:

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

Examples:

```php
$slug = $this->getString()->slugify('My Example Title');
$price = $this->getNumber()->currency(19.99, 'EUR');
```

```twig
{{ 'My Example Title'|slugify }}
{{ currency(19.99, 'EUR') }}
{{ date_format(post.created_at, 'd/m/Y H:i') }}
{{ path_join('uploads', user.avatar) }}
```

## Database and QueryBuilder

The PDO connection is driven by `Config/database.config.php` via `DatabaseConnection`.

Minimal example:

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

### Schema tools

The framework ships a dedicated database CLI:

- `database:create`
  creates the database declared in `database.config.php`
- `make:entity`
  generates a Data Mapper entity (POPO) and its repository in `Database/Entity/` and `Database/Repository/`
- `database:orm:diff`
  compares entities against the current database and generates a migration file in `Database/Migrations/`
- `database:migration:migrate`
  applies all pending migrations
- `database:migration:rollback`
  rolls back the last applied batch
- `database:migration:status`
  displays migration status and flags a drift between the current schema and the latest snapshot

Notable behaviors:

- `make:entity` is interactive: it asks for the entity name, its properties, and their types
- `make:entity --no-repository` skips repository generation
- `database:orm:diff --dry-run` shows the diff without writing a file
- `database:orm:diff --connection=<name>` targets a specific connection for multi-database projects
- the internal `neo_migrations` and `neo_schema_snapshots` tables are excluded from introspection
- generated migrations are written to `src/<Project>/Database/Migrations/`

Examples:

```bash
php bin/neo database:create --project=Blog
php bin/neo make:entity Post --project=Blog
php bin/neo database:orm:diff --project=Blog --name=add_posts_table
php bin/neo database:orm:diff --project=Blog --name=add_posts_table --dry-run
```

### QueryBuilder

`QueryBuilder` notably covers:

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

Example:

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

Example with a transaction:

```php
(new QueryBuilder())
    ->table('posts')
    ->transaction(function (QueryBuilder $qb): void {
        $qb->table('posts')->insert([
            'user_id' => 1,
            'title' => 'Transactional post',
            'content' => 'Content',
        ]);
    });
```

### Migrations

Migrations live in `src/<Project>/Database/Migrations/`.

Each migration exposes:

- `up(DatabaseManager $db): void`
- `down(DatabaseManager $db): void`

The runner maintains two technical tables:

- `neo_migrations` for the history of applied migrations
- `neo_schema_snapshots` to store a schema hash after execution

The snapshot lets `database:migration:status` warn when the current schema has changed since the last generated or applied migration.

Example workflow:

```bash
php bin/neo make:entity Post --project=Blog
php bin/neo database:orm:diff --project=Blog --name=initial_schema
php bin/neo database:migration:status --project=Blog
php bin/neo database:migration:migrate --project=Blog
php bin/neo database:migration:rollback --project=Blog
```

Minimal migration example:

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

## ORM and repositories

NeoPHP's ORM is a Data Mapper. Entities are POPOs annotated with mapping attributes. No parent class is required. Persistence goes through the `EntityManager`.

### EntityManager

`EntityManager` is the entry point for all persistence operations.

Main API:

- `persist(object $entity)` — registers an entity for insert or update
- `remove(object $entity)` — marks an entity for deletion
- `flush()` — writes all changes to the database
- `find(string $class, mixed $id)` — lookup by primary key
- `getRepository(string $class)` — returns the entity's repository
- `wrapInTransaction(callable $callback)` — runs a callback within a transaction
- `contains(object $entity)` — checks whether an entity is managed by the UnitOfWork
- `clear()` — clears the identity map

In a controller, `EntityManager` is accessible via `$this->entityManager` (registered by `DatabaseControllerExtension`).

Example:

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

`EntityRepository` is the base class generated by `make:entity`.

Available API:

- `find($id)` — lookup by primary key
- `findAll()` — returns all entities
- `findBy(array $criteria, array $orderBy, ?int $limit, ?int $offset)` — search by criteria
- `findOneBy(array $criteria, array $orderBy)` — returns a single result
- `count(array $criteria)` — counts entities matching criteria

Repository example:

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

Usage in a controller:

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

Relations available via attributes:

- `#[OneToOne(targetEntity: ..., inversedBy: ...)]` with `#[JoinColumn]`
- `#[ManyToOne(targetEntity: ..., inversedBy: ...)]` with `#[JoinColumn]`
- `#[OneToMany(targetEntity: ..., mappedBy: ...)]`
- `#[ManyToMany(targetEntity: ..., inversedBy: ...)]` with `#[JoinTable]`

Collections (`OneToMany`, `ManyToMany`) use the `Collection` class. Loading is lazy by default, handled through transparent proxies.

Example entity with relations:

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

See the [Data Mapper ORM (entities)](#data-mapper-orm-entities) section for creating entities via the CLI and the migration workflow.

## Data Mapper ORM (entities)

NeoPHP's ORM is built on the Data Mapper pattern. `make:entity` creates an entity and its repository. `database:orm:diff` generates the migration from the difference between the entities and the database.

### Creating an entity

```bash
php bin/neo make:entity Post --project=Blog
```

The generator is interactive: it asks for the name, then the properties and their types.

Example entity generated in `Database/Entity/Post.php`:

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

Available scalar types:

- `string`, `text`
- `integer`, `bigint`, `smallint`
- `boolean`, `float`, `decimal`
- `datetime`, `date`, `time`
- `json`

Available relations:

- `#[OneToOne(...)]` with `#[JoinColumn(...)]`
- `#[ManyToOne(...)]` with `#[JoinColumn(...)]`
- `#[OneToMany(...)]`
- `#[ManyToMany(...)]` with `#[JoinTable(...)]`

`OneToMany` and `ManyToMany` sides use `Collection` to manage collections of related objects.

> **ManyToMany note — automatic persistence on flush:** ManyToMany collections are now persisted automatically on `flush()`. A snapshot of the collection is taken when the entity is loaded; at flush time, the UoW computes the diff (additions/removals) and syncs the join table without manual action.

```php
$article = $em->find(Article::class, 1);
$article->getTags()->add($em->find(Tag::class, 5)); // Add
$article->getTags()->remove($existingTag);           // Remove
$em->flush(); // Automatically syncs the article_tag table
```

### Data Mapper repository

The generated repository extends `EntityRepository`:

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

`--no-repository` option available to skip repository generation.

### Generating the migration from entities

```bash
# Compare entities with the current database and generate the migration
php bin/neo database:orm:diff --project=Blog --name=add_posts_table

# Preview the diff without writing a file
php bin/neo database:orm:diff --project=Blog --name=add_posts_table --dry-run

# Apply
php bin/neo database:migration:migrate --project=Blog
```

On a multi-database project, the `--connection=<name>` option targets a specific connection.

Migrations generated by `database:orm:diff` follow the same `up()` / `down()` format as manual migrations and are stored in `Database/Migrations/`.

## Forms, upload, and validation

### Forms

NeoPHP ships:

- `FormFactory` — entry point for creating forms
- `FormBuilder` — fluent building API
- `Form` — form object
- `FieldType` — enum of available field types
- Twig rendering
- built-in CSRF
- constraint-based validation

Field types available via `FieldType`:

- `text`, `textarea`
- `email`, `password`
- `number`
- `hidden`
- `checkbox`
- `select`
- `date`, `datetime-local`

Available Twig helpers:

- `form_start()`
- `form_end()`
- `form_row()`
- `form_widget()`
- `form_label()`
- `form_error()`
- `form_errors()`
- `form_csrf()`

Example built via `FormFactory`:

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
            ->add('firstname', 'text', ['label' => 'First name', 'required' => true])
            ->add('email', 'email', ['label' => 'Email'])
            ->getForm();
    }
}
```

Twig example:

```twig
{{ form_start(form) }}
{{ form_row(form, 'firstname') }}
{{ form_row(form, 'email') }}
{{ form_end(form) }}
```

### Upload in a controller

The application entry point is `AbstractController::upload()`.

Signature:

```php
$filename = $this->upload(
    string $field,
    string $name,
    array $extensions,
    string $directory
);
```

This helper:

- retrieves the file via `Request::file()`
- checks the PHP upload
- reads the original extension
- rejects `php`, `phtml`, `exe`, `sh`, `js`
- checks the provided whitelist
- creates the target folder in `src/<Project>/Assets/<directory>`
- moves the file
- returns the final file name

Example:

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

Then display it:

```twig
<img src="{{ asset('uploads/avatars/' ~ user.getAvatar()) }}" alt="Avatar">
```

### Validation

The validator relies on constraint attributes placed on the properties of any class (entity, DTO, etc.).

Since the refactor, each constraint is **split into two files**: a PHP attribute in `Assert/` (which declares the parameters) and a validator in `Validator/` (which contains the logic). `ValidatorManager` resolves the validator via the DI container using the constraint's `validatedBy()` method.

Constraints present in the framework:

- `NotBlank`
- `Length`
- `Email`
- `Date`
- `Choice`
- `Range`
- `Regex`
- `Url`
- `Unique`
- `Exists` — checks that a value exists in the database (useful for validating a foreign key)
- `EqualToField`

Example on a DTO:

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
    #[NotBlank(message: 'First name is required.')]
    public string $firstname = '';

    #[NotBlank(message: 'Email is required.')]
    #[Email(message: 'Email is invalid.')]
    public string $email = '';

    #[Length(min: 8, message: 'Password must be at least 8 characters.')]
    public string $password = '';

    #[EqualToField(field: 'password', message: 'Passwords must match.')]
    public string $password_confirm = '';
}
```

## Seeding

The Seeder module lets you populate the database with reference or demo data.

A seeder is a class annotated `#[Seeder]` that implements `SeedInterface::run(EntityManager $em)`:

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

The `#[Seeder]` attribute configures two parameters:

| Parameter | Default | Description |
|-----------|--------|-------------|
| `order` | `0` | Increasing execution order |
| `group` | `'reference'` | `'reference'` for stable data, `'demo'` for development data |

Available commands:

```bash
# Generate a seeder
php bin/neo database:make:seed CountrySeeder --project=Blog --order=10 --group=reference

# Preview without running
php bin/neo database:run:seed --project=Blog --dry-run

# Run 'reference' seeders (default)
php bin/neo database:run:seed --project=Blog

# Include development seeders
php bin/neo database:run:seed --project=Blog --dev

# Filter by group
php bin/neo database:run:seed --project=Blog --group=demo
```

## Security: auth, password, middlewares, csrf

### Authentication

Auth is driven from `app.config.php`.

The framework supports two guards:

- `session`
- `token`

The `token` guard relies on `JwtManager`.

Typical configuration:

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

`AuthManager` API:

- `attempt()`
- `login()`
- `logout()`
- `check()`
- `user()`
- `hasRole()`
- `generateToken()`

Session login example:

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
            return $this->jsonError('Invalid credentials', 401);
        }

        return $this->redirectToRoute('dashboard.index');
    }
}
```

Token login example:

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
            return $this->jsonError('Invalid credentials', 401);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if ($user === null) {
            return $this->jsonError('User not found', 401);
        }

        return $this->jsonSuccess([
            'token' => $this->auth()->generateToken($user),
        ]);
    }
}
```

Twig exposes:

- `auth_check()`
- `auth_user()`
- `auth_has_role()`
- `csrf_token()`

### PasswordManager

The `PasswordManager` service provides:

- `hash()`
- `verify()`
- `needsRehash()`
- `generate()`
- `getInfo()`

Example:

```php
$hash = $this->getPasswordManager()->hash('secret123');
$ok = $this->getPasswordManager()->verify('secret123', $hash);
```

### Middlewares

Supported attributes:

- `#[Middleware(...)]` — attaches a middleware to a class or method
- `#[RateLimit(...)]` — rate limit on a route
- `#[Maintenance(...)]` — maintenance mode
- `#[IsGranted(roles: [...])]` — role-based access, shortcut for `RoleMiddleware`

Core middlewares:

- `AuthMiddleware` — checks that the user is authenticated
- `GuestMiddleware` — checks that the user is not logged in
- `RoleMiddleware` — checks a specific role
- `IsGrantedMiddleware` — checks one or more roles via `#[IsGranted]`
- `RateLimitMiddleware` — general rate limiting
- `AuthRateLimitMiddleware` — rate limiting on authentication
- `CsrfMiddleware` — CSRF validation on POST/PUT/PATCH/DELETE requests

Application middleware example:

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

Usage example with `#[Middleware]`:

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

Example with `#[IsGranted]`:

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

The CSRF manager stores tokens in the session under `_csrf_tokens`.

Behavior:

- generation via `generateToken()`
- default expiration of 3600 seconds
- validation via `validateToken()`
- integration in forms via `form_csrf()` and `csrf_token()`

## Events

NeoPHP ships an event dispatcher and several core events:

- `RequestEvent`
- `ResponseEvent`
- `ExceptionEvent`

Application listeners are expected in `src/<Project>/App/Event/Listener`.

They can be declared:

- via `#[AsListener(event: ..., priority: ...)]`
- via `EventSubscriberInterface`

Full example:

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

Example in a controller:

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

NeoPHP ships a scheduled task system runnable via the CLI.

Application crons are expected in the current project and can be run manually or automatically via
the operating system.

### Creating a cron

To generate a new cron:

```bash
php bin/neo make:cron <CronName> --project=Blog
```

Example:

```bash
php bin/neo make:cron CleanupTempFiles --project=Blog
```

The generator automatically creates the cron file in the target project.

### Listing crons

To display all crons available in a project:

```bash
php bin/neo cron:list --project=Blog
```

This command notably shows:

- the cron name
- its description
- its frequency
- its status

### Running crons

To run all of a project's crons:

```bash
php bin/neo cron:run --project=Blog
```

This is the command that should be scheduled automatically by the operating system.

### Automatic cron execution

#### Linux

On Linux, crons are usually driven via `crontab`.

Open the cron configuration:

```bash
crontab -e
```

Run NeoPHP crons every minute:

```bash
* * * * * php /path/to/project/bin/neo cron:run --project=Blog
```

Concrete example:

```bash
* * * * * php /var/www/neophp/bin/neo cron:run --project=Blog
```

Check cron logs:

```bash
grep CRON /var/log/syslog
```

#### macOS

macOS also supports `crontab`.

Open the configuration:

```bash
crontab -e
```

Add:

```bash
* * * * * php /path/to/project/bin/neo cron:run --project=Blog
```

Example:

```bash
* * * * * php /Users/benjamin/Sites/neophp/bin/neo cron:run --project=Blog
```

Check scheduled tasks:

```bash
crontab -l
```

#### Windows

On Windows, use Task Scheduler.

Command to run:

```bash
php C:\path\to\project\bin\neo cron:run --project=Blog
```

Example:

```bash
php C:\Sites\NeoPHP\bin\neo cron:run --project=Blog
```

Recommended configuration:

- trigger: every minute
- program: `php.exe`
- arguments:

```bash
C:\Sites\NeoPHP\bin\neo cron:run --project=Blog
```

Task Scheduler can be opened with:

```text
Win + R -> taskschd.msc
```

#### Docker

Example with a simple loop:

```bash
while true; do
    php bin/neo cron:run --project=Blog
    sleep 60
done
```

Example via `docker-compose`:

```yaml
services:
  cron:
    command: sh -c "while true; do php bin/neo cron:run --project=Blog; sleep 60; done"
```

### Recommendations

In production, it is recommended to:

- run `cron:run` every minute
- log errors via the `Logger`
- avoid overly long blocking operations
- use queues for heavy processing
- monitor executions via application or system logs

## Cache, logs, mailer, profiler, and errors

### Cache

The `Cache` service is driven by `cache.config.php`.

Available drivers:

- `files`
  storage in `src/<Project>/Storage/<path>`
- `redis`
  via `predis/predis`
- `array`
  in-memory storage for short-lived use or tests

API:

- `set()`
- `get()`
- `delete()`
- `clear()`
- `has()`
- `remember()`

Example:

```php
$this->getCache()->set('homepage.posts', $posts, 600);
$posts = $this->getCache()->get('homepage.posts', []);
$stats = $this->getCache()->remember('stats.daily', 300, fn() => $service->buildStats());
```

### Logger

The `Logger` service reads `logger.config.php` and handles:

- log levels
- channels
- rotation
- zip archiving

Supported levels:

- `debug`
- `info`
- `notice`
- `warning`
- `error`
- `critical`
- `alert`
- `emergency`

Example:

```php
$this->getLogger()->channel('framework')->error(
    'Business error',
    ['post_id' => 12],
    'PostController::show'
);
```

### Mailer

The `neo/Core/Utils/Mailer/` folder registers a `Mailer` service based on `PHPMailer`.

Configuration:

- `src/<Project>/Config/mailer.config.php`
- current driver via `default`
- sender via `from`
- SMTP via `drivers.smtp`

Main API:

- `to()`
- `subject()`
- `body()`
- `template()`
- `cc()`
- `bcc()`
- `attach()`
- `send()`
- `getSentMails()`

In a controller, `getMailer()` is available via the controller extension.

Example:

```php
$sent = $this->getMailer()
    ->to('user@example.com', 'John Doe')
    ->subject('Welcome')
    ->template('emails/welcome.html.twig', [
        'user' => $user,
    ])
    ->send();
```

If the mailer is disabled, sending is skipped and a warning is logged.

### Profiler

The `neo/Core/Profiler/` folder activates a debug bar only over HTTP and only when `app.config.php` sets `environment = dev`.

Exposed collectors:

- HTTP request
- resolved route and parameters
- SQL queries
- dispatched events
- logs
- authenticated user
- resolved translations and missing keys
- sent emails

The toolbar is injected into HTML responses.
It is skipped for `JsonResponse`, `RedirectResponse`, and non-HTML content.

### Error handling

`ErrorHandler`:

- intercepts exceptions and PHP errors
- logs errors
- dispatches an `ExceptionEvent`
- renders `errors/<code>.html.twig` if present
- otherwise provides an HTML fallback
- shows more detail in `dev`

Example error views:

```text
src/Blog/App/Views/errors/404.html.twig
src/Blog/App/Views/errors/500.html.twig
```

Example `404.html.twig`:

```twig
{% extends 'layouts/base_layout.html.twig' %}

{% block content %}
    <h1>404</h1>
    <p>{{ message }}</p>
{% endblock %}
```

## Markdown

The `Tools/Markdown` module provides a dependency-free Markdown parser. It converts Markdown text or a `.md` file into an array of structured blocks, rendered via Twig.

### Usage from a template

The `markdown_blocks()` function is available in every Twig template:

```twig
{# From a .md file (path relative to ROOT_DIR) #}
{% include 'markdown/document.html.twig'
    with { blocks: markdown_blocks('neo/Core/Asset/README.md') } %}

{# From a variable containing raw Markdown #}
{% set blocks = markdown_blocks(article.content) %}
```

The `md_inline` filter applies inline formatting (**bold**, *italic*, `code`, links):

```twig
{% for block in blocks %}
    {% if block.type == 'heading' %}
        <h{{ block.level }}>{{ block.text|md_inline|raw }}</h{{ block.level }}>
    {% elseif block.type == 'paragraph' %}
        <p>{{ block.text|md_inline|raw }}</p>
    {% endif %}
{% endfor %}
```

### Usage from PHP

```php
$manager = $container->get(MarkdownManager::class);

// From a file
$blocks = $manager->blocks('docs/guide.md');

// From a string
$blocks = $manager->parse("## Title\n\nContent.");
```

Block types returned: `heading`, `paragraph`, `code`, `list`, `table`, `quote`, `hr`.

## CLI and generators

Display global help:

```bash
php bin/neo
```

Display help for a command:

```bash
php bin/neo <command> --help
```

The console automatically loads:

- the framework's native commands in `neo/**/Commands/`
- application commands in `src/<Project>/App/Commands/`

Available native commands:

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

### Main generators

Examples:

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

Example interactive config command:

```bash
php bin/neo make:config mail --project=Blog
```

You could then enter, for example:

- `smtp.host`
- `smtp.port`
- `smtp.user`
- `smtp.pass`

The generator will write a nested PHP array.

### Application commands

`app:make:command` lets you generate a command in the target project. Once created in `src/<Project>/App/Commands/`, it is automatically detected by the console alongside native commands.

Example:

```bash
php bin/neo app:make:command CleanupLogs --name=logs:clean --category=maintenance --project=Blog
php bin/neo logs:clean --project=Blog
```

### Project maintenance

Examples:

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

## PHPUnit tests

The framework ships a per-project test layer with PHPUnit 13.2.

Available commands:

- `make:test`
- `make:test:auto`
- `run:test`
- `run:test:all`

On the first `make:test` or `make:test:auto`, NeoPHP generates:

- `src/<Project>/Tests/bootstrap.php`
- `src/<Project>/Tests/phpunit.xml`
- `src/<Project>/Tests/Config/database.config.test.php`
- the `Unit`, `Feature`, `Database`, `Middleware` folders

Base classes:

- `TestCase`
- `FeatureTestCase`
- `DatabaseTestCase`
- `MiddlewareTestCase`

Confirmed features:

- HTTP request simulation for feature tests
- transactions and automatic rollback for database tests
- config overrides via `*.config.test.php`
- dev-to-test schema sync
- `junit.xml` reports and HTML coverage

### Manual tests

Examples:

```bash
php bin/neo make:test UserServiceTest --type=unit --project=Blog
php bin/neo make:test UserControllerTest --type=feature --project=Blog
php bin/neo make:test UserRepositoryTest --type=database --project=Blog
php bin/neo make:test AuthMiddlewareTest --type=middleware --project=Blog
```

### Automatic generation with `#[Test]`

The automatic system relies on the `Neo\Core\Testing\Attribute\Test` attribute.

It can be placed:

- on a class
- on a public method

Current signature:

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

What `make:test:auto` does:

- prepares the PHPUnit scaffold if needed
- scans all PHP files in the project
- loads classes that contain `#[Test]`
- reads the attribute at the class and method level
- infers a test type
- picks a template
- generates the file in `Tests/<Type>/`

Type inference when `type = auto`:

- `Repository` => `database`
- `Controller` => `feature`
- `Middleware` => `middleware`
- otherwise => `unit`

Example on a service class:

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

Example on a repository:

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

Example on a controller method:

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

Useful options:

```bash
php bin/neo make:test:auto --project=Blog
php bin/neo make:test:auto --project=Blog --only=database
php bin/neo make:test:auto --project=Blog --dry-run
php bin/neo make:test:auto --project=Blog --force
php bin/neo run:test:all --project=Blog --coverage
```

## Deployment

The `app:make:deployment` command prepares an FTP deployment from `src/<Project>/Config/deploy.config.php`.

The flow implements:

- temporary patch of `app.config.php` to `prod`
- temporary patch of `public/index.php`
- merge of the root `composer.json` and the project `composer.json`
- dependency installation with `--no-dev`
- compression of `vendor/`
- FTP upload of the framework, the project, and the public folder
- upload of `vendor.zip`
- execution of a temporary unzip script on the server side

Expected keys in `deploy.config.php`:

- `ftp.host`
- `ftp.user`
- `ftp.pass`
- `remote.domain`
- `remote.framework_dir`
- `remote.public_dir`

Example:

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

## Dependencies and requirements

### PHP

- PHP `>= 8.5`

### Required PHP extensions

- `ext-pdo`
- `ext-zip`
- `ext-libxml`
- `ext-dom`
- `ext-ftp`
- `ext-iconv`
- `ext-curl`
- `ext-simplexml`
- `ext-fileinfo`

### Main dependencies

- `twig/twig`
- `twig/intl-extra`
- `psr/container`
- `matthiasmullie/minify`
- `wikimedia/less.php`
- `phpmailer/phpmailer`
- `predis/predis`

### Development dependencies

- `phpunit/phpunit`
- `phpstan/phpstan`

## Summary

NeoPHP currently covers:

- multi-project application core
- DI container with autowiring
- configuration via PHP files
- HTTP layer, responses, sessions, cookies, and flash
- attribute-based routing
- controllers and Twig views
- CSS, JS, and Less asset pipeline
- string-based translation, one file per locale, CLI sync
- Data Mapper ORM: annotated POPO entities (`#[Entity]`, `#[Column]`, relations), `EntityManager`, `EntityRepository`
- entity-driven migrations via `database:orm:diff`
- database migrations and schema snapshot tracking
- forms via `FormFactory` / `FormBuilder`, validation, upload, and CSRF
- session / token auth, password, middlewares, and `#[IsGranted]`
- events and crons
- cache, logs, mailer, profiler, and error handling
- generation and admin CLI (`project:create`, `make:entity`, `database:orm:diff`, etc.)
- manual testing and automatic generation via `#[Test]`
- built-in FTP deployment

The key point of the repository stays the same:

- `neo/` holds the engine
- `src/` holds the applications
- `php bin/neo ...` drives most of the workflow