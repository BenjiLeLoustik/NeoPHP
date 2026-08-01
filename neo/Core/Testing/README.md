# Testing

The Testing module provides the complete testing infrastructure for NeoPHP applications. It builds on PHPUnit and offers four specialized base classes, a PHP 8 attribute system for declaring tests directly on business classes, as well as an automatic test file generator.

---

## Summary

1. [Module Structure](#module-structure)
2. [Base Classes](#base-classes)
   - [TestCase](#testcase)
   - [DatabaseTestCase](#databasetestcase)
   - [FeatureTestCase](#featuretestcase)
   - [MiddlewareTestCase](#middlewaretestcase)
3. [The #[Test] Attribute and the TestType Enum](#the-test-attribute-and-the-testtype-enum)
4. [Scanner and Automatic Generator](#scanner-and-automatic-generator)
5. [CLI Commands](#cli-commands)
6. [Naming Conventions and Structure](#naming-conventions-and-structure)

---

## Module Structure

```
Testing/
├── TestCase.php                    # Base class for unit tests
├── DatabaseTestCase.php            # Base class for database tests
├── FeatureTestCase.php             # Base class for HTTP / feature tests
├── MiddlewareTestCase.php          # Base class for middleware tests
├── Attribute/
│   └── Test.php                   # PHP 8 #[Test] attribute
├── Enum/
│   └── TestType.php               # Enum of test types (unit, feature, database, middleware)
├── Context/
│   ├── TestClassContext.php        # Context of an analyzed class
│   └── TestMethodContext.php       # Context of an analyzed method
├── Scanner/
│   └── TestScanner.php            # Scanner for classes carrying the #[Test] attribute
├── Generator/
│   └── TestGenerator.php          # Test file generator
├── Scaffold/
│   └── TestScaffolder.php         # Creation of the initial Tests/ structure
├── Template/
│   ├── UnitTestTemplate.php
│   ├── FeatureTestTemplate.php
│   ├── DatabaseTestTemplate.php
│   ├── MiddlewareTestTemplate.php
│   └── ModelTestTemplate.php
└── Commands/
    ├── MakeTestCommand.php         # make:test
    ├── MakeTestAutoCommand.php     # make:test:auto
    ├── RunTestCommand.php          # run:test
    └── RunTestAllCommand.php       # run:test:all
```

---

## Base Classes

### TestCase

`Neo\Core\Testing\TestCase` is the parent class for all **unit** tests. It bootstraps the NeoPHP application and exposes the dependency injection container.

```php
use Neo\Core\Testing\TestCase;

class MyServiceTest extends TestCase
{
    public function test_calculation(): void
    {
        $service = $this->get(MyService::class);
        $this->assertSame(42, $service->calculate());
    }
}
```

**Available Methods:**

| Method | Description |
|---|---|
| `get(string $id): mixed` | Resolves a service from the DI container |
| `swap(string $id, mixed $value): void` | Replaces a service in the container (mock) |

The `App` instance is shared (static `static $app` property) between methods of the same test to avoid re-initializing the application for every test.

```php
class MyTest extends TestCase
{
    public function test_with_mock(): void
    {
        // Replace the real mailer with a fake one
        $this->swap(Mailer::class, new FakeMailer());

        $service = $this->get(MyService::class);
        $service->sendEmail('test@example.com');

        $this->assertTrue(true); // no exception = success
    }
}
```

---

### DatabaseTestCase

`Neo\Core\Testing\DatabaseTestCase` is specialized for tests that interact with the database. Each test runs inside a **transaction that is automatically rolled back** (`rollBack`) at the end, guaranteeing complete isolation without polluting the database.

```php
use Neo\Core\Testing\DatabaseTestCase;

class UserRepositoryTest extends DatabaseTestCase
{
    public function test_user_insertion(): void
    {
        $id = $this->insertFixture('users', [
            'name'  => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
        $this->assertIsInt((int) $id);
    }

    public function test_user_deletion(): void
    {
        $this->insertFixture('users', ['name' => 'Bob', 'email' => 'bob@example.com']);

        // After logical deletion
        $this->assertDatabaseMissing('users', ['email' => 'unknown@example.com']);
    }
}
```

**Available Methods:**

| Method | Description |
|---|---|
| `insertFixture(string $table, array $data): int\|string` | Inserts a row into the table and returns the last inserted ID |
| `fetchAll(string $table, string $where, array $bindings): array` | Retrieves all rows from a table |
| `assertDatabaseHas(string $table, array $data): void` | Checks that a row matching the criteria exists |
| `assertDatabaseMissing(string $table, array $data): void` | Checks that no row matches the criteria |
| `get(string $id): mixed` | Resolves a DI service |
| `swap(string $id, mixed $value): void` | Replaces a service (mock) |

The `$this->pdo` property directly exposes the active PDO connection if more complex queries are needed.

---

### FeatureTestCase

`Neo\Core\Testing\FeatureTestCase` allows testing the application's HTTP routes by sending **real requests** through the NeoPHP kernel. Responses are full `Response` objects.

```php
use Neo\Core\Testing\FeatureTestCase;

class ArticleControllerTest extends FeatureTestCase
{
    public function test_article_list(): void
    {
        $response = $this->get('/articles');

        $this->assertStatus(200, $response);
        $this->assertSeeText('My articles', $response);
    }

    public function test_article_creation(): void
    {
        $response = $this->post('/articles', [
            'title'   => 'My article',
            'content' => 'Test content',
        ]);

        $this->assertStatus(201, $response);
        $this->assertJsonKey('id', $response);
    }

    public function test_article_deletion(): void
    {
        $response = $this->delete('/articles/1', [
            'Authorization' => 'Bearer token123',
        ]);

        $this->assertStatus(200, $response);
    }

    public function test_article_update(): void
    {
        $response = $this->put('/articles/1', ['title' => 'New title']);

        $this->assertStatus(200, $response);
    }
}
```

**Available HTTP Methods:**

| Method | Signature |
|---|---|
| `get` | `get(string $uri, array $headers = []): Response` |
| `post` | `post(string $uri, array $body = [], array $headers = []): Response` |
| `put` | `put(string $uri, array $body = [], array $headers = []): Response` |
| `delete` | `delete(string $uri, array $headers = []): Response` |
| `request` | `request(string $method, string $uri, array $body, array $headers): Response` |

**Available Assertions:**

| Assertion | Description |
|---|---|
| `assertStatus(int $expected, Response $response)` | Checks the response's HTTP status code |
| `assertSeeText(string $text, Response $response)` | Checks that a text is present in the response |
| `assertJsonKey(string $key, Response $response)` | Checks that a key exists in the JSON response |

`FrameworkException` exceptions are caught and converted into responses with the appropriate HTTP code (500 by default).

---

### MiddlewareTestCase

`Neo\Core\Testing\MiddlewareTestCase` is dedicated to testing **middlewares**. It allows instantiating a middleware via the DI container and checking its behavior (pass or block).

```php
use Neo\Core\Testing\MiddlewareTestCase;

class AuthMiddlewareTest extends MiddlewareTestCase
{
    public function test_middleware_allows_logged_in_user(): void
    {
        // Simulate a logged-in user
        $this->swap(AuthService::class, new FakeAuthService(authenticated: true));

        $middleware = $this->makeMiddleware(AuthMiddleware::class);

        $this->assertMiddlewarePasses($middleware);
    }

    public function test_middleware_blocks_logged_out_user(): void
    {
        $this->swap(AuthService::class, new FakeAuthService(authenticated: false));

        $middleware = $this->makeMiddleware(AuthMiddleware::class);

        $this->assertMiddlewareBlocksWithCode($middleware, 401);
    }
}
```

**Available Methods:**

| Method | Description |
|---|---|
| `makeMiddleware(string $class, array $params = []): MiddlewareInterface` | Instantiates a middleware via the container |
| `assertMiddlewarePasses(MiddlewareInterface $m)` | Checks that `handle()` returns `true` |
| `assertMiddlewareBlocks(MiddlewareInterface $m)` | Checks that `handle()` returns `false` or throws a `FrameworkException` |
| `assertMiddlewareBlocksWithCode(MiddlewareInterface $m, int $code)` | Checks that the middleware throws a `FrameworkException` with the exact HTTP code |
| `get(string $id): mixed` | Resolves a DI service |
| `swap(string $id, mixed $value): void` | Replaces a service (mock) |

---

## The #[Test] Attribute and the TestType Enum

### The #[Test] Attribute

The `Neo\Core\Testing\Attribute\Test` attribute can be placed on a **class** or a **method** to tell the automatic generator how to create the corresponding test.

```php
use Neo\Core\Testing\Attribute\Test;

#[Test(type: 'unit')]
class MyService
{
    #[Test(cases: [['input' => 'foo', 'expected' => 'FOO']])]
    public function transform(string $input): string
    {
        return strtoupper($input);
    }

    #[Test(skip: true)]
    public function tooComplexMethod(): void
    {
        // this test will be ignored by the generator
    }
}
```

**Attribute Parameters:**

| Parameter | Type | Default | Description |
|---|---|---|---|
| `type` | `string` | `'auto'` | Test type: `unit`, `feature`, `database`, `middleware`, `auto` |
| `cases` | `array` | `[]` | Data sets for data providers |
| `route` | `?string` | `null` | HTTP route to call (feature tests) |
| `httpMethod` | `string` | `'GET'` | HTTP method (feature tests) |
| `dataset` | `array` | `[]` | Shared static data |
| `skip` | `bool` | `false` | Skips this class/method during generation |
| `extends` | `?string` | `null` | Custom parent class to extend |

### TestType Enum

`Neo\Core\Testing\Enum\TestType` determines which `TestCase` to use depending on the context.

| Value | Generated TestCase | Subfolder |
|---|---|---|
| `unit` | `TestCase` | `Unit/` |
| `feature` | `FeatureTestCase` | `Feature/` |
| `database` | `DatabaseTestCase` | `Database/` |
| `middleware` | `MiddlewareTestCase` | `Middleware/` |
| `auto` | Detected from the FQCN | (varies) |

Automatic detection (`auto`) inspects the class name:
- Contains `Repository` → `DatabaseTestCase`
- Contains `Controller` → `FeatureTestCase`
- Contains `Middleware` → `MiddlewareTestCase`
- Otherwise → `TestCase`

---

## Scanner and Automatic Generator

### TestScanner

`Neo\Core\Testing\Scanner\TestScanner` recursively walks the source folder (`src/MyProject/`) looking for PHP files carrying the `#[Test]` attribute. It returns a list of `TestClassContext`.

```php
$scanner = new TestScanner();
$contexts = $scanner->scan('/path/to/src/MyProject');

foreach ($contexts as $ctx) {
    echo $ctx->fqcn;       // Full class name
    echo $ctx->shortName;  // Short name
    echo $ctx->type->value; // 'unit', 'feature', etc.
}
```

### TestGenerator

`Neo\Core\Testing\Generator\TestGenerator` uses `TestScanner` to analyze the project and generates the corresponding test files based on the available templates.

```php
$generator = new TestGenerator($container);

$result = $generator->generate(
    force: false,      // Do not overwrite existing files
    onlyType: 'unit',  // Generate only unit tests
    dryRun: true,      // Simulate without writing
);

// $result = ['generated' => [...], 'skipped' => [...]]
```

The templates used vary depending on the type and context of the class:
- Class in a `Model` namespace (without `Repository` or `Controller`) → `ModelTestTemplate`
- Type `database` → `DatabaseTestTemplate`
- Type `feature` → `FeatureTestTemplate`
- Type `middleware` → `MiddlewareTestTemplate`
- Otherwise → `UnitTestTemplate`

---

## CLI Commands

### `make:test` — Generate a Test Manually

Creates a PHPUnit test file from a skeleton adapted to the chosen type.

```bash
php neo make:test UserTest --project=Blog --type=unit
php neo make:test ArticleControllerTest --project=Blog --type=feature
php neo make:test UserRepositoryTest --project=Blog --type=database --force
```

**Options:**

| Option | Description |
|---|---|
| `testName` (argument) | Name of the test class (`Test` suffix added automatically) |
| `--project` | Target project (folder in `src/`) |
| `--type` | Type: `unit`, `feature`, `database`, `middleware` |
| `--force` | Overwrites the file if it already exists |

File generated at: `src/MyProject/Tests/Unit/UserTest.php`

### `make:test:auto` — Automatic Generation from Attributes

Scans the project for classes annotated with `#[Test]` and generates the corresponding tests.

```bash
php neo make:test:auto --project=Blog
php neo make:test:auto --project=Blog --only=feature
php neo make:test:auto --project=Blog --dry-run
php neo make:test:auto --project=Blog --force
```

**Options:**

| Option | Description |
|---|---|
| `--project` | Target project |
| `--force` | Overwrites existing files |
| `--only` | Filters by type: `unit`, `feature`, `database`, `middleware` |
| `--dry-run` | Shows what would be generated without writing |

### `run:test` — Run a Targeted Test

Runs a specific PHPUnit test file with `--testdox`.

```bash
php neo run:test UserTest --project=Blog
php neo run:test UserTest --project=Blog --type=unit --filter=test_creation
```

**Options:**

| Option | Description |
|---|---|
| `testName` (argument) | Name of the test class |
| `--project` | Target project |
| `--type` | Restricts the search to the type's subfolder |
| `--filter` | PHPUnit filter on the method name |

### `run:test:all` — Run All of a Project's Tests

Runs the full suite via the project's `phpunit.xml`.

```bash
php neo run:test:all --project=Blog
php neo run:test:all --project=Blog --coverage
php neo run:test:all --project=Blog --format=html --stop-on-failure
```

**Options:**

| Option | Description |
|---|---|
| `--project` | Target project |
| `--format` | Output format: `console`, `html`, `both` |
| `--coverage` | Generates a coverage report (requires Xdebug or PCOV) |
| `--stop-on-failure` | Stops execution on the first failure |

HTML reports are generated in `src/MyProject/Storage/reports/`.

---

## Naming Conventions and Structure

The expected test structure in each project:

```
src/MyProject/
└── Tests/
    ├── phpunit.xml
    ├── Unit/
    │   └── MyServiceTest.php
    ├── Feature/
    │   └── ArticleControllerTest.php
    ├── Database/
    │   └── UserRepositoryTest.php
    └── Middleware/
        └── AuthMiddlewareTest.php
```

- The test class name must always end with `Test` (added automatically if absent).
- Namespaces follow the convention `Neo\Src\MyProject\Tests\{Type}`.
- Each test must extend the base class corresponding to its type.