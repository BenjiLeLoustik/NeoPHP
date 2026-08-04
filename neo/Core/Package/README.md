# Package

The `Package` module lets a NeoPHP project depend on external, Composer-installed
packages that plug directly into the framework's discovery mechanisms — routing,
views, listeners, cron jobs, console commands, and migrations — without ever
copying or symlinking a single file into the project.

A package is read *in place*, wherever Composer put it (typically `vendor/...`),
by the same scanners (`ScannerFileManager`) that already scan a project's own
`App/Controllers`, `App/Event/Listener`, etc. Only package-provided default
configuration is ever copied into the project, because configuration is meant
to be edited by the developer once installed.

---

## Summary

1. [Structure](#structure)
2. [Package Folder Convention](#package-folder-convention)
3. [PackageInterface](#packageinterface)
4. [AbstractPackage](#abstractpackage)
5. [Declaring a Package](#declaring-a-package)
6. [What Gets Discovered, and How](#what-gets-discovered-and-how)
7. [Configuration](#configuration)
8. [Entities and Repositories](#entities-and-repositories)
9. [Migrations](#migrations)
10. [Assets](#Assets)
11. [Local Development](#local-development)
12. [Full Example](#full-example)

---

## Structure

```
Package/
├── PackageManager.php                # Copies a package's default config into the project
├── PackageModule.php                 # DI registration, reads app.config.php's 'packages' key
├── Interface/
│   └── PackageInterface.php          # Contract every package must implement
├── Abstract/
│   └── AbstractPackage.php           # Convention-based default path resolution
└── Exception/
    └── PackageException.php
```

---

## Package Folder Convention

A NeoPHP package mirrors the structure of a NeoPHP project. Every folder below
is optional — a package only needs the ones it actually uses.

```
{package-root}/
├── composer.json           # PSR-4 autoload, package name, requirements
├── README.md
├── src/
│   ├── {Name}Package.php   # The package's entry point, implements PackageInterface
│   ├── Controllers/        # Scanned like a project's App/Controllers
│   ├── Templates/          # Scanned like a project's Templates/, exposed as a Twig namespace
│   ├── Listeners/          # Scanned like a project's App/Event/Listener
│   ├── Crons/               # Scanned like a project's App/Crons
│   ├── Commands/            # Scanned like a project's App/Commands
│   └── Assets/               # Package-owned static assets, served by the package itself
├── config/
│   └── *.config.php         # Default configuration, copied once into the project
└── database/
    ├── Entity/               # PSR-4 autoloaded, referenced explicitly by class-string
    ├── Repository/           # PSR-4 autoloaded, referenced explicitly by class-string
    └── Migrations/            # Read directly from the package, never copied
```

`Storage/` may be added at the package root if the package needs its own
writable runtime directory (cache, temp files) that isn't tied to a specific
project.

---

## PackageInterface

**File:** `Interface/PackageInterface.php`

```php
interface PackageInterface
{
    public function getName(): string;   // Unique name, used as Twig namespace and config sub-folder
    public function getPath(): string;   // Absolute path to the package root

    public function getControllerPath(): ?string;
    public function getViewPath(): ?string;
    public function getListenersPath(): ?string;
    public function getCronsPath(): ?string;
    public function getCommandsPath(): ?string;
    public function getMigrationsPath(): ?string;
    public function getConfigPath(): ?string;

    public function register(Container $container): void;
}
```

Every path getter returns `null` when the corresponding folder doesn't exist —
consumers (`RouterManager`, `EventManager`, ...) simply skip it, no error is
raised for an unused folder.

`register(Container $container)` is the package's hook into the DI container —
use it to bind interfaces to implementations, register services, or anything
else a normal `Module::register()` would do.

---

## AbstractPackage

**File:** `Abstract/AbstractPackage.php`

Most packages don't need to implement every getter by hand — `AbstractPackage`
resolves each path by convention, relative to `getPath()`, and returns `null`
automatically if the folder doesn't exist:

| Getter | Resolved path |
|---|---|
| `getControllerPath()` | `{path}/src/Controllers` |
| `getViewPath()` | `{path}/src/Templates` |
| `getListenersPath()` | `{path}/src/Listeners` |
| `getCronsPath()` | `{path}/src/Crons` |
| `getCommandsPath()` | `{path}/src/Commands` |
| `getMigrationsPath()` | `{path}/database/Migrations` |
| `getConfigPath()` | `{path}/config` |

A typical package only has to implement `getName()` and `getPath()`:

```php
final class HelloPackage extends AbstractPackage
{
    public function getName(): string { return 'Hello'; }
    public function getPath(): string { return dirname(__DIR__); }
}
```

Override any individual getter if your package doesn't follow the convention
for that one folder.

---

## Declaring a Package

1. Require it via Composer, like any other dependency:
```bash
composer require vendor-name/hello-package
```

2. Declare it in the project's `Config/app.config.php`:
```php
return [
    // ...
    'packages' => [
        \Vendor\HelloPackage\HelloPackage::class,
    ],
];
```

3. That's it. On the next request (or CLI command), `PackageModule` instantiates
   the class, calls `register()`, and every scanner picks up its folders
   automatically.

---

## What Gets Discovered, and How

No file is ever copied or symlinked for code. Every scanner that already walks
a project's own folders (`RouterManager`, `ViewManager`, `EventManager`,
`ExtensionManager`, `ConsoleManager`, `CronScanner`, migration commands) simply
receives the package's path *in addition to* the project's own, and reads it
directly from wherever Composer installed it — typically `vendor/vendor-name/hello-package/`.

| Domain | Package getter | Consumer |
|---|---|---|
| Routes | `getControllerPath()` | `RouterManager` |
| Views | `getViewPath()` | `ViewManager` (registered as a Twig namespace `@{Name}`) |
| Event listeners | `getListenersPath()` | `EventManager` |
| Cron jobs | `getCronsPath()` | `CronScanner` |
| CLI commands | `getCommandsPath()` | `ConsoleManager` |
| Controller/Twig extensions | `getPath()` (whole tree) | `ExtensionManager` |
| Migrations | `getMigrationsPath()` | Migration commands |

Because nothing is copied, editing a package's source (in local development)
takes effect immediately, on the very next request — no sync step, no cache
to clear beyond the usual route/listener cache in debug mode.

---

## Configuration

Configuration is the one exception: a package's default `*.config.php` files
**are copied**, once, into the project — because configuration is meant to be
readable and editable by the developer inside their own project, not hidden
inside `vendor/`.

On first boot, `PackageModule` copies every file from `getConfigPath()` into:

```
{project}/Config/Packages/{PackageName}/
```

Only files that don't already exist at the destination are copied — a
developer's local edits are never overwritten by a `composer update`.

```php
$this->getConfig()->from('hello')->get('greeting');
// Reads {project}/Config/Packages/Hello/hello.config.php
```

---

## Entities and Repositories

`database/Entity/` and `database/Repository/` are organizational conventions
only — NeoPHP's ORM (`EntityManager`, `EntityRepository`) resolves entities by
explicit `class-string`, never by scanning a directory. A package's entities
and repositories work out of the box as soon as Composer's PSR-4 autoload
knows about them; no wiring through `PackageInterface` is needed for this.

---

## Migrations

Migration commands (`database:migration:migrate`, `rollback`, `status`) run
migrations directly from `getMigrationsPath()`, in addition to the project's
own `Database/Migrations/` — the files are never copied.

**Naming convention (required):** every migration class name must be prefixed
with the package's name, to avoid collisions with the project's own migrations
or another package's:

```
MigrationVersion_{PackageName}_{timestamp}_{Description}.php
```

```php
final class MigrationVersion_Hello_20260101_000000_CreateGreetingsTable implements MigrationInterface
{
    public function up(DatabaseManager $db): void { /* ... */ }
    public function down(DatabaseManager $db): void { /* ... */ }
}
```

---

## Assets

A package can ship its own static assets (CSS, JS, images, fonts) under
`src/Assets/`. Unlike controllers, views, or listeners, these files are
neither scanned nor copied — they are served on demand, straight from
wherever Composer installed the package, by a dedicated core controller.

```
{package-root}/
└── src/
    └── Assets/
        ├── css/
        │   └── style.css
        └── js/
            └── app.js
```

### Serving package assets

`PackageAssetController` (`neo/Core/Package/Controllers/PackageAssetController.php`)
exposes every package's `getAssetsPath()` under a single route:

```
GET /packages-assets/{package}/{path}
```

Reference a package's asset in a template using its `getName()` value:

```twig
<link rel="stylesheet" href="/packages-assets/Hello/css/style.css">
<script src="/packages-assets/Hello/js/app.js"></script>
```

The controller resolves `{path}` strictly inside the package's `Assets/`
folder (path traversal is rejected) and returns a `404` for an unknown
package, a missing file, or any path escaping that folder.

### Limitations

- **No compilation pipeline.** Unlike a project's own `Assets/` (compiled
  and minified by `AssetManager` into `public/builds/`), package assets are
  served exactly as shipped. A package author is responsible for delivering
  already-minified files if needed.
- **Served through PHP**, not directly by the web server — acceptable for
  typical package assets (icons, a small admin dashboard's CSS/JS), but not
  a replacement for the project's own static asset pipeline. For
  high-traffic assets, the web server can optionally be configured to
  intercept `/packages-assets/{Name}/...` and serve the file directly from
  `vendor/vendor-name/{package}/src/Assets/`, bypassing PHP entirely — this
  is an infrastructure-level optimization, outside the framework's scope.

### Using package Less/CSS sources in a project's own pipeline

Less resolves `@import` by filesystem path, not by URL, so a project can
import a package's Less sources directly into its own stylesheet without
any framework-level wiring:

```less
// src/MyProject/Assets/less/app.less

@import "../../../../vendor/vendor-name/hello-package/src/Assets/less/variables.less";

.my-custom-class {
    color: @hello-primary-color;
}
```

`AssetManager` compiles the project's own `app.less` as usual — it never
needs to know a package exists. The only caveat is that the relative path
to `vendor/` depends on the importing file's exact depth in the project.

There is currently no equivalent mechanism for JavaScript (no import
resolution across `vendor/` at compile time) — a package's JS should be
loaded separately via `PackageAssetController`, alongside the project's own
compiled `app.js`, rather than merged into it.

## Local Development

While developing a package locally, register it as a Composer `path` repository
so changes are picked up without republishing:

**Root `composer.json`:**
```json
{
    "repositories": [
        { "type": "path", "url": "packages/hello-package" }
    ]
}
```

**Project's `composer.json` (e.g. `src/MyProject/composer.json`):**
```json
{
    "require": {
        "vendor-name/hello-package": "@dev"
    }
}
```

Then:
```bash
composer update
```

No further sync step is required — the package is read directly from
`packages/hello-package/` on every request.

---

## Full Example: HelloPackage

This is a complete, minimal package demonstrating every discovery domain:
a controller, a view, default configuration, an entity with its repository,
a migration, and a cron job.

```
hello-package/
├── composer.json
├── README.md
├── src/
│   ├── HelloPackage.php
│   ├── Controllers/
│   │   └── HelloController.php
│   ├── Templates/
│   │   └── hello.html.twig
│   └── Crons/
│       └── GreetingCleanupCron.php
├── config/
│   └── hello.config.php
└── database/
    ├── Entity/
    │   └── Greeting.php
    ├── Repository/
    │   └── GreetingRepository.php
    └── Migrations/
        └── MigrationVersion_Hello_20260101_000000_CreateGreetingsTable.php
```

### `composer.json`

```json
{
    "name": "vendor-name/hello-package",
    "description": "Example NeoPHP package",
    "type": "library",
    "require": {
        "php": ">=8.5"
    },
    "autoload": {
        "psr-4": {
            "Vendor\\HelloPackage\\": "src/",
            "Vendor\\HelloPackage\\Database\\": "database/"
        }
    }
}
```

### `src/HelloPackage.php`

```php
<?php

declare(strict_types=1);

namespace Vendor\HelloPackage;

use Neo\Core\Package\Abstract\AbstractPackage;

final class HelloPackage extends AbstractPackage
{
    public function getName(): string
    {
        return 'Hello';
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
```

### `src/Controllers/HelloController.php`

```php
<?php

declare(strict_types=1);

namespace Vendor\HelloPackage\Controllers;

use Neo\Core\Controller\AbstractController;
use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\Http\Request\Request;
use Neo\Core\Http\Response\Types\Response;
use Neo\Core\Routing\Attribute\MainRoute;
use Neo\Core\Routing\Attribute\Route;
use Vendor\HelloPackage\Database\Entity\Greeting;
use Vendor\HelloPackage\Database\Repository\GreetingRepository;

#[MainRoute(path: '/hello', name: 'hello')]
final class HelloController extends AbstractController
{
    #[Route(path: '/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var EntityManager $em */
        $em = $this->container->get(EntityManager::class);

        /** @var GreetingRepository $repository */
        $repository = $em->getRepository(Greeting::class);

        return $this->render('@Hello/hello.html.twig', [
            'message' => $this->getConfig()->from('hello')->get('greeting', 'Hello, world!'),
            'greetings' => $repository->findAllOrdered(),
        ]);
    }

    #[Route(path: '/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        /** @var EntityManager $em */
        $em = $this->container->get(EntityManager::class);

        /** @var GreetingRepository $repository */
        $repository = $em->getRepository(Greeting::class);

        $repository->create($request->getPost('message', 'Hello!'));

        return $this->redirect('/hello');
    }
}
```

### `src/Templates/hello.html.twig`

```twig
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hello Package</title>
</head>
<body>
    <h1>{{ message }}</h1>

    <ul>
        {% for greeting in greetings %}
            <li>{{ greeting.message }} — {{ greeting.createdAt|date('Y-m-d H:i:s') }}</li>
        {% endfor %}
    </ul>

    <form method="post" action="/hello/add">
        <input type="text" name="message" placeholder="New greeting" required>
        <button type="submit">Add</button>
    </form>
</body>
</html>
```

### `src/Crons/GreetingCleanupCron.php`

```php
<?php

declare(strict_types=1);

namespace Vendor\HelloPackage\Crons;

use Neo\Core\Cron\Attribute\Cron;
use Vendor\HelloPackage\Database\Repository\GreetingRepository;

final class GreetingCleanupCron
{
    public function __construct(private readonly GreetingRepository $repository) {}

    #[Cron(
        expression: '0 0 * * *',
        description: 'Removes greetings older than 30 days',
    )]
    public function handle(): void
    {
        $this->repository->deleteOlderThan(30);
    }
}
```

### `config/hello.config.php`

```php
<?php

declare(strict_types=1);

return [
    'greeting' => 'Hello from HelloPackage!',
];
```

### `database/Entity/Greeting.php`

```php
<?php

declare(strict_types=1);

namespace Vendor\HelloPackage\Database\Entity;

use Neo\Core\Database\ORM\Mapping\Attribute\Column;
use Neo\Core\Database\ORM\Mapping\Attribute\Entity;
use Neo\Core\Database\ORM\Mapping\Attribute\GeneratedValue;
use Neo\Core\Database\ORM\Mapping\Attribute\Id;
use Neo\Core\Database\ORM\Mapping\Attribute\Table;
use Vendor\HelloPackage\Database\Repository\GreetingRepository;

#[Entity(repositoryClass: GreetingRepository::class)]
#[Table(name: 'greetings')]
final class Greeting
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer', unsigned: true)]
    private ?int $id = null;

    #[Column(type: 'string', length: 255)]
    private string $message;

    #[Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(string $message)
    {
        $this->message = $message;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
```

### `database/Repository/GreetingRepository.php`

```php
<?php

declare(strict_types=1);

namespace Vendor\HelloPackage\Database\Repository;

use Neo\Core\Database\ORM\Persistence\EntityRepository;
use Vendor\HelloPackage\Database\Entity\Greeting;

/**
 * @extends EntityRepository<Greeting>
 */
final class GreetingRepository extends EntityRepository
{
    /**
     * @return list<Greeting>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], orderBy: ['createdAt' => 'DESC']);
    }

    public function create(string $message): Greeting
    {
        $greeting = new Greeting($message);

        $this->getEntityManager()->persist($greeting);
        $this->getEntityManager()->flush();

        return $greeting;
    }

    public function deleteOlderThan(int $days): void
    {
        $threshold = new \DateTime("-{$days} days");

        foreach ($this->findAll() as $greeting) {
            if ($greeting->getCreatedAt() < $threshold) {
                $this->getEntityManager()->remove($greeting);
            }
        }

        $this->getEntityManager()->flush();
    }
}
```

### `database/Migrations/MigrationVersion_Hello_20260101_000000_CreateGreetingsTable.php`

```php
<?php

declare(strict_types=1);

use Neo\Core\Database\DatabaseManager;
use Neo\Core\Database\Migration\Interface\MigrationInterface;

final class MigrationVersion_Hello_20260101_000000_CreateGreetingsTable implements MigrationInterface
{
    public function up(DatabaseManager $db): void
    {
        $db->execute("
            CREATE TABLE IF NOT EXISTS `greetings` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `message`    VARCHAR(255) NOT NULL,
                `created_at` DATETIME     NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(DatabaseManager $db): void
    {
        $db->execute("DROP TABLE IF EXISTS `greetings`");
    }
}
```

### Enabling it in a project

```php
// src/MyProject/Config/app.config.php
return [
    // ...
    'packages' => [
        \Vendor\HelloPackage\HelloPackage::class,
    ],
];
```

### Testing it end to end

```bash
php bin/neo debug:router --project=MyProject
php bin/neo database:migration:migrate --project=MyProject
php bin/neo cron:list --project=MyProject
php bin/neo app:serve MyProject
# → http://localhost:800X/hello/
```