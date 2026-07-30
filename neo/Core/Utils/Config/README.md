# Config — NeoPHP

Le sous-module `Config` charge, expose et permet de modifier les fichiers de configuration PHP d'un projet.

---

## Sommaire

1. [Structure](#structure)
2. [ConfigManager](#configmanager)
3. [Accès aux valeurs](#accès-aux-valeurs)
4. [Surcharge pour les tests](#surcharge-pour-les-tests)
5. [ConfigTemplateWriter](#configtemplatewriter)
6. [Extension contrôleur](#extension-contrôleur)
7. [Commandes CLI](#commandes-cli)

---

## Structure

```
Config/
├── ConfigManager.php                   # Chargement et accès aux configs
├── ConfigModule.php                    # Enregistrement DI
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
│   └── ConfigTemplateWriter.php        # Écriture de fichiers config depuis templates
├── Commands/
│   ├── GenerateDefaultConfigCommand.php
│   └── MakeConfigCommand.php
├── Exception/
│   └── ConfigException.php
└── Extension/
    └── ConfigControllerExtension.php   # Injecte getConfig() dans les contrôleurs
```

---

## ConfigManager

**Fichier :** `ConfigManager.php`

Charge tous les fichiers `*.config.php` du répertoire `configsPath` du projet. L'accès se fait via un patron fluide `from(key)->get(path)`.

**Fonctionnement au démarrage :**

1. Charge tous les fichiers `*.config.php` du répertoire `configsPath`.
2. Si un répertoire de test est configuré (`testConfigsPath`), charge les fichiers `*.config.test.php` et les fusionne profondément (`deepMerge`) sur les configs existantes.

---

## Accès aux valeurs

```php
$config = $container->get(ConfigManager::class);

// Lire une valeur simple
$dbName = $config->from('database')->get('connections.default.dbname');

// Valeur par défaut si absente
$debug = $config->from('app')->get('debug', false);

// Lire toute la configuration d'un fichier
$allDbConfig = $config->from('database')->all();

// Modifier une valeur à l'exécution
$config->from('app')->set('locale', 'fr');
```

**Accès imbriqué par point :**

```php
// app.config.php retourne ['features' => ['registration' => true, 'api' => false]]
$config->from('app')->get('features.registration'); // true
$config->from('app')->get('features.unknown', 'default'); // 'default'
```

**Structure d'un fichier de config :**

```php
// src/MyProject/Config/app.config.php
return [
    'name'   => 'Mon Application',
    'debug'  => false,
    'locale' => 'fr',
    'features' => [
        'registration' => true,
        'api'          => false,
    ],
];
```

---

## Surcharge pour les tests

```php
// src/MyProject/Config/app.config.test.php
// Valeurs qui écrasent celles de app.config.php dans les tests
return [
    'debug'  => true,
    'features' => [
        'api' => true,
    ],
];
```

La fusion est profonde (`deepMerge`) : seules les clés déclarées dans le fichier `.test.php` sont écrasées.

---

## ConfigTemplateWriter

**Fichier :** `Writer/ConfigTemplateWriter.php`

Utilisé par les commandes de scaffolding pour écrire des fichiers de configuration depuis des templates.

```php
ConfigTemplateWriter::write(
    templates: [new DatabaseConfigTemplate()],
    configPath: '/path/to/Config/',
    projectName: 'Blog',
    context: ['dbname' => 'blog_db'],
    askOverwrite: true,
);
```

Chaque template implémente `ConfigTemplateInterface` avec les méthodes `filename()` et `render(string $project, array $context): string`.

---

## Extension contrôleur

**Fichier :** `Extension/ConfigControllerExtension.php`

Injecte automatiquement `getConfig()` dans tous les contrôleurs.

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

## Commandes CLI

| Commande | Description |
|----------|-------------|
| `config:generate` | Génère les fichiers de configuration par défaut pour un projet |
| `make:config` | Crée un nouveau fichier de configuration personnalisé |
