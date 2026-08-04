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
10. [Local Development](#local-development)
11. [Full Example](#full-example)

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

## Full Example

See `packages/hello-package/` for a complete, working example package
demonstrating a controller, a view, default configuration, and a migration.