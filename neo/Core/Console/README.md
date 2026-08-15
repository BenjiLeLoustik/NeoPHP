# Console

The `Console` module is NeoPHP's CLI infrastructure. It provides the command discovery and execution engine, a typed input/output system, interactive helpers (questions, choices, confirmation, hidden input), colored terminal rendering, and a command generator.

---

## Summary

- [ConsoleManager](#consolemanager)
- [AbstractCommand](#abstractcommand)
- [Command Attribute](#command-attribute)
- [Input](#input)
  - [InputArgument](#inputargument)
  - [InputOption](#inputoption)
  - [Interactive Helpers](#interactive-helpers)
- [Output](#output)
- [Color Enum](#color-enum)
- [Native Commands](#native-commands)
  - [app:make:command](#appmakecommand)
  - [app:serve](#appserve)

---

## ConsoleManager

**File:** `ConsoleManager.php`

`ConsoleManager` is the entry point for every CLI execution. It is invoked from the `bin/neo` script and orchestrates command discovery, loading, and execution.

### Automatic discovery

The manager recursively scans two folders looking for PHP files located inside a `Command/` subfolder:

- `neo/` — the framework's native commands
- `src/` — application projects' commands

Only classes extending `AbstractCommand` and decorated with `#[Command]` are registered. Abstract classes are ignored.

### Execution

```bash
php bin/neo <command> [arguments] [options]
php bin/neo <command> --help   # Shows the command's help
php bin/neo                    # Lists all commands
```

### Automatic project handling

If the command declares a `--project` option and no project is yet loaded in the container, the manager interrupts execution to interactively ask for the target project. The container is then re-instantiated with the correct application context before calling `do()`.

```bash
php bin/neo make:controller MyController
# → Target project ? (interactive list if --project is absent)
```

### Global help display

With no argument, the manager displays every command grouped by category, sorted alphabetically.

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

**File:** `Abstract/AbstractCommand.php`

Base class that every NeoPHP command must extend. It provides the mechanism for defining arguments and options, input validation, and contextual help rendering.

### Creating a command

```php
#[Command(
    name: 'cache:clear',
    description: 'Clears the application cache',
    category: 'Cache',
)]
final class ClearCacheCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->addArgument(
            name: 'driver',
            description: 'Cache driver to clear',
            mode: InputArgument::OPTIONAL,
            default: 'all',
        );

        $this->addOption(
            name: 'force',
            shortcut: 'f',
            mode: InputOption::NONE,
            description: 'Force without confirmation',
        );
    }

    public function do(Input $input, Output $output): ExitCode
    {
        $driver = $input->getArgument('driver');
        $force  = (bool) $input->getOption('force');

        if (!$force && !Input::confirm("Clear the '$driver' cache?")) {
            Output::muted('Cancelled.');
            return ExitCode::SUCCESS;
        }

        // ... clearing logic ...

        Output::success("Cache '$driver' cleared.");
        return ExitCode::SUCCESS;
    }
}
```

### Methods available inside `configure()`

| Method          | Description                                          |
|-------------------|---------------------------------------------------------|
| `addArgument()`  | Adds a positional argument to the command                |
| `addOption()`    | Adds a named option (`--name` or `-n`)                    |

### The `do()` method

The `do()` method is the command's body. It receives an `Input` object and an `Output` object, and must return a value from the `ExitCode` enum:

| Value                | Exit code | Meaning              |
|-----------------------|-------------|-------------------------|
| `ExitCode::SUCCESS`  | `0`         | Success                 |
| `ExitCode::FAILURE`  | `1`         | Failure                 |
| `ExitCode::INVALID`  | `2`         | Invalid input           |

### Automatic help

Every command automatically gets the `--help` / `-h` option, which displays the list of arguments, options and their description.

```bash
php bin/neo cache:clear --help

Command     : cache:clear
Description : Clears the application cache

  Arguments:
  <driver> (optional)          Cache driver to clear

  Options:
  -f, --force                  Force without confirmation

  Global options:
  --help, -h                   Show this help message
```

---

## Command Attribute

**File:** `Attribute/Command.php`

Native PHP attribute (`#[Attribute]`) applied at the class level to declare a command and its metadata.

```php
#[Attribute(Attribute::TARGET_CLASS)]
class Command
{
    public function __construct(
        public ?string $name = null,        // CLI name (e.g. 'cache:clear')
        public ?string $description = null, // Short description
        public ?string $category = null,    // Group shown in the help (e.g. 'Cache')
        public ?string $project = null,     // Associated project (optional)
    ) {}
}
```

The `project` property is used by `ConsoleManager::findProjectForCommand()` to determine which project to load before running the command.

---

## Input

**File:** `Input/Input.php`

Class responsible for parsing and accessing arguments and options passed on the command line.

### Parsing CLI tokens

`Input` automatically handles the following forms:

| Form                        | Example                    | Result                            |
|-------------------------------|-------------------------------|--------------------------------------|
| Option with `=`              | `--project=MyApp`             | option `project` = `"MyApp"`        |
| Option with value            | `--project MyApp`             | option `project` = `"MyApp"`        |
| Flag (no value)               | `--force`                     | option `force` = `true`             |
| Short shortcut                | `-f`                           | option `force` = `true`             |
| Short shortcut + value        | `-d SubFolder`                 | option `dir` = `"SubFolder"`        |
| Positional argument          | `MyController`                 | argument `controller` = `"MyController"` |

### Retrieving values

```php
$input->getArgument('controller'); // → "MyController"
$input->getOption('project');      // → "MyApp"
$input->hasOption('force');        // → true if --force is present and not false
```

### InputArgument

**File:** `Input/InputArgument.php`

Defines a positional argument with its mode and an optional default value.

```php
$this->addArgument(
    name: 'files',
    description: 'Files to process',
    mode: InputArgument::IS_ARRAY, // Captures all remaining tokens
);
```

| Constant                    | Value | Behavior                                          |
|-------------------------------|---------|------------------------------------------------------|
| `InputArgument::REQUIRED`   | `1`     | Required — error if absent                          |
| `InputArgument::OPTIONAL`   | `2`     | Optional — uses the default value                    |
| `InputArgument::IS_ARRAY`   | `4`     | Captures multiple values into an array                |

Modes can be combined with OR: `InputArgument::REQUIRED | InputArgument::IS_ARRAY`.

### InputOption

**File:** `Input/InputOption.php`

Defines a named option with its shortcut, mode, and default value.

```php
$this->addOption(
    name: 'format',
    shortcut: 'f',
    mode: InputOption::REQUIRED,
    description: 'Output format',
    default: 'json',
);
```

| Constant                    | Value | Behavior                                             |
|-------------------------------|---------|---------------------------------------------------------|
| `InputOption::NONE`         | `1`     | Boolean flag — no value expected                        |
| `InputOption::REQUIRED`     | `2`     | Required value (`--format=json` or `--format json`)      |
| `InputOption::OPTIONAL`     | `4`     | Optional value                                            |
| `InputOption::IS_ARRAY`     | `8`     | Multiple values accepted                                   |

### Interactive Helpers

All interactive methods are static methods on the `Input` class.

#### `Input::ask()` — Free text input

```php
$name = Input::ask('Project name?', 'MyProject');
// → "Project name? [MyProject] : "
```

#### `Input::confirm()` — Yes / No

```php
if (Input::confirm('Confirm deletion?', false)) {
    // ...
}
// → "Confirm deletion? [y/N] : "
// Accepts: y, yes, o, oui (case-insensitive)
```

#### `Input::choice()` — Selection from a list

```php
$project = Input::choice('Target project?', ['MySite', 'MyApi', 'Admin'], 'MySite');
// → Displays a numbered list, returns the selected choice
```

#### `Input::multiChoice()` — Multiple selection

```php
$formats = Input::multiChoice('Formats to export?', ['json', 'csv', 'xml']);
// → "1,3" selects json and xml
```

#### `Input::secret()` — Hidden input (passwords)

```php
$password = Input::secret('Database password?');
// → Masks keyboard input on Unix (stty -echo)
// → Normal reading on Windows
```

#### `Input::autocomplete()` — Input with suggestions

```php
$expression = Input::autocomplete(
    'Cron expression?',
    ['* * * * *', '0 * * * *', '0 0 * * *'],
    '* * * * *'
);
// → Automatically completes if the input matches the start of a suggestion
```

---

## Output

**File:** `Output/Output.php`

Terminal rendering utility class. All of its methods are static.

### Rendering methods

| Method                             | Color     | Example usage                                  |
|---------------------------------------|-------------|----------------------------------------------------|
| `Output::success($message)`         | Green       | Confirmation of a successful operation             |
| `Output::error($message)`           | Red         | Displaying an error                                |
| `Output::warning($message)`         | Yellow      | Non-blocking warning                               |
| `Output::info($message)`            | Cyan `→`    | Regular information                                |
| `Output::muted($message)`           | Grayed out  | Secondary, low-importance message                  |
| `Output::step($step, $message)`     | Blue        | Step within a multi-phase process                  |
| `Output::skip($message)`            | Yellow/dim  | Skipped item (`[SKIP]`)                            |
| `Output::label($label, $value)`     | Bold        | Displaying a key/value pair                        |
| `Output::title($message)`           | White/bold  | Section header with a separator                    |
| `Output::separator()`               | Grayed out  | Horizontal separator line                          |
| `Output::newLine()`                 | —           | Line break                                         |
| `Output::badge($text, $color)`      | BG color    | Inline colored badge (returns a string)            |
| `Output::usage($command, $desc)`    | Cyan        | Help header for a command                          |
| `Output::option($flag, $desc)`      | Yellow      | Help line for an option                            |
| `Output::argument($name, $desc)`    | Cyan        | Help line for an argument                          |
| `Output::example($cmd)`             | Green       | Example command                                    |
| `Output::progress($cur, $total)`    | Green       | Real-time progress bar                             |
| `Output::colorize($text, $color)`   | —           | Colorizes a string (returns a string)               |

### Concrete examples

```php
Output::title('Project synchronization');
Output::info('Analyzing...');
Output::step('1/3', 'Reading source files');
Output::step('2/3', 'Compiling assets');
Output::step('3/3', 'Updating the manifest');
Output::success('Synchronization complete.');

// Progress bar
for ($i = 1; $i <= 10; $i++) {
    Output::progress($i, 10, "File $i/10");
    usleep(100000);
}

// Inline badge
echo Output::badge('NEW', 'green') . ' Feature available.' . "\n";
```

---

## Color Enum

**File:** `Enum/Color.php`

Pure PHP enum representing ANSI color and style codes. Used internally by `Output`.

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
    // Backgrounds: BG_RED, BG_GREEN, BG_YELLOW, BG_BLUE, BG_CYAN
}
```

Each case exposes two methods:

```php
Color::GREEN->wrap('Green text');   // → "\033[32mGreen text\033[0m"
Color::BOLD->apply();               // → "\033[1m" (without automatic reset)
```

---

## Native Commands

### `app:make:command`

**File:** `Command/MakeCommand.php`

Generates a command skeleton for an application project.

#### Synopsis

```bash
php bin/neo app:make:command [commandName] --project=<Project> [--name=<cli:name>] [--category=<cat>] [--force]
```

#### Options

| Name           | Description                                                       |
|------------------|-----------------------------------------------------------------------|
| `commandName`   | PHP class name (e.g. `CleanLogsCommand`)                              |
| `--project`     | Target project inside `./src/`                                         |
| `--name`        | CLI name of the command (e.g. `cache:clear`)                          |
| `--category`    | Category (`app`, `other`, `testing`, `cron`, `config`, `debug`)       |
| `--force`       | Overwrites the file if it already exists                              |

If a parameter is missing, the command asks for it interactively. The CLI name is automatically guessed from the class name (`CleanLogsCommand` → `clean:logs`).

#### Usage example

```bash
php bin/neo app:make:command CleanLogsCommand --project=MyProject --name=logs:clean --category=app
```

#### Generated file

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

**File:** `Command/ServeCommand.php`

Starts PHP's built-in HTTP server for a NeoPHP project. The address and port are read from the `access` key in the project's `app.config.php` file.

#### Synopsis

```bash
php bin/neo app:serve [project]
```

If `project` is omitted, an interactive list of available projects is displayed.

```bash
php bin/neo app:serve MyProject
# → Starting server for MyProject
# → URL: http://myproject.localhost:8001
# → (PHP server started on localhost:8001)

php bin/neo app:serve
# → Available projects:
#   [1] MyProject  → http://localhost:8000
#   [2] MyApi      → http://localhost:8001
# → Choose a project
```

The server is started via `passthru("php -S {access} -t public")`, targeting the `public/` folder as the web root.

---

## File structure

```
neo/Core/Console/
├── ConsoleManager.php             # Main CLI orchestrator
├── Abstract/
│   └── AbstractCommand.php        # Base class for all commands
├── Attribute/
│   └── Command.php                # #[Command] attribute
├── Input/
│   ├── Input.php                  # CLI parsing + interactive helpers
│   ├── InputArgument.php          # Argument definition
│   └── InputOption.php            # Option definition
├── Output/
│   └── Output.php                 # Colored terminal rendering
├── Enum/
│   └── Color.php                  # ANSI codes
├── Interface/
│   └── CommandInterface.php
├── Helper/
│   └── Fs.php                     # Filesystem utilities (ensureDir, pascalCase...)
└── Commands/
    ├── MakeCommand.php            # app:make:command
    └── ServeCommand.php           # app:serve
```