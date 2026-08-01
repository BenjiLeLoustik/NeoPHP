# Translation

The Translation module provides a complete internationalization (i18n) system for NeoPHP applications. It handles automatic locale resolution, loading of PHP translation files by domain, automatic writing of missing keys in development mode, as well as native integration with Twig and the Profiler.

---

## Summary

1. [Module Structure](#module-structure)
2. [Configuration](#configuration)
3. [Translation Files](#translation-files)
4. [TranslationManager](#translationmanager)
5. [LocaleManager](#localemanager)
6. [TranslationDomain](#translationdomain)
7. [TranslationRegistry](#translationregistry)
8. [TranslationLoader and TranslationWriter](#translationloader-and-translationwriter)
9. [Twig Integration](#twig-integration)
10. [Global Helper translate()](#global-helper-translate)
11. [TranslationCollector (Profiler)](#translationcollector-profiler)
12. [translation:sync Command](#translationsync-command)

---

## Module Structure

```
Translation/
├── TranslationManager.php              # Main manager (implements TranslatorInterface)
├── TranslationModule.php               # Registration in the DI container
├── Domain/
│   └── TranslationDomain.php           # Normalization and resolution of domains
├── Loader/
│   └── TranslationLoader.php           # Loading of translation files with cache
├── Registry/
│   └── TranslationRegistry.php         # Static registry of translation paths
├── Locale/
│   └── LocaleManager.php               # Resolution of the active locale
├── Writer/
│   └── TranslationWriter.php           # Automatic writing of missing keys
├── Collector/
│   └── TranslationCollector.php        # Collector for the Profiler
├── Extension/
│   └── TranslationViewExtension.php    # Twig functions and filters
├── Helper/
│   └── Translate.php                   # Global translate() function
├── Interface/
│   ├── TranslatorInterface.php
│   └── TranslationCollectorInterface.php
└── Commands/
    └── TranslationSyncCommand.php      # translation:sync
```

---

## Configuration

Configuration is found in `src/MyProject/Config/app.config.php`:

```php
return [
    'translation' => [
        'enabled'          => true,
        'default_locale'   => 'fr',
        'available_locales' => [
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español',
        ],
    ],
];
```

| Key | Type | Description |
|---|---|---|
| `enabled` | `bool` | Enables or disables the translation system |
| `default_locale` | `string` | Locale used by default |
| `available_locales` | `array` | Map `code => label` of available locales |

When `enabled` is `false`, the `translate()` method directly returns the original text with the replacements applied.

---

## Translation Files

Translations are PHP files that return an associative array `key => translation`.

**Folder structure:**

```
src/MyProject/Translations/
├── fr/
│   ├── common.php      # Default domain
│   ├── auth.php
│   └── emails.php
└── en/
    ├── common.php
    ├── auth.php
    └── emails.php
```

**Example `fr/common.php` file:**

```php
<?php

declare(strict_types=1);

return [
    'Bienvenue'                 => 'Bienvenue',
    'Bonjour :name'             => 'Bonjour :name',
    'Page non trouvée'          => 'Page non trouvée',
    'Une erreur est survenue'   => 'Une erreur est survenue',
];
```

**Example `en/common.php` file:**

```php
<?php

declare(strict_types=1);

return [
    'Bienvenue'                 => 'Welcome',
    'Bonjour :name'             => 'Hello :name',
    'Page non trouvée'          => 'Page not found',
    'Une erreur est survenue'   => 'An error occurred',
];
```

The path is resolved using the formula: `{basePath}/{locale}/{domain}.php`

---

## TranslationManager

`Neo\Core\Translation\TranslationManager` is the main entry point. It implements `TranslatorInterface`.

### Translating a Text

```php
use Neo\Core\Translation\TranslationManager;

$translator = $container->get(TranslationManager::class);

// Simple translation
echo $translator->translate('Bienvenue');

// Translation with replacements (:param)
echo $translator->translate('Bonjour :name', ['name' => 'Alice']);

// Translation in a specific domain
echo $translator->translate('Connexion', [], 'auth');
```

Replacements work with the `:parameterName` syntax in the translated value.

### Changing the Locale

```php
// Changes the active locale and stores a cookie (default duration: 1 year)
$translator->setLocale('en');

// Custom duration in seconds
$translator->setLocale('es', lifetime: 7200);
```

If `available_locales` is defined in the configuration and the requested locale is not part of it, a `TranslationException` (code 400) is thrown.

### Other Methods

```php
$translator->getLocale();              // Returns the active locale, e.g. 'fr'
$translator->getLocales();             // Returns the available_locales array
$translator->isEnabledTranslation();   // Returns true if the system is active
```

---

## LocaleManager

`Neo\Core\Translation\Locale\LocaleManager` is responsible for the **automatic resolution** of the locale at startup. It follows this priority order:

1. `lang` cookie present and valid → locale from the cookie
2. Configured default locale (`default_locale`) if it is within `available_locales`
3. Fallback to `'fr'`

```php
$locale = LocaleManager::resolve($container);
// Returns e.g. 'en' if the lang=en cookie is present
```

---

## TranslationDomain

`Neo\Core\Translation\Domain\TranslationDomain` handles the normalization of domain names.

```php
use Neo\Core\Translation\Domain\TranslationDomain;

// Normalizes null or '' to 'common'
TranslationDomain::normalize(null);   // 'common'
TranslationDomain::normalize('');     // 'common'
TranslationDomain::normalize('auth'); // 'auth'

// Resolves the full path to a translation file
TranslationDomain::resolveFilePath('/app/Translations', 'fr', 'auth');
// Result: '/app/Translations/fr/auth.php'
```

The `TranslationDomain::DEFAULT` constant equals `'common'`.

---

## TranslationRegistry

`Neo\Core\Translation\Registry\TranslationRegistry` maintains the static list of translation folders to consult. Multiple paths can be registered; the `TranslationLoader` iterates over all of them and merges the results (`array_replace`).

```php
use Neo\Core\Translation\Registry\TranslationRegistry;

// Register a translations folder
TranslationRegistry::registerPath('/app/src/Blog/Translations');
TranslationRegistry::registerPath('/app/src/Shared/Translations');

// Retrieve all registered paths
$paths = TranslationRegistry::getPaths();
```

---

## TranslationLoader and TranslationWriter

### TranslationLoader

`Neo\Core\Translation\Loader\TranslationLoader` loads translation files with an **in-memory cache** (keyed by `domain:locale`). In development mode, it invalidates OPcache and PHP's stat cache on every load to guarantee file freshness.

```php
$loader = new TranslationLoader();

// Loads fr/common.php from all registered paths
$translations = $loader->load('fr');

// Loads fr/auth.php
$translations = $loader->load('fr', 'auth');

// Invalidates the cache to force a reload
$loader->invalidate('fr', 'auth');
```

### TranslationWriter

`Neo\Core\Translation\Writer\TranslationWriter` is used in development mode (`environment === 'dev'`) to **automatically add missing keys** to the corresponding translation file. This avoids having to manually create every key.

When a key is not found, it is added with its own value as the default translation:

```php
// In fr/common.php, if 'Nouveau message' is missing, it becomes:
// 'Nouveau message' => 'Nouveau message'
```

---

## Twig Integration

`Neo\Core\Translation\Extension\TranslationViewExtension` automatically exposes Twig functions and filters as soon as the Translation module is active.

### Functions Available in Templates

```twig
{# Simple translation #}
{{ translate('Bienvenue') }}

{# Short alias #}
{{ trans('Bienvenue') }}

{# With replacements #}
{{ translate('Bonjour :name', {name: user.name}) }}

{# In a specific domain #}
{{ translate('Connexion', {}, 'auth') }}

{# Active locale #}
{{ getLocale() }}

{# All available locales #}
{% for code, label in getLocales() %}
    <a href="/lang/{{ code }}">{{ label }}</a>
{% endfor %}

{# Check whether translation is enabled #}
{% if isEnabledTranslation() %}
    {# ... #}
{% endif %}
```

### Available Filter

```twig
{# Twig filter equivalent to trans() #}
{{ 'Bienvenue'|trans }}
{{ 'Bonjour :name'|trans({name: user.name}) }}
{{ 'Connexion'|trans({}, 'auth') }}
```

---

## Global Helper translate()

The `Helper/Translate.php` file defines the global `translate()` function, available anywhere in the PHP code without dependency injection.

```php
// In a controller, a service, or any class
$message = translate('Bienvenue');
$message = translate('Bonjour :name', ['name' => 'Alice']);
$message = translate('Connexion', [], 'auth');
```

The function automatically resolves the `TranslationManager` from the `ContainerRegistry`.

---

## TranslationCollector (Profiler)

`Neo\Core\Translation\Collector\TranslationCollector` integrates with the NeoPHP Profiler to display translation information in real time during the request:

- Active locale
- Number of resolved keys (hits)
- Number of missing keys (misses)
- Detailed table of hits and misses by domain

The Profiler tab is displayed in green if all keys are resolved, in yellow if some keys are missing, in gray if translation is disabled.

---

## translation:sync Command

`translation:sync` scans the project's source files (`src/MyProject/`) looking for every call to `translate()`, `trans()`, the Twig `|trans` filter, and `// @translatable` comments, then synchronizes the translation files.

```bash
# See what would be added/removed without writing
php neo translation:sync --project=Blog --dry-run

# Synchronize (adds missing keys)
php neo translation:sync --project=Blog

# Synchronize and remove obsolete keys
php neo translation:sync --project=Blog --prune
```

**Options:**

| Option | Description |
|---|---|
| `--project` | Target project (folder in `src/`) |
| `--dry-run` | Displays the differences without writing |
| `--prune` | Removes keys that are no longer used in the code (destructive) |

**Automatically Recognized Patterns:**

```php
// Direct PHP calls
translate('My key');
translate('My key', ['param' => 'value'], 'domain');
trans('My key');

// In Twig templates
{{ translate('My key') }}
{{ 'My key'|trans }}
{{ 'My key'|trans({}, 'auth') }}

// Annotation in config files
'default_role' => 'admin', // @translatable
'default_role' => 'admin', // @translatable:auth
```

Locales are automatically discovered from `app.config.php` (`available_locales`) or from existing folders in the `Translations/` directory.

Each new key is added with itself as the default value (`'My key' => 'My key'`), making later translation easier.