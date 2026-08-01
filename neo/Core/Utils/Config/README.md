# Config

The `Config` submodule loads, exposes, and allows modification of a project's PHP configuration files.

---

## Table of Contents

1. [Structure](#structure)
2. [ConfigManager](#configmanager)
3. [Accessing Values](#accessing-values)
4. [Test Override](#test-override)
5. [ConfigTemplateWriter](#configtemplatewriter)
6. [Controller Extension](#controller-extension)
7. [CLI Commands](#cli-commands)

---

## Structure

```
Config/
├── ConfigManager.php                   # Loading and access to configs
├── ConfigModule.php                    # DI registration
├── Templates/
│   ├── Interface/
│   │   └── ConfigTemplateInterface.php
│   ├── ApiConfigTemplate.php
│   ├── AppConfigTemplate.php
│   ├── AuthConfigTemplate.php
│   ├── CacheConfigTemplate.php
│   ├── DatabaseConfigTemplate.php
│   ├── LoggerConfigTemplate.php
│   ├── SessionConfigTemplate.php
│   └── TwigConfigTemplate.php
├── Writer/
│   └── ConfigTemplateWriter.php        # Writing config files from templates
├── Commands/
│   ├── GenerateDefaultConfigCommand.php
│   └── MakeConfigCommand.php
├── Exception/
│   └── ConfigException.php
└── Extension/
    └── ConfigControllerExtension.php   # Injects getConfig() into controllers
```

---

## ConfigManager

**File:** `ConfigManager.php`

Loads all `*.config.php` files from the project's `configsPath` directory. Access is done via a fluent `from(key)->get(path)` pattern.

**Startup Behavior:**

1. Loads all `*.config.php` files from the `configsPath` directory.
2. If a test directory is configured (`testConfigsPath`), loads the `*.config.test.php` files and deeply merges them (`deepMerge`) onto the existing configs.

---

## Accessing Values

```php
$config = $container->get(ConfigManager::class);

// Read a simple value
$dbName = $config->from('database')->get('connections.default.dbname');

// Default value if missing
$debug = $config->from('app')->get('debug', false);

// Read a whole file's configuration
$allDbConfig = $config->from('database')->all();

// Modify a value at runtime
$config->from('app')->set('locale', 'fr');
```

**Dot-Notation Nested Access:**

```php
// app.config.php returns ['features' => ['registration' => true, 'api' => false]]
$config->from('app')->get('features.registration'); // true
$config->from('app')->get('features.unknown', 'default'); // 'default'
```

**Structure of a Config File:**

```php
// src/MyProject/Config/app.config.php
return [
    'name'   => 'My Application',
    'debug'  => false,
    'locale' => 'fr',
    'features' => [
        'registration' => true,
        'api'          => false,
    ],
];
```

---

## Test Override

```php
// src/MyProject/Config/app.config.test.php
// Values that override those in app.config.php during tests
return [
    'debug'  => true,
    'features' => [
        'api' => true,
    ],
];
```

The merge is deep (`deepMerge`): only the keys declared in the `.test.php` file are overridden.

---

## ConfigTemplateWriter

**File:** `Writer/ConfigTemplateWriter.php`

Used by scaffolding commands to write configuration files from templates.

```php
ConfigTemplateWriter::write(
    templates: [new DatabaseConfigTemplate()],
    configPath: '/path/to/Config/',
    projectName: 'Blog',
    context: ['dbname' => 'blog_db'],
    askOverwrite: true,
);
```

Each template implements `ConfigTemplateInterface` with the `filename()` and `render(string $project, array $context): string` methods.

---

## Controller Extension

**File:** `Extension/ConfigControllerExtension.php`

Automatically injects `getConfig()` into all controllers.

```php
class AppController extends AbstractController
{
    public function settings(): Response
    {
        $appName  = $this->getConfig()->from('app')->get('name');
        $features = $this->getConfig()->from('app')->get('features');

        return $this->render('settings.html.twig', [
            'appName'  => $appName,
            'features' => $features,
        ]);
    }
}
```

---

## CLI Commands

| Command | Description |
|----------|-------------|
| `config:generate` | Generates the default configuration files for a project |
| `make:config` | Creates a new custom configuration file |