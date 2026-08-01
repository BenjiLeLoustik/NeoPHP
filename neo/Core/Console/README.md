# Console

Le module `Console` est l'infrastructure CLI de NeoPHP. Il fournit le moteur de découverte et d'exécution des commandes, un système d'entrée/sortie typé, des helpers interactifs (questions, choix, confirmation, saisie secrète), un rendu coloré en terminal, et un générateur de commandes.

---

## Sommaire

- [ConsoleManager](#consolemanager)
- [AbstractCommand](#abstractcommand)
- [Attribut Command](#attribut-command)
- [Input](#input)
  - [InputArgument](#inputargument)
  - [InputOption](#inputoption)
  - [Helpers interactifs](#helpers-interactifs)
- [Output](#output)
- [Enum Color](#enum-color)
- [Commandes natives](#commandes-natives)
  - [app:make:command](#appmakecommand)
  - [app:serve](#appserve)

---

## ConsoleManager

**Fichier :** `ConsoleManager.php`

`ConsoleManager` est le point d'entrée de toute exécution CLI. Il est invoqué depuis le script `bin/neo` et orchestre la découverte, le chargement et l'exécution des commandes.

### Découverte automatique

Le manager parcourt récursivement deux dossiers à la recherche de fichiers PHP situés dans un sous-dossier `Commands/` :

- `neo/` — commandes natives du framework
- `src/` — commandes des projets applicatifs

Seules les classes étendant `AbstractCommand` et décorées avec `#[Command]` sont enregistrées. Les classes abstraites sont ignorées.

### Exécution

```bash
php bin/neo <commande> [arguments] [options]
php bin/neo <commande> --help   # Affiche l'aide de la commande
php bin/neo                     # Affiche la liste de toutes les commandes
```

### Gestion automatique du projet

Si la commande déclare une option `--project` et qu'aucun projet n'est encore chargé dans le conteneur, le manager interrompt l'exécution pour demander interactivement le projet cible. Le conteneur est ensuite réinstancié avec le bon contexte applicatif avant d'appeler `do()`.

```bash
php bin/neo make:controller MonController
# → Target project ? (liste interactive si --project absent)
```

### Affichage de l'aide globale

Sans argument, le manager affiche toutes les commandes regroupées par catégorie, triées alphabétiquement.

```
 CONTROLLER
  make:controller          Create a web or API Controller for a project

 CRON
  cron:list                List all registered cron jobs for a project
  cron:run                 Run all due cron jobs for a project
  make:cron                Create a Cron class for a project

 PROJECT
  project:create           Create a new NeoPHP project inside ./src/
  project:delete           Delete a NeoPHP project
  project:sync             Sync root composer.json with all projects in ./src/
```

---

## AbstractCommand

**Fichier :** `Abstract/AbstractCommand.php`

Classe de base que doit étendre toute commande NeoPHP. Elle fournit le mécanisme de définition des arguments et options, la validation de l'entrée, et le rendu de l'aide contextuelle.

### Créer une commande

```php
#[Command(
    name: 'cache:clear',
    description: 'Vide le cache de l\'application',
    category: 'Cache',
)]
final class ClearCacheCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'driver',
            description: 'Driver de cache à vider',
            mode: InputArgument::OPTIONAL,
            default: 'all',
        );

        $this->addOption(
            name: 'force',
            shortcut: 'f',
            mode: InputOption::NONE,
            description: 'Forcer sans confirmation',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $driver = $input->getArgument('driver');
        $force  = (bool) $input->getOption('force');

        if (!$force && !Input::confirm("Vider le cache '$driver' ?")) {
            Output::muted('Annulé.');
            return ExitCode::SUCCESS;
        }

        // ... logique de vidage ...

        Output::success("Cache '$driver' vidé.");
        return ExitCode::SUCCESS;
    }
}
```

### Méthodes disponibles dans `configure()`

| Méthode        | Description                                        |
|----------------|----------------------------------------------------|
| `addArgument()`| Ajoute un argument positionnel à la commande       |
| `addOption()`  | Ajoute une option nommée (`--nom` ou `-n`)         |

### Méthode `do()`

La méthode `do()` est le corps de la commande. Elle reçoit un objet `Input` et un objet `Output`, et doit retourner une valeur de l'enum `ExitCode` :

| Valeur             | Code de sortie | Signification        |
|--------------------|---------------|----------------------|
| `ExitCode::SUCCESS`| `0`           | Succès               |
| `ExitCode::FAILURE`| `1`           | Échec                |
| `ExitCode::INVALID`| `2`           | Entrée invalide      |

### Aide automatique

Chaque commande bénéficie automatiquement de l'option `--help` / `-h` qui affiche la liste des arguments, options et leur description.

```bash
php bin/neo cache:clear --help

Command     : cache:clear
Description : Vide le cache de l'application

  Arguments:
  <driver> (optional)          Driver de cache à vider

  Options:
  -f, --force                  Forcer sans confirmation

  Global options:
  --help, -h                   Show this help message
```

---

## Attribut Command

**Fichier :** `Attribute/Command.php`

Attribut PHP natif (`#[Attribute]`) appliqué au niveau classe pour déclarer une commande et ses métadonnées.

```php
#[Attribute(Attribute::TARGET_CLASS)]
class Command
{
    public function __construct(
        public ?string $name = null,        // Nom CLI (ex: 'cache:clear')
        public ?string $description = null, // Description courte
        public ?string $category = null,    // Groupe dans l'aide (ex: 'Cache')
        public ?string $project = null,     // Projet associé (optionnel)
    ) {}
}
```

La propriété `project` est utilisée par `ConsoleManager::findProjectForCommand()` pour déterminer quel projet charger avant d'exécuter la commande.

---

## Input

**Fichier :** `Input/Input.php`

Classe responsable de l'analyse et de l'accès aux arguments et options passés en ligne de commande.

### Analyse des tokens CLI

`Input` gère automatiquement les formes suivantes :

| Forme                    | Exemple                    | Résultat                        |
|--------------------------|----------------------------|---------------------------------|
| Option avec `=`          | `--project=MyApp`          | option `project` = `"MyApp"`    |
| Option avec valeur       | `--project MyApp`          | option `project` = `"MyApp"`    |
| Flag (sans valeur)       | `--force`                  | option `force` = `true`         |
| Raccourci court          | `-f`                       | option `force` = `true`         |
| Raccourci court + valeur | `-d SubFolder`             | option `dir` = `"SubFolder"`    |
| Argument positionnel     | `MyController`             | argument `controller` = `"MyController"` |

### Récupérer les valeurs

```php
$input->getArgument('controller'); // → "MyController"
$input->getOption('project');      // → "MyApp"
$input->hasOption('force');        // → true si --force présent et non false
```

### InputArgument

**Fichier :** `Input/InputArgument.php`

Définit un argument positionnel avec son mode et une valeur par défaut optionnelle.

```php
$this->addArgument(
    name: 'files',
    description: 'Fichiers à traiter',
    mode: InputArgument::IS_ARRAY, // Capture tous les tokens restants
);
```

| Constante                 | Valeur | Comportement                                    |
|---------------------------|--------|-------------------------------------------------|
| `InputArgument::REQUIRED` | `1`    | Obligatoire — erreur si absent                 |
| `InputArgument::OPTIONAL` | `2`    | Facultatif — utilise la valeur par défaut       |
| `InputArgument::IS_ARRAY` | `4`    | Capture plusieurs valeurs dans un tableau       |

Les modes sont combinables par OR : `InputArgument::REQUIRED | InputArgument::IS_ARRAY`.

### InputOption

**Fichier :** `Input/InputOption.php`

Définit une option nommée avec son raccourci, son mode et sa valeur par défaut.

```php
$this->addOption(
    name: 'format',
    shortcut: 'f',
    mode: InputOption::REQUIRED,
    description: 'Format de sortie',
    default: 'json',
);
```

| Constante                 | Valeur | Comportement                                    |
|---------------------------|--------|-------------------------------------------------|
| `InputOption::NONE`       | `1`    | Flag booléen — pas de valeur attendue           |
| `InputOption::REQUIRED`   | `2`    | Valeur obligatoire (`--format=json` ou `--format json`) |
| `InputOption::OPTIONAL`   | `4`    | Valeur facultative                              |
| `InputOption::IS_ARRAY`   | `8`    | Plusieurs valeurs acceptées                     |

### Helpers interactifs

Toutes les méthodes interactives sont des méthodes statiques de la classe `Input`.

#### `Input::ask()` — Saisie libre

```php
$name = Input::ask('Nom du projet ?', 'MonProjet');
// → "Nom du projet ? [MonProjet] : "
```

#### `Input::confirm()` — Oui / Non

```php
if (Input::confirm('Confirmer la suppression ?', false)) {
    // ...
}
// → "Confirmer la suppression ? [y/N] : "
// Accepte : y, yes, o, oui (insensible à la casse)
```

#### `Input::choice()` — Sélection dans une liste

```php
$project = Input::choice('Projet cible ?', ['MonSite', 'MonApi', 'Admin'], 'MonSite');
// → Affiche une liste numérotée, retourne le choix sélectionné
```

#### `Input::multiChoice()` — Sélection multiple

```php
$formats = Input::multiChoice('Formats à exporter ?', ['json', 'csv', 'xml']);
// → "1,3" sélectionne json et xml
```

#### `Input::secret()` — Saisie masquée (mots de passe)

```php
$password = Input::secret('Mot de passe de la base de données ?');
// → Masque l'entrée clavier sur Unix (stty -echo)
// → Lecture normale sur Windows
```

#### `Input::autocomplete()` — Saisie avec suggestions

```php
$expression = Input::autocomplete(
    'Expression cron ?',
    ['* * * * *', '0 * * * *', '0 0 * * *'],
    '* * * * *'
);
// → Complète automatiquement si la saisie correspond au début d'une suggestion
```

---

## Output

**Fichier :** `Output/Output.php`

Classe utilitaire de rendu terminal. Toutes ses méthodes sont statiques.

### Méthodes de rendu

| Méthode                           | Couleur  | Exemple d'usage                              |
|-----------------------------------|----------|----------------------------------------------|
| `Output::success($message)`       | Vert     | Confirmation d'une opération réussie         |
| `Output::error($message)`         | Rouge    | Affichage d'une erreur                       |
| `Output::warning($message)`       | Jaune    | Avertissement non bloquant                   |
| `Output::info($message)`          | Cyan `→` | Information courante                         |
| `Output::muted($message)`         | Grisé    | Message secondaire, peu important            |
| `Output::step($step, $message)`   | Bleu     | Étape dans un processus multi-phases         |
| `Output::skip($message)`          | Jaune/dim| Élément ignoré (`[SKIP]`)                   |
| `Output::label($label, $value)`   | Gras     | Affichage d'une paire clé/valeur             |
| `Output::title($message)`         | Blanc/gras| En-tête de section avec séparateur         |
| `Output::separator()`             | Grisé    | Ligne de séparation horizontale              |
| `Output::newLine()`               | —        | Saut de ligne                                |
| `Output::badge($text, $color)`    | BG color | Badge coloré inline (retourne une string)    |
| `Output::usage($command, $desc)`  | Cyan     | En-tête d'aide d'une commande                |
| `Output::option($flag, $desc)`    | Jaune    | Ligne d'aide pour une option                 |
| `Output::argument($name, $desc)`  | Cyan     | Ligne d'aide pour un argument                |
| `Output::example($cmd)`           | Vert     | Exemple de commande                          |
| `Output::progress($cur, $total)`  | Vert     | Barre de progression en temps réel           |
| `Output::colorize($text, $color)` | —        | Colorise une chaîne (retourne une string)    |

### Exemples concrets

```php
Output::title('Synchronisation des projets');
Output::info('Analyse en cours...');
Output::step('1/3', 'Lecture des fichiers sources');
Output::step('2/3', 'Compilation des assets');
Output::step('3/3', 'Mise à jour du manifest');
Output::success('Synchronisation terminée.');

// Barre de progression
for ($i = 1; $i <= 10; $i++) {
    Output::progress($i, 10, "Fichier $i/10");
    usleep(100000);
}

// Badge inline
echo Output::badge('NEW', 'green') . ' Fonctionnalité disponible.' . "\n";
```

---

## Enum Color

**Fichier :** `Enum/Color.php`

Enum PHP pur représentant les codes ANSI de couleur et de style. Utilisé en interne par `Output`.

```php
enum Color: string
{
    case RESET  = "\033[0m";
    case BOLD   = "\033[1m";
    case DIM    = "\033[2m";
    case RED    = "\033[31m";
    case GREEN  = "\033[32m";
    case YELLOW = "\033[33m";
    case BLUE   = "\033[34m";
    case CYAN   = "\033[36m";
    case WHITE  = "\033[37m";
    // Fonds : BG_RED, BG_GREEN, BG_YELLOW, BG_BLUE, BG_CYAN
}
```

Chaque case expose deux méthodes :

```php
Color::GREEN->wrap('Texte vert');  // → "\033[32mTexte vert\033[0m"
Color::BOLD->apply();              // → "\033[1m" (sans reset automatique)
```

---

## Commandes natives

### `app:make:command`

**Fichier :** `Commands/MakeCommand.php`

Génère un squelette de commande pour un projet applicatif.

#### Synopsis

```bash
php bin/neo app:make:command [commandName] --project=<Project> [--name=<cli:name>] [--category=<cat>] [--force]
```

#### Options

| Nom          | Description                                              |
|--------------|----------------------------------------------------------|
| `commandName`| Nom de la classe PHP (ex. `CleanLogsCommand`)            |
| `--project`  | Projet cible dans `./src/`                               |
| `--name`     | Nom CLI de la commande (ex. `cache:clear`)               |
| `--category` | Catégorie (`app`, `other`, `testing`, `cron`, `config`, `debug`) |
| `--force`    | Écrase le fichier si il existe déjà                     |

Si un paramètre est absent, la commande le demande interactivement. Le nom CLI est deviné automatiquement depuis le nom de classe (`CleanLogsCommand` → `clean:logs`).

#### Exemple d'utilisation

```bash
php bin/neo app:make:command CleanLogsCommand --project=MyProject --name=logs:clean --category=app
```

#### Fichier généré

```
src/MyProject/App/Commands/CleanLogsCommand.php
```

```php
namespace Neo\Src\MyProject\App\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Output\Output;
use Neo\Core\Console\Input\Input;

#[Command(
    name: 'logs:clean',
    description: 'Add a short description',
    category: 'app'
)]
final class CleanLogsCommand extends AbstractCommand
{
    public function configure(): void
    {
        // TODO: Configure arguments and options
    }

    public function do(Input $input, Output $output): ExitCode
    {
        // TODO: implement command logic
        Output::success('Done.');
        return ExitCode::SUCCESS;
    }
}
```

---

### `app:serve`

**Fichier :** `Commands/ServeCommand.php`

Lance le serveur HTTP intégré de PHP pour un projet NeoPHP. L'adresse et le port sont lus depuis la clé `access` du fichier `app.config.php` du projet.

#### Synopsis

```bash
php bin/neo app:serve [project]
```

Si `project` est omis, une liste interactive des projets disponibles est affichée.

```bash
php bin/neo app:serve MyProject
# → Starting server for MyProject
# → URL: http://myproject.localhost:8001
# → (serveur PHP lancé sur localhost:8001)

php bin/neo app:serve
# → Available projects:
#   [1] MyProject  → http://localhost:8000
#   [2] MyApi      → http://localhost:8001
# → Choose a project
```

Le serveur est démarré via `passthru("php -S {access} -t public")`, en ciblant le dossier `public/` comme racine web.

---

## Structure des fichiers

```
neo/Core/Console/
├── ConsoleManager.php              # Orchestrateur CLI principal
├── Abstract/
│   └── AbstractCommand.php        # Classe de base pour toutes les commandes
├── Attribute/
│   └── Command.php                # Attribut #[Command]
├── Input/
│   ├── Input.php                  # Parsing CLI + helpers interactifs
│   ├── InputArgument.php          # Définition d'un argument
│   └── InputOption.php            # Définition d'une option
├── Output/
│   └── Output.php                 # Rendu terminal coloré
├── Enum/
│   └── Color.php                  # Codes ANSI
├── Interface/
│   └── CommandInterface.php
├── Helper/
│   └── Fs.php                     # Utilitaires filesystem (ensureDir, pascalCase...)
└── Commands/
    ├── MakeCommand.php            # app:make:command
    └── ServeCommand.php           # app:serve
```
