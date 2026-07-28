# Module Application

Le module `Application` est le point d'entrée de tout projet NeoPHP. Il est responsable de la détection de l'application active (selon le contexte HTTP ou CLI), de l'enregistrement des chemins standards dans le conteneur de dépendances, et de la gestion du cycle de vie des projets via des commandes dédiées.

---

## Sommaire

- [ApplicationDetector](#applicationdetector)
- [ApplicationPaths](#applicationpaths)
- [Commandes](#commandes)
  - [project:create](#projectcreate)
  - [project:delete](#projectdelete)
  - [project:sync](#projectsync)

---

## ApplicationDetector

**Fichier :** `ApplicationDetector.php`

Cette classe détermine quel projet NeoPHP doit être chargé pour la requête en cours. Elle adapte sa logique selon que l'application s'exécute en mode HTTP ou CLI.

### Détection HTTP

En mode HTTP, `ApplicationDetector` lit le nom d'hôte depuis `$_SERVER['HTTP_HOST']` (ou `SERVER_NAME`), puis le compare aux valeurs `access` définies dans les fichiers `app.config.php` de chaque projet. Un fichier de cache (`/storage/app-detect-cache.json`) est maintenu afin d'éviter de relire tous les fichiers de config à chaque requête. Ce cache est invalidé automatiquement dès qu'un fichier de configuration est modifié (comparaison par signature MD5).

```php
// Exemple : src/MyProject/Config/app.config.php
return [
    'access' => 'myproject.localhost:8001',
    // ...
];
```

Lorsqu'une requête arrive sur `myproject.localhost:8001`, le détecteur résout automatiquement le projet `MyProject` et l'enregistre dans le conteneur sous la clé `'application'`.

### Détection CLI

En mode CLI, la détection suit l'ordre de priorité suivant :

1. Variable globale `$GLOBALS['_NEO_TEST_PROJECT']` (utilisée dans les tests automatisés).
2. Variable globale `$GLOBALS['_NEO_CLI_PROJECT']` (positionnée par le `ConsoleManager` lorsqu'un projet est sélectionné interactivement).
3. Argument `--project=<NomDuProjet>` passé dans la ligne de commande.

```bash
php bin/neo make:controller MonController --project=MyProject
# équivalent à --project=MyProject dans $argv
```

Si aucun projet ne peut être résolu, une `ApplicationException` est levée avec un message explicite.

### Méthode principale

```php
$detector->detect(); // Lance la détection selon le contexte (HTTP ou CLI)
```

---

## ApplicationPaths

**Fichier :** `ApplicationPaths.php`

Une fois le projet résolu, `ApplicationPaths` enregistre l'ensemble des chemins standards du projet dans le conteneur de dépendances. Ces chemins sont utilisés par tous les autres modules du framework (assets, vues, ORM, crons, etc.).

### Utilisation

```php
$appPaths = new ApplicationPaths($container);
$appPaths->register(); // Utilise le projet déjà enregistré dans le conteneur
// ou
$appPaths->register('MyProject'); // Force un projet spécifique
```

### Chemins enregistrés dans le conteneur

| Clé du conteneur        | Valeur résolue                                      |
|-------------------------|-----------------------------------------------------|
| `application`           | Nom du projet (ex. `MyProject`)                     |
| `basePath`              | Racine du monorepo                                  |
| `appPath`               | `{basePath}/src/MyProject`                          |
| `publicPath`            | `{basePath}/public_html` ou `{basePath}/public`     |
| `buildsPath`            | `{publicPath}/builds/`                              |
| `srcPath`               | `{basePath}/src`                                    |
| `storagePath`           | `{appPath}/Storage`                                 |
| `configsPath`           | `{appPath}/Config`                                  |
| `viewsPath`             | `{appPath}/Templates`                               |
| `controllersPath`       | `{appPath}/App/Controllers`                         |
| `assetsPath`            | `{appPath}/Assets/`                                 |
| `repositoryPath`        | `{appPath}/Database/Repository`                     |
| `listenersPath`         | `{appPath}/App/Event/Listener`                      |
| `cronsPath`             | `{appPath}/App/Crons`                               |
| `controllerNamespace`   | `Neo\Src\MyProject\App\Controllers\`                |
| `repositoryNamespace`   | `Neo\Src\MyProject\Database\Repository`             |
| `manifestFilename`      | `manifest.json`                                     |

### Résolution du dossier public

La méthode `resolvePublicPath()` cherche dans l'ordre :
1. `public_html/` (hébergements mutualisés)
2. `public/` (standard)
3. Retourne `{basePath}/public` si aucun des deux n'existe

---

## Commandes

### `project:create`

**Fichier :** `Commands/ProjectCreateCommand.php`

Crée un nouveau projet NeoPHP complet à l'intérieur du dossier `./src/`. Cette commande est l'outil de scaffolding principal du framework.

#### Synopsis

```bash
php bin/neo project:create <projectName> [--skeleton]
```

#### Arguments et options

| Nom          | Type      | Description                                              |
|--------------|-----------|----------------------------------------------------------|
| `projectName`| Argument  | Nom du projet (converti automatiquement en PascalCase)   |
| `--skeleton` | Option    | Crée uniquement la structure de dossiers, sans les fichiers d'exemple |

#### Ce que la commande génère

**Structure de dossiers (toujours créés) :**
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

**Fichiers de configuration générés automatiquement :**
- `app.config.php` — configuration principale (accès HTTP avec port automatiquement assigné)
- `database.config.php` — connexion base de données
- `logger.config.php` — journalisation
- `cache.config.php` — gestion du cache
- `twig.config.php` — moteur de template
- `session.config.php` — sessions
- `api.config.php` — configuration API
- `auth.config.php` — authentification

**Sans `--skeleton` : fichiers d'exemple supplémentaires :**
- `App/Controllers/DefaultController.php` — contrôleur de bienvenue
- `Templates/layouts/base_layout.html.twig` — layout Twig de base
- `Templates/pages/default/index.html.twig` — page d'accueil
- `Assets/css/app.css` — styles de base
- `Assets/js/app.js` — JS de base
- `Translations/fr/` et `Translations/en/` — traductions initiales

**Gestion automatique de Composer :**

La commande alloue automatiquement un port disponible (à partir de 8000) en scannant les projets existants, enregistre le projet dans le `composer.json` racine comme dépendance `path`, puis lance `composer update` automatiquement.

```bash
php bin/neo project:create MonSite
# → Crée src/MonSite/ avec tous les fichiers
# → Port 8001 assigné si 8000 est déjà utilisé
# → composer.json racine mis à jour
# → composer update exécuté
```

```bash
php bin/neo project:create MonSite --skeleton
# → Crée uniquement la structure de dossiers et les configs
```

#### Module généré

Chaque projet reçoit un module principal (`{Name}Module.php`) implémentant `ModuleInterface` :

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

**Fichier :** `Commands/ProjectDeleteCommand.php`

Supprime entièrement un projet NeoPHP : ses sources, ses builds et son enregistrement dans `composer.json`.

#### Synopsis

```bash
php bin/neo project:delete <projectName>
```

#### Comportement

1. Demande une confirmation interactive avant toute suppression (action irréversible).
2. Supprime le dossier `public/builds/{Project}` si il existe.
3. Retire le projet du `composer.json` racine (entrée `repositories` et `require`).
4. Supprime le dossier `src/{Project}`.
5. Lance `composer update --optimize-autoloader` pour nettoyer l'autoloader.

```bash
php bin/neo project:delete MonSite
# Affiche : "You are about to delete project 'MonSite'. This action is irreversible."
# Demande confirmation, puis supprime tout
```

---

### `project:sync`

**Fichier :** `Commands/ProjectSyncCommand.php`

Synchronise le `composer.json` racine avec tous les projets présents dans `./src/`. Utile après un clone du dépôt ou un ajout manuel de projet.

#### Synopsis

```bash
php bin/neo project:sync [--run-composer]
```

#### Options

| Nom              | Description                                            |
|------------------|--------------------------------------------------------|
| `--run-composer` | Lance `composer update` automatiquement après la sync  |

#### Comportement

La commande parcourt tous les sous-dossiers de `./src/` possédant un `composer.json`, et pour chacun vérifie si le projet est déjà référencé dans le `composer.json` racine. Si ce n'est pas le cas, il est ajouté automatiquement.

```bash
php bin/neo project:sync
# → 2 added, 1 already present

php bin/neo project:sync --run-composer
# → Sync + composer update
```

---

## Structure des fichiers

```
neo/Core/Application/
├── ApplicationDetector.php         # Détection HTTP et CLI du projet actif
├── ApplicationPaths.php            # Enregistrement des chemins dans le conteneur
├── Exception/
│   └── ApplicationException.php
└── Commands/
    ├── ProjectCreateCommand.php    # project:create
    ├── ProjectDeleteCommand.php    # project:delete
    └── ProjectSyncCommand.php      # project:sync
```
