# Application

The `Application` module is the entry point for every NeoPHP project. It is responsible for detecting the active application (based on the HTTP or CLI context), registering standard paths in the dependency container, and managing the project lifecycle through dedicated commands.

---

## Summary

- [ApplicationDetector](#applicationdetector)
- [ApplicationPaths](#applicationpaths)
- [Commands](#commands)
  - [project:create](#projectcreate)
  - [project:delete](#projectdelete)
  - [project:sync](#projectsync)

---

## ApplicationDetector

**File:** `ApplicationDetector.php`

This class determines which NeoPHP project should be loaded for the current request. It adapts its logic depending on whether the application is running in HTTP or CLI mode.

### HTTP Detection

In HTTP mode, `ApplicationDetector` reads the hostname from `$_SERVER['HTTP_HOST']` (or `SERVER_NAME`), then compares it against the `access` values defined in each project's `app.config.php` file. A cache file (`/storage/app-detect-cache.json`) is maintained to avoid re-reading all config files on every request. This cache is automatically invalidated as soon as a config file is modified (MD5 signature comparison).

```php
// Example: src/MyProject/Config/app.config.php
return [
    'access' => 'myproject.localhost:8001',
    // ...
];
```

When a request arrives on `myproject.localhost:8001`, the detector automatically resolves the `MyProject` project and registers it in the container under the `'application'` key.

### CLI Detection

In CLI mode, detection follows this priority order:

1. Global variable `$GLOBALS['_NEO_TEST_PROJECT']` (used in automated tests).
2. Global variable `$GLOBALS['_NEO_CLI_PROJECT']` (set by `ConsoleManager` when a project is selected interactively).
3. `--project=<ProjectName>` argument passed on the command line.

```bash
php bin/neo make:controller MyController --project=MyProject
# equivalent to --project=MyProject in $argv
```

If no project can be resolved, an `ApplicationException` is thrown with an explicit message.

### Main method

```php
$detector->detect(); // Triggers detection based on context (HTTP or CLI)
```

---

## ApplicationPaths

**File:** `ApplicationPaths.php`

Once the project has been resolved, `ApplicationPaths` registers all of the project's standard paths in the dependency container. These paths are used by every other module of the framework (assets, views, ORM, crons, etc.).

### Usage

```php
$appPaths = new ApplicationPaths($container);
$appPaths->register(); // Uses the project already registered in the container
// or
$appPaths->register('MyProject'); // Forces a specific project
```

### Paths registered in the container

| Container key        | Resolved value                                    |
|-----------------------|-----------------------------------------------------|
| `application`         | Project name (e.g. `MyProject`)                     |
| `basePath`             | Monorepo root                                        |
| `appPath`              | `{basePath}/src/MyProject`                           |
| `publicPath`           | `{basePath}/public_html` or `{basePath}/public`      |
| `buildsPath`           | `{publicPath}/builds/`                                |
| `srcPath`              | `{basePath}/src`                                      |
| `storagePath`          | `{appPath}/Storage`                                   |
| `configsPath`          | `{appPath}/Config`                                    |
| `viewsPath`            | `{appPath}/Templates`                                 |
| `controllersPath`      | `{appPath}/App/Controllers`                           |
| `assetsPath`           | `{appPath}/Assets/`                                   |
| `repositoryPath`       | `{appPath}/Database/Repository`                       |
| `listenersPath`        | `{appPath}/App/Event/Listener`                        |
| `cronsPath`            | `{appPath}/App/Crons`                                 |
| `controllerNamespace`  | `Neo\Src\MyProject\App\Controllers\`                  |
| `repositoryNamespace`  | `Neo\Src\MyProject\Database\Repository`               |
| `manifestFilename`     | `manifest.json`                                       |

### Public folder resolution

The `resolvePublicPath()` method looks for, in order:
1. `public_html/` (shared hosting)
2. `public/` (standard)
3. Falls back to `{basePath}/public` if neither exists

---

## Commands

### `project:create`

**File:** `Command/ProjectCreateCommand.php`

Creates a complete new NeoPHP project inside the `./src/` folder. This command is the framework's main scaffolding tool.

#### Synopsis

```bash
php bin/neo project:create <projectName> [--skeleton]
```

#### Arguments and options

| Name           | Type      | Description                                                  |
|-----------------|-----------|------------------------------------------------------------------|
| `projectName`  | Argument  | Project name (automatically converted to PascalCase)             |
| `--skeleton`   | Option    | Creates only the folder structure, without the example files     |

#### What the command generates

**Folder structure (always created):**
```
src/MyProject/
├── App/
│   ├── Controllers/
│   ├── Middlewares/
│   └── Services/
├── Assets/
├── Config/
├── Database/
│   ├── Migrations/
│   ├── Entity/
│   └── Repository/
├── Storage/
├── Templates/
├── Translations/
├── MyProjectModule.php
├── composer.json
└── .gitignore
```

**Configuration files automatically generated:**
- `app.config.php` — main configuration (HTTP access with automatically assigned port)
- `database.config.php` — database connection
- `logger.config.php` — logging
- `cache.config.php` — cache management
- `twig.config.php` — template engine
- `session.config.php` — sessions
- `api.config.php` — API configuration
- `auth.config.php` — authentication

**Without `--skeleton`: additional example files:**
- `App/Controllers/DefaultController.php` — welcome controller
- `Templates/layouts/base_layout.html.twig` — base Twig layout
- `Templates/pages/default/index.html.twig` — home page
- `Assets/css/app.css` — base styles
- `Assets/js/app.js` — base JS
- `Translations/fr/` and `Translations/en/` — initial translations

**Automatic Composer management:**

The command automatically allocates an available port (starting from 8000) by scanning existing projects, registers the project in the root `composer.json` as a `path` dependency, then automatically runs `composer update`.

```bash
php bin/neo project:create MonSite
# → Creates src/MonSite/ with all files
# → Port 8001 assigned if 8000 is already in use
# → Root composer.json updated
# → composer update executed
```

```bash
php bin/neo project:create MonSite --skeleton
# → Creates only the folder structure and the config files
```

#### Generated module

Each project receives a main module (`{Name}Module.php`) implementing `ModuleInterface`:

```php
final class MyProjectModule implements ModuleInterface
{
    public function dependencies(): array { return []; }
    public function register(Container $container): void {}
    public function init(Container $container): object { return $this; }
}
```

---

### `project:delete`

**File:** `Command/ProjectDeleteCommand.php`

Completely removes a NeoPHP project: its sources, its builds, and its registration in `composer.json`.

#### Synopsis

```bash
php bin/neo project:delete <projectName>
```

#### Behavior

1. Asks for interactive confirmation before any deletion (irreversible action).
2. Removes the `public/builds/{Project}` folder if it exists.
3. Removes the project from the root `composer.json` (`repositories` and `require` entries).
4. Removes the `src/{Project}` folder.
5. Runs `composer update --optimize-autoloader` to clean up the autoloader.

```bash
php bin/neo project:delete MonSite
# Displays: "You are about to delete project 'MonSite'. This action is irreversible."
# Asks for confirmation, then deletes everything
```

---

### `project:sync`

**File:** `Command/ProjectSyncCommand.php`

Synchronizes the root `composer.json` with every project present in `./src/`. Useful after cloning the repository or manually adding a project.

#### Synopsis

```bash
php bin/neo project:sync [--run-composer]
```

#### Options

| Name               | Description                                             |
|----------------------|-------------------------------------------------------------|
| `--run-composer`   | Automatically runs `composer update` after the sync         |

#### Behavior

The command scans every subfolder of `./src/` that has a `composer.json`, and for each one checks whether the project is already referenced in the root `composer.json`. If not, it is added automatically.

```bash
php bin/neo project:sync
# → 2 added, 1 already present

php bin/neo project:sync --run-composer
# → Sync + composer update
```

---

## File structure

```
neo/Core/Application/
├── ApplicationDetector.php         # HTTP and CLI detection of the active project
├── ApplicationPaths.php            # Registration of paths in the container
├── Exception/
│   └── ApplicationException.php
└── Commands/
    ├── ProjectCreateCommand.php    # project:create
    ├── ProjectDeleteCommand.php    # project:delete
    └── ProjectSyncCommand.php      # project:sync
```