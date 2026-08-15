# Cron

The `Cron` module allows scheduling and running recurring tasks in NeoPHP. Jobs are declared via a PHP attribute directly on class methods, discovered through automatic scanning, and run based on a standard 5-field cron expression. The module supports job locking, timezone handling, and integrates with the framework's logging system.

---

## Summary

- [Cron Attribute](#cron-attribute)
- [CronScanner](#cronscanner)
- [CronRunner](#cronrunner)
- [Commands](#commands)
  - [cron:run](#cronrun)
  - [cron:list](#cronlist)
  - [make:cron](#makecron)
- [System Integration](#system-integration)

---

## Cron Attribute

**File:** `Attribute/Cron.php`

Native PHP attribute applied at the **method** level to declare a scheduled job.

```php
#[Attribute(Attribute::TARGET_METHOD)]
final class Cron
{
    public function __construct(
        public readonly string $expression,   // 5-field cron expression
        public readonly string $description,  // Human-readable job description
        public readonly string $timezone = 'UTC', // Timezone
        public readonly bool   $lock = false, // Execution locking
    ) {}
}
```

### Declaring a job

```php
namespace Neo\Src\MyProject\App\Crons;

use Neo\Core\Cron\Attribute\Cron;

final class MaintenanceCron
{
    #[Cron(
        expression: '0 2 * * *',
        description: 'Clears the logs every night at 2am',
        timezone: 'Europe/Paris',
        lock: true,
    )]
    public function cleanLogs(): void
    {
        // Cleanup logic...
    }

    #[Cron(
        expression: '*/15 * * * *',
        description: 'Syncs data every 15 minutes',
    )]
    public function syncData(): void
    {
        // Sync logic...
    }
}
```

### Cron expression format

Expressions follow the standard 5-field POSIX format:

```
┌───── minute       (0-59)
│  ┌──── hour         (0-23)
│  │  ┌─── day of month (1-31)
│  │  │  ┌── month        (1-12)
│  │  │  │  ┌─ day of week (0-6, 0=Sunday)
│  │  │  │  │
*  *  *  *  *
```

| Expression       | Meaning                                    |
|-------------------|---------------------------------------------|
| `* * * * *`       | Every minute                                 |
| `0 * * * *`       | At the start of every hour                   |
| `0 0 * * *`       | Every day at midnight                        |
| `0 0 * * 0`       | Every Sunday at midnight                     |
| `0 0 1 * *`       | On the first of every month at midnight      |
| `*/15 * * * *`    | Every 15 minutes                             |
| `0 9-17 * * 1-5`  | Every hour from 9am to 5pm on weekdays        |
| `0,30 * * * *`    | Every 30 minutes                             |

**Syntax supported by the parser:**
- `*` — always true
- `*/n` — every n intervals (modulo)
- `n-m` — inclusive range
- `n,m,p` — list of values
- `n` — exact value

---

## CronScanner

**File:** `Scanner/CronScanner.php`

Recursively scans a crons folder to discover every job declared via `#[Cron]`.

### How it works

```php
$scanner = new CronScanner();
$jobs = $scanner->scan('/path/to/src/MyProject/App/Crons');
```

The scanner:
1. Recursively walks the folder via `RecursiveDirectoryIterator`.
2. For each `.php` file, extracts the namespace and class name by analyzing its content (regex).
3. Loads the file (`require_once`) and instantiates a `ScannerAttributeManager` on the class.
4. Collects every public method decorated with `#[Cron]`.

### Returned structure

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

Example result:

```php
[
    [
        'class'       => 'Neo\Src\MyProject\App\Crons\MaintenanceCron',
        'method'      => 'cleanLogs',
        'expression'  => '0 2 * * *',
        'description' => 'Clears the logs every night at 2am',
        'timezone'    => 'Europe/Paris',
        'lock'        => true,
    ],
    [
        'class'       => 'Neo\Src\MyProject\App\Crons\MaintenanceCron',
        'method'      => 'syncData',
        'expression'  => '*/15 * * * *',
        'description' => 'Syncs data every 15 minutes',
        'timezone'    => 'UTC',
        'lock'        => false,
    ],
]
```

---

## CronRunner

**File:** `Runner/CronRunner.php`

Runs the list of scanned jobs, only triggering those whose cron expression matches the current moment.

### Due check (`isDue`)

For each job, `CronRunner` creates a `DateTime` object in the job's timezone, then compares the five fields of the expression (minute, hour, day, month, weekday) against the current values.

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

### Locking mechanism

When `lock: true` is declared on a job, `CronRunner` creates a lock file in the system's temp directory before running the job, and removes it in the `finally` block.

```
/tmp/neo_cron_<md5(class::method)>.lock
```

If the lock file already exists at execution time, the job is skipped with a log warning. This prevents concurrent runs of the same job (for example if a job takes longer than a minute and cron fires again on the next minute).

### Resolving the job instance

The runner first tries to resolve the class from the dependency container (to benefit from automatic injection). If that fails, it instantiates the class directly.

```php
try {
    $instance = $this->container->get($job['class']);
} catch (\Throwable) {
    $instance = new ($job['class'])();
}

$instance->{$job['method']}();
```

### Logs

Every run (success or failure) is logged. The runner attempts to use the framework's logging module (`cron.loggerModule`), and also prints the message to the console via `Output`.

| Event                      | Log level | Console output              |
|-------------------------------|-------------|----------------------------------|
| Job ran successfully         | `info`      | `Output::info()`                 |
| Job skipped (lock active)    | `warning`   | `Output::warning()`              |
| Job failed                    | `error`     | `Output::error()`                |

---

## Commands

### `cron:run`

**File:** `Command/CronRunCommand.php`

Scans and runs every due cron job for a given project. This command is meant to be called by the system scheduler (crontab) every minute.

#### Synopsis

```bash
php bin/neo cron:run --project=<Project>
```

#### Recommended crontab configuration

```cron
* * * * * /usr/bin/php /var/www/mysite/bin/neo cron:run --project=MyProject >> /var/log/neo-cron.log 2>&1
```

#### Behavior

1. Checks that the project exists inside `./src/`.
2. Registers the project's paths in the container (`ApplicationPaths::register()`).
3. Scans the project's `App/Crons/` folder via `CronScanner`.
4. Passes the list of jobs to `CronRunner::run()`.
5. Only jobs whose expression matches the current moment are run.

```bash
php bin/neo cron:run --project=MyProject
# → (silent if no job is due at this moment)
# → "→ Cron 'MaintenanceCron::cleanLogs' executed successfully."
```

---

### `cron:list`

**File:** `Command/CronListCommand.php`

Displays the list of every cron job registered for a project, with its expression, class, and description.

#### Synopsis

```bash
php bin/neo cron:list --project=<Project>
```

#### Example output

```
Registered Cron Jobs
────────────────────────────────────────────────────────────

  0 2 * * *           Neo\Src\MyProject\App\Crons\MaintenanceCron::cleanLogs   Clears the logs every night at 2am
  */15 * * * *        Neo\Src\MyProject\App\Crons\MaintenanceCron::syncData    Syncs data every 15 minutes
```

---

### `make:cron`

**File:** `Command/MakeCronCommand.php`

Generates a Cron class skeleton for a NeoPHP project.

#### Synopsis

```bash
php bin/neo make:cron [cron] --project=<Project> [--expression=<expr>] [--force]
```

#### Options

| Name             | Description                                                          |
|--------------------|---------------------------------------------------------------------------|
| `cron`            | Class name (optional, asked interactively)                                |
| `--project`       | Target project                                                             |
| `--expression`    | Cron expression (with autocompletion on common expressions)               |
| `--force`         | Overwrites the file if it already exists                                   |

#### Common expressions offered for autocompletion

| Expression      | Frequency            |
|-------------------|--------------------------|
| `* * * * *`      | Every minute              |
| `0 * * * *`      | Every hour                 |
| `0 0 * * *`      | Every day                  |
| `0 0 * * 0`      | Every week                 |
| `0 0 1 * *`      | Every month                |

#### Name normalization

The name is automatically normalized to PascalCase with the `Cron` suffix:
- `clean-logs` → `CleanLogsCron`
- `sync data` → `SyncDataCron`
- `ReportCron` → `ReportCron` (unchanged)

#### Usage example

```bash
php bin/neo make:cron CleanLogs --project=MyProject --expression="0 3 * * *"
```

#### Generated file

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

## System Integration

### Full architecture

```
crontab (every minute)
        │
        ▼
php bin/neo cron:run --project=MyProject
        │
        ▼
CronRunCommand::do()
        │
        ├── ApplicationPaths::register('MyProject')
        │       └── registers 'cronsPath' in the container
        │
        ├── CronScanner::scan(cronsPath)
        │       └── reads src/MyProject/App/Crons/**/*.php
        │       └── returns the list of jobs with their attributes
        │
        └── CronRunner::run(jobs)
                ├── For each job: isDue(expression, timezone) ?
                │       └── Yes → check lock → instantiate → run → log
                └── No → silent skip
```

### Crons folder

Cron classes must be placed inside:

```
src/{Project}/App/Crons/
├── MaintenanceCron.php
├── ReportCron.php
└── SyncCron.php
```

The scanner walks this folder recursively, so subfolders are supported:

```
src/{Project}/App/Crons/
├── Maintenance/
│   ├── CleanLogsCron.php
│   └── PurgeCacheCron.php
└── Reports/
    └── DailyReportCron.php
```

### A single class can contain multiple jobs

```php
final class DatabaseCron
{
    #[Cron(expression: '0 1 * * *', description: 'Daily backup', lock: true)]
    public function backup(): void { /* ... */ }

    #[Cron(expression: '0 4 * * 0', description: 'Weekly optimization', lock: true)]
    public function optimize(): void { /* ... */ }

    #[Cron(expression: '*/5 * * * *', description: 'Connection health check')]
    public function healthCheck(): void { /* ... */ }
}
```

---

## File structure

```
neo/Core/Cron/
├── Attribute/
│   └── Cron.php                    # #[Cron] attribute for methods
├── Scanner/
│   └── CronScanner.php             # Discovery through file scanning
├── Runner/
│   └── CronRunner.php              # Execution with lock/timezone/log handling
├── Exception/
│   └── CronException.php
└── Commands/
    ├── CronRunCommand.php           # cron:run
    ├── CronListCommand.php          # cron:list
    └── MakeCronCommand.php          # make:cron
```