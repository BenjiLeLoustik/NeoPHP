# Module Cron

Le module `Cron` permet de planifier et d'exécuter des tâches récurrentes dans NeoPHP. Les jobs sont déclarés par attribut PHP directement sur des méthodes de classes, découverts par scan automatique, et exécutés en fonction d'une expression cron standard à 5 champs. Le module supporte le verrouillage de jobs, la gestion des fuseaux horaires, et s'intègre avec le système de logs du framework.

---

## Sommaire

- [Attribut Cron](#attribut-cron)
- [CronScanner](#cronscanner)
- [CronRunner](#cronrunner)
- [Commandes](#commandes)
  - [cron:run](#cronrun)
  - [cron:list](#cronlist)
  - [make:cron](#makecron)
- [Intégration système](#intégration-système)

---

## Attribut Cron

**Fichier :** `Attribute/Cron.php`

Attribut PHP natif appliqué au niveau des **méthodes** pour déclarer un job planifié.

```php
#[Attribute(Attribute::TARGET_METHOD)]
final class Cron
{
    public function __construct(
        public readonly string $expression,   // Expression cron 5 champs
        public readonly string $description,  // Description lisible du job
        public readonly string $timezone = 'UTC', // Fuseau horaire
        public readonly bool   $lock = false, // Verrouillage d'exécution
    ) {}
}
```

### Déclaration d'un job

```php
namespace Neo\Src\MyProject\App\Crons;

use Neo\Core\Cron\Attribute\Cron;

final class MaintenanceCron
{
    #[Cron(
        expression: '0 2 * * *',
        description: 'Nettoyage des logs toutes les nuits à 2h',
        timezone: 'Europe/Paris',
        lock: true,
    )]
    public function cleanLogs(): void
    {
        // Logique de nettoyage...
    }

    #[Cron(
        expression: '*/15 * * * *',
        description: 'Synchronisation des données toutes les 15 minutes',
    )]
    public function syncData(): void
    {
        // Logique de synchronisation...
    }
}
```

### Format des expressions cron

Les expressions suivent le format standard POSIX à 5 champs :

```
┌───── minute       (0-59)
│  ┌──── heure        (0-23)
│  │  ┌─── jour du mois (1-31)
│  │  │  ┌── mois        (1-12)
│  │  │  │  ┌─ jour de semaine (0-6, 0=dimanche)
│  │  │  │  │
*  *  *  *  *
```

| Expression      | Signification                             |
|-----------------|-------------------------------------------|
| `* * * * *`     | Chaque minute                             |
| `0 * * * *`     | Au début de chaque heure                  |
| `0 0 * * *`     | Tous les jours à minuit                   |
| `0 0 * * 0`     | Tous les dimanches à minuit               |
| `0 0 1 * *`     | Le premier de chaque mois à minuit        |
| `*/15 * * * *`  | Toutes les 15 minutes                     |
| `0 9-17 * * 1-5`| Toutes les heures de 9h à 17h les jours ouvrés |
| `0,30 * * * *`  | Toutes les 30 minutes                     |

**Syntaxes supportées par le parser :**
- `*` — toujours vrai
- `*/n` — tous les n intervalles (modulo)
- `n-m` — plage inclusive
- `n,m,p` — liste de valeurs
- `n` — valeur exacte

---

## CronScanner

**Fichier :** `Scanner/CronScanner.php`

Analyse récursivement un dossier de crons pour découvrir tous les jobs déclarés via `#[Cron]`.

### Fonctionnement

```php
$scanner = new CronScanner();
$jobs = $scanner->scan('/path/to/src/MyProject/App/Crons');
```

Le scanner :
1. Parcourt récursivement le dossier via `RecursiveDirectoryIterator`.
2. Pour chaque fichier `.php`, extrait le namespace et le nom de classe par analyse de contenu (regex).
3. Charge le fichier (`require_once`) et instancie une `ScannerAttributeManager` sur la classe.
4. Collecte toutes les méthodes publiques décorées avec `#[Cron]`.

### Structure retournée

```php
/** @return list<array{
 *     class: class-string,
 *     method: string,
 *     expression: string,
 *     description: string,
 *     timezone: string,
 *     lock: bool
 * }> */
```

Exemple de résultat :

```php
[
    [
        'class'       => 'Neo\Src\MyProject\App\Crons\MaintenanceCron',
        'method'      => 'cleanLogs',
        'expression'  => '0 2 * * *',
        'description' => 'Nettoyage des logs toutes les nuits à 2h',
        'timezone'    => 'Europe/Paris',
        'lock'        => true,
    ],
    [
        'class'       => 'Neo\Src\MyProject\App\Crons\MaintenanceCron',
        'method'      => 'syncData',
        'expression'  => '*/15 * * * *',
        'description' => 'Synchronisation des données toutes les 15 minutes',
        'timezone'    => 'UTC',
        'lock'        => false,
    ],
]
```

---

## CronRunner

**Fichier :** `Runner/CronRunner.php`

Exécute la liste des jobs scannés en ne lançant que ceux dont l'expression cron correspond à l'instant d'exécution.

### Vérification de l'échéance (`isDue`)

Pour chaque job, `CronRunner` crée un objet `DateTime` dans le fuseau horaire du job, puis compare les cinq champs de l'expression (minute, heure, jour, mois, jour de semaine) avec les valeurs actuelles.

```php
private function isDue(string $expression, string $timezone): bool
{
    [$minute, $hour, $day, $month, $weekday] = explode(' ', trim($expression));

    $now = new DateTime('now', new DateTimeZone($timezone));

    return $this->matchesPart($minute,   (int) $now->format('i'))
        && $this->matchesPart($hour,     (int) $now->format('G'))
        && $this->matchesPart($day,      (int) $now->format('j'))
        && $this->matchesPart($month,    (int) $now->format('n'))
        && $this->matchesPart($weekday,  (int) $now->format('w'));
}
```

### Mécanisme de verrouillage (lock)

Quand `lock: true` est déclaré sur un job, `CronRunner` crée un fichier verrou dans le répertoire temporaire du système avant d'exécuter le job, et le supprime dans le bloc `finally`.

```
/tmp/neo_cron_<md5(class::method)>.lock
```

Si le fichier verrou existe déjà au moment de l'exécution, le job est ignoré avec un avertissement de log. Cela prévient les exécutions concurrentes d'un même job (par exemple si un job dure plus d'une minute et que le cron est appelé à la minute suivante).

### Résolution de l'instance du job

Le runner tente d'abord de résoudre la classe depuis le conteneur de dépendances (pour bénéficier de l'injection automatique). En cas d'échec, il instancie la classe directement.

```php
try {
    $instance = $this->container->get($job['class']);
} catch (\Throwable) {
    $instance = new ($job['class'])();
}

$instance->{$job['method']}();
```

### Logs

Chaque exécution (succès ou échec) est journalisée. Le runner tente d'utiliser le module de logs du framework (`cron.loggerModule`), et affiche également le message en console via `Output`.

| Événement                | Niveau de log | Sortie console             |
|--------------------------|---------------|----------------------------|
| Job exécuté avec succès  | `info`        | `Output::info()`           |
| Job ignoré (verrou actif)| `warning`     | `Output::warning()`        |
| Job en erreur            | `error`       | `Output::error()`          |

---

## Commandes

### `cron:run`

**Fichier :** `Commands/CronRunCommand.php`

Scanne et exécute tous les cron jobs échus pour un projet donné. Cette commande est conçue pour être appelée par le planificateur système (crontab) chaque minute.

#### Synopsis

```bash
php bin/neo cron:run --project=<Project>
```

#### Configuration crontab recommandée

```cron
* * * * * /usr/bin/php /var/www/monsite/bin/neo cron:run --project=MyProject >> /var/log/neo-cron.log 2>&1
```

#### Comportement

1. Vérifie que le projet existe dans `./src/`.
2. Enregistre les chemins du projet dans le conteneur (`ApplicationPaths::register()`).
3. Scanne le dossier `App/Crons/` du projet via `CronScanner`.
4. Passe la liste des jobs à `CronRunner::run()`.
5. Seuls les jobs dont l'expression correspond à l'instant courant sont exécutés.

```bash
php bin/neo cron:run --project=MyProject
# → (silence si aucun job n'est dû à cet instant)
# → "→ Cron 'MaintenanceCron::cleanLogs' executed successfully."
```

---

### `cron:list`

**Fichier :** `Commands/CronListCommand.php`

Affiche la liste de tous les cron jobs enregistrés pour un projet, avec leur expression, leur classe et leur description.

#### Synopsis

```bash
php bin/neo cron:list --project=<Project>
```

#### Exemple de sortie

```
Registered Cron Jobs
────────────────────────────────────────────────────────────

  0 2 * * *           Neo\Src\MyProject\App\Crons\MaintenanceCron::cleanLogs   Nettoyage des logs toutes les nuits à 2h
  */15 * * * *        Neo\Src\MyProject\App\Crons\MaintenanceCron::syncData    Synchronisation des données toutes les 15 minutes
```

---

### `make:cron`

**Fichier :** `Commands/MakeCronCommand.php`

Génère un squelette de classe Cron pour un projet NeoPHP.

#### Synopsis

```bash
php bin/neo make:cron [cron] --project=<Project> [--expression=<expr>] [--force]
```

#### Options

| Nom            | Description                                                         |
|----------------|---------------------------------------------------------------------|
| `cron`         | Nom de la classe (optionnel, demandé interactivement)               |
| `--project`    | Projet cible                                                        |
| `--expression` | Expression cron (avec autocomplétion sur les expressions communes)  |
| `--force`      | Écrase le fichier si il existe déjà                                |

#### Expressions communes proposées à l'autocomplétion

| Expression    | Fréquence          |
|---------------|--------------------|
| `* * * * *`   | Chaque minute      |
| `0 * * * *`   | Chaque heure       |
| `0 0 * * *`   | Chaque jour        |
| `0 0 * * 0`   | Chaque semaine     |
| `0 0 1 * *`   | Chaque mois        |

#### Normalisation du nom

Le nom est normalisé automatiquement en PascalCase avec le suffixe `Cron` :
- `clean-logs` → `CleanLogsCron`
- `sync data` → `SyncDataCron`
- `ReportCron` → `ReportCron` (inchangé)

#### Exemple d'utilisation

```bash
php bin/neo make:cron CleanLogs --project=MyProject --expression="0 3 * * *"
```

#### Fichier généré

`src/MyProject/App/Crons/CleanLogsCron.php`

```php
namespace Neo\Src\MyProject\App\Crons;

use Neo\Core\Cron\Attribute\Cron;

final class CleanLogsCron
{
    #[Cron(
        expression: '0 3 * * *',
        description: 'TODO: describe this cron job',
    )]
    public function handle(): void
    {
        // TODO: implement cron logic
    }
}
```

---

## Intégration système

### Architecture complète

```
crontab (chaque minute)
        │
        ▼
php bin/neo cron:run --project=MyProject
        │
        ▼
CronRunCommand::do()
        │
        ├── ApplicationPaths::register('MyProject')
        │       └── enregistre 'cronsPath' dans le conteneur
        │
        ├── CronScanner::scan(cronsPath)
        │       └── lit src/MyProject/App/Crons/**/*.php
        │       └── retourne la liste des jobs avec attributs
        │
        └── CronRunner::run(jobs)
                ├── Pour chaque job : isDue(expression, timezone) ?
                │       └── Oui → vérifie lock → instancie → exécute → log
                └── Non → skip silencieux
```

### Dossier des crons

Les classes de crons doivent être placées dans :

```
src/{Project}/App/Crons/
├── MaintenanceCron.php
├── ReportCron.php
└── SyncCron.php
```

Le scanner parcourt récursivement ce dossier, donc les sous-dossiers sont supportés :

```
src/{Project}/App/Crons/
├── Maintenance/
│   ├── CleanLogsCron.php
│   └── PurgeCacheCron.php
└── Reports/
    └── DailyReportCron.php
```

### Une classe peut contenir plusieurs jobs

```php
final class DatabaseCron
{
    #[Cron(expression: '0 1 * * *', description: 'Backup quotidien', lock: true)]
    public function backup(): void { /* ... */ }

    #[Cron(expression: '0 4 * * 0', description: 'Optimisation hebdomadaire', lock: true)]
    public function optimize(): void { /* ... */ }

    #[Cron(expression: '*/5 * * * *', description: 'Vérification des connexions')]
    public function healthCheck(): void { /* ... */ }
}
```

---

## Structure des fichiers

```
neo/Core/Cron/
├── Attribute/
│   └── Cron.php                    # Attribut #[Cron] pour les méthodes
├── Scanner/
│   └── CronScanner.php             # Découverte par scan de fichiers
├── Runner/
│   └── CronRunner.php              # Exécution avec gestion lock/timezone/log
├── Exception/
│   └── CronException.php
└── Commands/
    ├── CronRunCommand.php           # cron:run
    ├── CronListCommand.php          # cron:list
    └── MakeCronCommand.php          # make:cron
```
