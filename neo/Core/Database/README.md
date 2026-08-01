# Database & ORM

This module provides the entire data access layer of the NeoPHP framework: a complete Data Mapper ORM, a migration system, a form manager, and low-level database access.

---

## Summary

1. [General Architecture](#general-architecture)
2. [ORM — Object-Relational Mapper](#orm--object-relational-mapper)
   - [EntityManager](#entitymanager)
   - [UnitOfWork](#unitofwork)
   - [EntityRepository](#entityrepository)
   - [EntityPersister](#entitypersister)
   - [ObjectHydrator](#objecthydrator)
   - [ProxyFactory](#proxyfactory)
   - [QueryBuilder](#querybuilder)
   - [Pagination](#pagination)
   - [Collections](#collections)
3. [Mapping — PHP Attributes](#mapping--php-attributes)
   - [ClassMetaData and MetadataFactory](#classmetadata-and-metadatafactory)
   - [Available Attributes](#available-attributes)
   - [ORM Types](#orm-types)
   - [Platform](#platform)
4. [Migrations](#migrations)
   - [DatabaseIntrospector](#databaseintrospector)
   - [MigrationRunner](#migrationrunner)
   - [MigrationGenerator](#migrationgenerator)
   - [SchemaDiffer](#schemadiffer)
   - [SchemaTool](#schematool)
5. [Forms](#forms)
   - [FormFactory and FormBuilder](#formfactory-and-formbuilder)
   - [Form and FormField](#form-and-formfield)
   - [FormRenderer](#formrenderer)
6. [Database Access](#database-access)
   - [DatabaseManager](#databasemanager)
   - [DatabaseConnection](#databaseconnection)
7. [Seeding](#seeding)
   - [SeedInterface](#seedinterface)
   - [The #[Seeder] Attribute](#attribut-seeder)
   - [SeedManager](#seedmanager)
8. [CLI Commands](#cli-commands)
9. [Important Technical Notes](#important-technical-notes)

---

## General Architecture

```
Database/
├── ORM/
│   ├── Persistence/        # EntityManager, UnitOfWork, EntityPersister, ObjectHydrator, ProxyFactory, EntityRepository
│   ├── Mapping/            # ClassMetaData, MetadataFactory
│   │   └── Attribute/      # Entity, Table, Column, Id, GeneratedValue, relations, JoinColumn, JoinTable
│   ├── Query/              # QueryBuilder
│   ├── Collection/         # Collection, LazyCollection
│   ├── Platform/           # AbstractPlatform, MySQLPlatform
│   ├── Schema/             # SchemaTool
│   └── Type/               # Type, TypeRegistry
├── Migration/
│   ├── Runner/             # MigrationRunner
│   ├── Generator/          # MigrationGenerator
│   ├── Interface/          # MigrationInterface
│   ├── SchemaDiffer.php
│   └── MigrationSchemaSnapshot.php
├── Form/
│   ├── Field/              # FieldType, FormField
│   ├── Form.php
│   ├── FormBuilder.php
│   ├── FormFactory.php
│   ├── FormRenderer.php
│   └── PropertyAccessor.php
├── Access/
│   ├── Connection/         # DatabaseConnection
│   └── Introspector/       # DatabaseIntrospector
│       └── Metadata/       # ColumnMetadata, ForeignKeyMetadata, IndexMetadata
├── Pagination/
│   ├── Paginator.php
│   └── Extension/          # PaginationViewExtension (paginator_links Twig function)
├── Commands/               # CLI commands
└── DatabaseManager.php
```

---

## ORM — Object-Relational Mapper

NeoPHP's ORM follows the **Data Mapper** pattern: entities are plain PHP objects (POPO) with no mandatory inheritance. Persistence is handled entirely outside of the entity.

### EntityManager

**File:** `ORM/Persistence/EntityManager.php`

Main entry point of the ORM. It orchestrates every persistence operation.

| Method | Description |
|---------|-------------|
| `persist(object $entity)` | Registers an entity for insertion or tracking |
| `remove(object $entity)` | Marks an entity for deletion |
| `flush()` | Executes every pending operation against the database |
| `find(string $class, mixed $id)` | Finds an entity by its identifier |
| `getReference(string $class, mixed $id)` | Returns a proxy without loading the data |
| `getRepository(string $class)` | Returns the repository associated with an entity |
| `contains(object $entity)` | Checks whether an entity is managed |
| `clear()` | Clears the identity map and the unit of work |
| `wrapInTransaction(callable $cb)` | Runs a callback inside a transaction |

```php
// Injection into a controller or service
use Neo\Core\Database\ORM\Persistence\EntityManager;

$em = $container->get(EntityManager::class);

// Create and persist an entity
$user = new User();
$user->setName('Alice')->setEmail('alice@example.com');

$em->persist($user);
$em->flush(); // INSERT INTO users ...

// Retrieve an entity by its ID
$user = $em->find(User::class, 1);

// Modify and save
$user->setName('Alice Updated');
$em->flush(); // UPDATE users SET name = ? WHERE id = ?

// Delete
$em->remove($user);
$em->flush(); // DELETE FROM users WHERE id = ?

// Explicit transaction
$result = $em->wrapInTransaction(function (EntityManager $em) {
    $order = new Order();
    $em->persist($order);
    return $order;
});
```

### UnitOfWork

**File:** `ORM/Persistence/UnitOfWork.php`

Implements the **Unit of Work** pattern. It maintains an **identity map** and manages entity states.

**Entity states:**

| Constant | Value | Description |
|-----------|--------|-------------|
| `STATE_MANAGED` | 1 | Tracked entity, will be synchronized on flush |
| `STATE_NEW` | 2 | New entity, not yet persisted |
| `STATE_DETACHED` | 3 | Entity detached from the UoW |
| `STATE_REMOVED` | 4 | Entity marked for deletion |

**Internal behavior:**

- The identity map `$identityMap[className][idHash] = entity` guarantees instance uniqueness.
- At `flush()` time, `computeChangeSets()` detects modified fields by comparing against `$originalEntityData`.
- Insertion order is determined by a topological sort (`getCommitOrder()`) based on dependencies between entities (foreign keys).
- PDO transactions are handled automatically: if no transaction is active, the UoW opens one.

```php
// Access the UoW through the EntityManager
$uow = $em->getUnitOfWork();

// Check whether an entity is managed
$uow->isManaged($entity); // bool

// Force-register an entity as managed
$uow->registerManaged($entity, $id, $originalData);
```

### EntityRepository

**File:** `ORM/Persistence/EntityRepository.php`

Generic repository class, extensible per entity.

```php
$repo = $em->getRepository(User::class);

// Find by ID
$user = $repo->find(1);

// Find all
$users = $repo->findAll();

// Find by criteria
$users = $repo->findBy(
    criteria: ['role' => 'admin'],
    orderBy: ['name' => 'ASC'],
    limit: 10,
    offset: 0
);

// Find a single one
$user = $repo->findOneBy(['email' => 'alice@example.com']);

// Count
$count = $repo->count(['active' => true]);

// Pagination
$paginator = $repo->paginate(page: 1, perPage: 20, criteria: ['role' => 'admin'], orderBy: ['name' => 'ASC']);
```

See the [Pagination](#pagination) section for the full API of the returned object.

**Custom repository:**

```php
// src/MyProject/Database/Repository/UserRepository.php
namespace Neo\Src\MyProject\Database\Repository;

use Neo\Core\Database\ORM\Persistence\EntityRepository;
use Neo\Src\MyProject\Database\Entity\User;

/**
 * @extends EntityRepository<User>
 */
final class UserRepository extends EntityRepository
{
    public function findAdmins(): array
    {
        return $this->findBy(['role' => 'admin'], ['name' => 'ASC']);
    }

    public function findByEmailDomain(string $domain): array
    {
        // Access to the persister for custom queries
        return $this->persister()->loadAll(['email_domain' => $domain]);
    }
}
```

### EntityPersister

**File:** `ORM/Persistence/EntityPersister.php`

Executes the actual SQL operations (INSERT, UPDATE, DELETE, SELECT) for a given entity class.

- **`insert(object $entity)`** — builds and executes an INSERT, returns the last inserted ID.
- **`update(object $entity, array $changeSet)`** — generates an UPDATE for the modified fields only.
- **`delete(object $entity)`** — executes a DELETE by primary key.
- **`loadById(array $criteria)`** — SELECT with hydration.
- **`loadAll(array $criteria, array $orderBy, ?int $limit, ?int $offset)`** — SELECT with filters, sorting and pagination.
- **`loadCollection(array $assoc, mixed $ownerId)`** — loading of OneToMany and ManyToMany associations.

### ObjectHydrator

**File:** `ORM/Persistence/ObjectHydrator.php`

Converts an SQL row (associative array) into a PHP object. For each row:

1. Converts each column value via the `TypeRegistry`.
2. For `ToOne` associations (owning side), creates a lazy proxy.
3. For `ToMany` associations, creates a `LazyCollection`.
4. Registers the entity in the UoW via `registerManaged()`.

### ProxyFactory

**File:** `ORM/Persistence/ProxyFactory.php`

Uses the PHP 8.4 **Lazy Ghosts** feature (`ReflectionClass::newLazyGhost`) to create transparent proxies.

```php
// A proxy is a "ghost" object: its data is loaded
// only on the first access to a non-ID property.
$proxy = $em->getReference(User::class, 42);
// No SQL query here

echo $proxy->getName(); // Triggers lazy loading
// SELECT * FROM users WHERE id = 42
```

### QueryBuilder

**File:** `ORM/Query/QueryBuilder.php`

Fluent SQL query builder, independent of the ORM, operating directly on `DatabaseManager`.

```php
use Neo\Core\Database\Query\QueryBuilder;

$qb = QueryBuilder::for($db, 'users');

// SELECT with join and pagination
$rows = $qb
    ->select('u.id', 'u.name', 'p.title')
    ->join('posts p', 'p.user_id', '=', 'u.id')
    ->where('u.active', '=', true)
    ->orWhere('u.role', '=', 'admin')
    ->whereIn('u.id', [1, 2, 3])
    ->orderBy('u.name', 'ASC')
    ->limit(20)
    ->offset(0)
    ->get();

// Count
$count = QueryBuilder::for($db, 'users')
    ->where('role', '=', 'admin')
    ->count();

// Insert and retrieve the ID
$id = QueryBuilder::for($db, 'users')
    ->insertGetId(['name' => 'Bob', 'email' => 'bob@example.com']);

// Update
QueryBuilder::for($db, 'users')
    ->where('id', '=', 5)
    ->update(['name' => 'Robert']);

// Delete
QueryBuilder::for($db, 'users')
    ->where('active', '=', false)
    ->delete();

// Inspect the generated SQL
echo $qb->toSql();
```

### Pagination

**Files:** `Database/Pagination/Paginator.php`, `Database/Pagination/Extension/PaginationViewExtension.php`

`Paginator` is a standalone container that holds a page's items and its associated metadata (current page, total count, etc.). It is produced either by `QueryBuilder::paginate()` or by `EntityRepository::paginate()`.

**From the QueryBuilder:**

```php
$paginator = QueryBuilder::for($db, 'posts')
    ->where('published', '=', true)
    ->orderBy('created_at', 'DESC')
    ->paginate(page: 2, perPage: 10);
```

`paginate()` clones the builder to run the `COUNT()` separately — the original builder (columns, `LIMIT`/`OFFSET`) is not altered and can still be used afterward.

**From an EntityRepository:**

```php
$paginator = $repo->paginate(page: 1, perPage: 20, criteria: ['role' => 'admin'], orderBy: ['name' => 'ASC']);
```

**`Paginator` API:**

| Method | Description |
|---------|-------------|
| `getItems()` | Items of the current page (`list<T>`) |
| `getCurrentPage()` | Current page number |
| `getPerPage()` | Number of items per page |
| `getTotalItems()` | Total number of items (across all pages) |
| `getTotalPages()` | Total number of pages |
| `hasNextPage()` / `hasPreviousPage()` | `bool` |
| `getNextPage()` / `getPreviousPage()` | Next/previous page number, or `null` |
| `getLinks(int $onEachSide = 2)` | Sliding window of page numbers for navigation, e.g. `[1, null, 4, 5, 6, null, 12]` (`null` = `...`) |

`Paginator` also implements `\IteratorAggregate` and `\Countable`: you can iterate over it directly (`foreach ($paginator as $item)`) or call `count($paginator)`.

**Rendering in a Twig template:**

The `paginator_links()` function generates the HTML navigation. No text is hardcoded in the extension: labels are parameters, with neutral symbols by default.

```twig
{# Basic navigation, default symbols (« / » / …) #}
{{ paginator_links(paginator) }}

{# Explicit base URL (otherwise the current request path is used) #}
{{ paginator_links(paginator, '/posts') }}

{# Custom / translated labels via the i18n module #}
{{ paginator_links(
    paginator,
    prevLabel: translate('pagination.prev'),
    nextLabel: translate('pagination.next'),
    gapLabel: translate('pagination.gap')
) }}
```

The navigation adds a `?page=N` parameter (or `&page=N` if the base URL already has a query string) to each link, and is not rendered if the paginator only has a single page.

### Collections

**Files:** `ORM/Collection/Collection.php`, `ORM/Collection/LazyCollection.php`

`Collection` is a typed container with standard methods (`add`, `remove`, `contains`, `count`, `toArray`, etc.).

`LazyCollection` extends `Collection`: it receives a `Closure` instead of data and only loads its items on first use.

```php
// Inside an entity, after hydration
$user = $em->find(User::class, 1);
$posts = $user->getPosts(); // Uninitialized LazyCollection

foreach ($posts as $post) {
    // First access: SELECT * FROM posts WHERE user_id = 1
    echo $post->getTitle();
}
```

---

## Mapping — PHP Attributes

### ClassMetaData and MetadataFactory

**Files:** `ORM/Mapping/ClassMetaData.php`, `ORM/Mapping/MetadataFactory.php`

`MetadataFactory` reads the PHP attributes of an entity class and builds a cached `ClassMetaData` object.

**Automatic rules:**
- If no `#[Table]` is specified, the table name is inferred: `UserProfile` → `user_profiles`.
- Column types are inferred from PHP types (`int` → `integer`, `bool` → `boolean`, `array` → `json`, etc.).
- Default join columns for `ManyToOne` are `{field}_id`.

### Available Attributes

#### `#[Entity]`

```php
use Neo\Core\Database\ORM\Mapping\Attribute\Entity;

#[Entity(repositoryClass: UserRepository::class, readOnly: false)]
final class User { }
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `repositoryClass` | `?string` | FQCN of the custom repository |
| `readOnly` | `bool` | Read-only entity |

#### `#[Table]`

```php
use Neo\Core\Database\ORM\Mapping\Attribute\Table;

#[Table(name: 'app_users')]
final class User { }
```

#### `#[Column]`

```php
use Neo\Core\Database\ORM\Mapping\Attribute\Column;

#[Column(type: 'string', name: 'full_name', length: 100, nullable: false, unique: true)]
private string $name;
```

| Parameter | Type | Default | Description |
|-----------|------|--------|-------------|
| `type` | `string` | `'string'` | ORM type (see TypeRegistry) |
| `name` | `?string` | property name | SQL column name |
| `length` | `?int` | null | Length (VARCHAR) |
| `nullable` | `bool` | `false` | Nullable column |
| `unique` | `bool` | `false` | UNIQUE constraint |
| `unsigned` | `bool` | `false` | Unsigned integer |
| `default` | `mixed` | null | Default value |
| `precision` | `?int` | null | Precision (DECIMAL) |
| `scale` | `?int` | null | Scale (DECIMAL) |
| `columnDefinition` | `?string` | null | Raw SQL column definition |

#### `#[Id]` and `#[GeneratedValue]`

```php
use Neo\Core\Database\ORM\Mapping\Attribute\Id;
use Neo\Core\Database\ORM\Mapping\Attribute\GeneratedValue;

#[Id]
#[GeneratedValue]  // strategy: 'AUTO' by default
#[Column(type: 'integer', unsigned: true)]
private ?int $id = null;
```

#### `#[ManyToOne]`

```php
use Neo\Core\Database\ORM\Mapping\Attribute\ManyToOne;
use Neo\Core\Database\ORM\Mapping\Attribute\JoinColumn;

#[ManyToOne(targetEntity: Category::class, inversedBy: 'products', cascade: ['persist'])]
#[JoinColumn(name: 'category_id', nullable: false, onDelete: 'CASCADE')]
private ?Category $category = null;
```

| Parameter | Description |
|-----------|-------------|
| `targetEntity` | FQCN of the target entity |
| `inversedBy` | Name of the inverse field on the target |
| `fetch` | `'LAZY'` (default) |
| `cascade` | `['persist', 'remove', 'all']` |

#### `#[OneToMany]`

```php
use Neo\Core\Database\ORM\Mapping\Attribute\OneToMany;
use Neo\Core\Database\ORM\Collection\Collection;

/** @var Collection<Post> */
#[OneToMany(targetEntity: Post::class, mappedBy: 'author', cascade: ['persist'], orphanRemoval: true)]
private Collection $posts;
```

#### `#[OneToOne]`

```php
// Owning side (holds the FK)
#[OneToOne(targetEntity: Profile::class, inversedBy: 'user')]
#[JoinColumn(name: 'profile_id', unique: true, nullable: true)]
private ?Profile $profile = null;

// Inverse side
#[OneToOne(targetEntity: User::class, mappedBy: 'profile')]
private ?User $user = null;
```

#### `#[ManyToMany]`

```php
use Neo\Core\Database\ORM\Mapping\Attribute\ManyToMany;
use Neo\Core\Database\ORM\Mapping\Attribute\JoinTable;
use Neo\Core\Database\ORM\Mapping\Attribute\JoinColumn;

// Owning side
/** @var Collection<Tag> */
#[ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
#[JoinTable(
    name: 'article_tag',
    joinColumns: [new JoinColumn(name: 'article_id')],
    inverseJoinColumns: [new JoinColumn(name: 'tag_id')]
)]
private Collection $tags;

// Inverse side
/** @var Collection<Article> */
#[ManyToMany(targetEntity: Article::class, mappedBy: 'tags')]
private Collection $articles;
```

### Complete entity example

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Database\Entity;

use Neo\Core\Database\ORM\Collection\Collection;
use Neo\Core\Database\ORM\Mapping\Attribute\Column;
use Neo\Core\Database\ORM\Mapping\Attribute\Entity;
use Neo\Core\Database\ORM\Mapping\Attribute\GeneratedValue;
use Neo\Core\Database\ORM\Mapping\Attribute\Id;
use Neo\Core\Database\ORM\Mapping\Attribute\ManyToOne;
use Neo\Core\Database\ORM\Mapping\Attribute\OneToMany;
use Neo\Core\Database\ORM\Mapping\Attribute\Table;
use Neo\Src\Blog\Database\Repository\PostRepository;

#[Entity(repositoryClass: PostRepository::class)]
#[Table(name: 'posts')]
final class Post
{
    #[Id]
    #[GeneratedValue]
    #[Column(type: 'integer', unsigned: true)]
    private ?int $id = null;

    #[Column(type: 'string', length: 255)]
    private string $title;

    #[Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    private ?User $author = null;

    /** @var Collection<Comment> */
    #[OneToMany(targetEntity: Comment::class, mappedBy: 'post', orphanRemoval: true)]
    private Collection $comments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->comments  = new Collection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    // ...
}
```

### ORM Types

| ORM name | PHP type | MySQL SQL |
|---------|----------|-----------|
| `string` | `string` | `VARCHAR(255)` |
| `text` | `string` | `TEXT` |
| `integer` | `int` | `INT` |
| `smallint` | `int` | `SMALLINT` |
| `bigint` | `int` | `BIGINT` |
| `boolean` | `bool` | `TINYINT(1)` |
| `float` | `float` | `FLOAT` |
| `decimal` | `string` | `DECIMAL` |
| `datetime` | `\DateTime` | `DATETIME` |
| `date` | `\DateTime` | `DATE` |
| `time` | `\DateTime` | `TIME` |
| `json` | `array` | `JSON` / `TEXT` |

**Registering a custom type:**

```php
use Neo\Core\Database\ORM\Type\TypeRegistry;

TypeRegistry::register(new MyCustomType());
```

### Platform

`AbstractPlatform` defines the contract for SQL generation specific to a DBMS. `MySQLPlatform` is the default implementation.

```php
$platform = $em->getPlatform();
$platform->quoteIdentifier('my_table'); // `my_table`
$platform->getName();                   // 'mysql'
```

---

## Migrations

### DatabaseIntrospector

**File:** `Access/Introspector/DatabaseIntrospector.php`

Reads the actual structure of the database (tables, columns, foreign keys, indexes) via `information_schema` and `SHOW`/`DESCRIBE`. This is the source of truth used by the migration pipeline to compare the current state of the database against the desired state (ORM entities).

Results are returned as typed DTOs, in `Access/Introspector/Metadata/`:

| Method | Returns |
|---------|--------|
| `getTables()` | `list<string>` |
| `getColumns(string $table)` | `list<ColumnMetadata>` |
| `getForeignKeys(string $table)` | `list<ForeignKeyMetadata>` |
| `getIndexes(string $table)` | `list<IndexMetadata>` |

```php
$introspector = new DatabaseIntrospector($container);
// Or on a specific connection:
$introspector = DatabaseIntrospector::on($container, 'secondary');

foreach ($introspector->getColumns('users') as $column) {
    echo $column->getName() . ' : ' . $column->getType();
    if ($column->isNullable()) {
        echo ' (nullable)';
    }
}

foreach ($introspector->getForeignKeys('posts') as $fk) {
    echo $fk->getColumn() . ' → ' . $fk->getReferencedTable() . '.' . $fk->getReferencedColumn();
}

foreach ($introspector->getIndexes('posts') as $index) {
    echo $index->getName() . ' : ' . implode(', ', $index->getColumns());
    echo $index->isUnique() ? ' (unique)' : '';
}
```

Each DTO also exposes a `toArray()` method that rebuilds the original `array{...}` shape. It is used at the boundary with components that still work with generic arrays — notably `MigrationSchemaSnapshot`, which serializes the schema to JSON to store it in the database (`neo_schema_snapshots`), and `MigrationGenerator::generate()`, which reuses the same SQL-building logic as `generateDiff()`. `SchemaDiffer` continues to work exclusively with arrays, since it compares both the current schema (introspected) and the desired schema (from `SchemaTool`, on the ORM side) or an old schema read back from a JSON snapshot.

### MigrationRunner

**File:** `Migration/Runner/MigrationRunner.php`

Handles running and rolling back migrations. Maintains the `neo_migrations` table in the database.

```php
$runner = new MigrationRunner($db, 'default');

// List pending migrations
$pending = $runner->getPending('/path/to/migrations');

// Run every pending migration
$runner->run('/path/to/migrations');

// Dry run (list without running)
$runner->run('/path/to/migrations', dryRun: true);

// Rollback the last batch
$runner->rollback('/path/to/migrations');
```

### MigrationGenerator

**File:** `Migration/Generator/MigrationGenerator.php`

Generates PHP migration files from a schema diff.

Each generated migration follows the `MigrationVersion_YYYYMMDD_HHmmss.php` format and contains:
- `up(DatabaseManager $db)` — applies the changes.
- `down(DatabaseManager $db)` — reverts the changes.
- `tableExists()` and `columnExists()` helpers for idempotent operations.

**Example of a generated migration:**

```php
<?php
declare(strict_types=1);

/**
 * Migration: add_posts_table
 * Generated: 2026-07-28 10:00:00
 */
final class MigrationVersion_20260728_100000
{
    public function up(\Neo\Core\Database\DatabaseManager $db): void
    {
        if (!$this->tableExists($db, 'posts')) {
            $db->execute('CREATE TABLE IF NOT EXISTS `posts` (
                `id` int NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `content` text,
                `created_at` datetime NOT NULL,
                `author_id` int,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    }

    public function down(\Neo\Core\Database\DatabaseManager $db): void
    {
        if ($this->tableExists($db, 'posts')) {
            $db->execute('DROP TABLE IF EXISTS `posts`');
        }
    }

    private function tableExists(\Neo\Core\Database\DatabaseManager $db, string $table): bool
    {
        $row = $db->fetch(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $table]
        );
        return $row !== null;
    }

    private function columnExists(\Neo\Core\Database\DatabaseManager $db, string $table, string $column): bool
    {
        $row = $db->fetch(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column',
            ['table' => $table, 'column' => $column]
        );
        return $row !== null;
    }
}
```

### SchemaDiffer

**File:** `Migration/SchemaDiffer.php`

Compares two schemas and produces a structured diff:

```php
$differ = new SchemaDiffer();
$diff = $differ->diff($previousSchema, $currentSchema);

// $diff contains:
// - tablesToCreate: new tables
// - tablesToDrop:   removed tables
// - tableChanges:   changes per table (added, removed, modified)

// Detect whether the diff is empty
$differ->isEmpty($diff); // bool

// Table rename candidates (by column signature)
$candidates = $differ->findTableRenameCandidates($tablesToCreate, $tablesToDrop);

// Column rename candidates
$candidates = $differ->findColumnRenameCandidates($removed, $added);

// Risky NOT NULL changes
$risks = $differ->findRiskyNotNullChanges($tablesToCreate, $tableChanges);
```

### SchemaTool

**File:** `ORM/Schema/SchemaTool.php`

Translates ORM metadata into a database schema.

```php
$schemaTool = new SchemaTool($em);

// Get the desired schema from entities
$schema = $schemaTool->getSchema([User::class, Post::class, Comment::class]);

// Get foreign keys
$fks = $schemaTool->getForeignKeys([User::class, Post::class]);

// Get indexes
$indexes = $schemaTool->getIndexes([User::class]);
```

---

## Forms

### FormFactory and FormBuilder

**Files:** `Form/FormFactory.php`, `Form/FormBuilder.php`

```php
$factory = $container->get(FormFactory::class);

// Simple form
$builder = $factory->create('register');
$builder
    ->add('username', 'text', ['required' => true, 'maxLength' => 50])
    ->add('email', 'email', ['required' => true])
    ->add('password', 'password', ['required' => true, 'minLength' => 8])
    ->add('role', 'select', ['choices' => ['user' => 'User', 'admin' => 'Administrator']])
    ->add('newsletter', 'checkbox', ['label' => 'Subscribe to the newsletter']);

$form = $builder->getForm();

// Form bound to an entity
$user = $em->find(User::class, 1);
$builder = $factory->createFor($user, 'edit_user');
$builder->add('name', 'text', ['required' => true]);

$form = $builder->getForm(); // Values are pre-filled from $user
```

**Options available for `add()`:**

| Option | Type | Description |
|--------|------|-------------|
| `label` | `string` | Field label (auto-generated if absent) |
| `required` | `bool` | Adds the `NotBlank` constraint |
| `requiredMessage` | `string` | Validation message |
| `minLength` | `int` | Minimum length |
| `maxLength` | `int` | Maximum length |
| `constraints` | `array` | Additional validation constraints |
| `mapped` | `bool` | Bind to the entity field (default: `true`) |
| `choices` | `array` | Options for `select` |
| `attr` | `array` | Additional HTML attributes |
| `placeholder` | `string` | HTML placeholder |

### Form and FormField

**Files:** `Form/Form.php`, `Form/Field/FormField.php`

```php
// Handle a submission
$form->handleRequest($_POST);

if ($form->isSubmitted() && $form->isValid()) {
    // Data is automatically mapped onto the bound entity
    $user = $form->getEntity();
    $em->persist($user);
    $em->flush();
} else {
    // Access errors
    $errors = $form->getErrors();
    // ['email' => ['This field is required.'], ...]
}

// Access raw data
$data = $form->getData();
// ['username' => 'Alice', 'email' => 'alice@example.com', ...]
```

**Field types (`FieldType`):**

| Value | HTML type |
|--------|-----------|
| `Text` | `text` |
| `Textarea` | `textarea` |
| `Email` | `email` |
| `Password` | `password` |
| `Number` | `number` |
| `Hidden` | `hidden` |
| `Checkbox` | `checkbox` |
| `Select` | `select` |
| `Date` | `date` |
| `DateTime` | `datetime-local` |

### FormRenderer

**File:** `Form/FormRenderer.php`

```php
$renderer = new FormRenderer();

// Full form rendering
$html = $renderer->render($form, action: '/register', method: 'POST', attributes: ['class' => 'form-card']);

// Partial rendering
$html  = $renderer->start($form, '/register');
$html .= $renderer->field($form->getField('email'));
$html .= $renderer->field($form->getField('password'));
$html .= $renderer->end();
```

The rendering produces structured HTML:

```html
<form action="/register" method="POST">
   <input type="hidden" name="_csrf_token" value="abc123">

   <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="" required="required">
      <ul class="form-errors">
         <li>This field is required.</li>
      </ul>
   </div>
</form>
```

---

## Database Access

### DatabaseManager

**File:** `DatabaseManager.php`

Low-level interface for PDO queries.

```php
$db = $container->get(DatabaseManager::class);

// Query returning a single row
$row = $db->fetch('SELECT * FROM users WHERE id = :id', ['id' => 1]);

// Query returning multiple rows
$rows = $db->fetchAll('SELECT * FROM users WHERE role = ?', ['admin']);

// Execute with no result (INSERT, UPDATE, DELETE)
$db->execute('UPDATE users SET active = 1 WHERE id = ?', [42]);

// Query returning a PDOStatement object
$stmt = $db->query('SELECT COUNT(*) FROM users');

// Last inserted ID
$id = $db->lastInsertId();

// Specific connection (multi-connection)
$dbSecondary = DatabaseManager::on('secondary');
```

### DatabaseConnection

**File:** `Access/Connection/DatabaseConnection.php`

PDO connection manager. Supports multiple named connections.

```php
// Default connection (managed automatically by the framework)
$pdo = DatabaseConnection::getPdo();

// Named connection
$pdo = DatabaseConnection::connectTo('reporting');

// Check connection status
DatabaseConnection::isConnected();          // default connection
DatabaseConnection::isConnected('reporting'); // named connection

// Names of active connections
$names = DatabaseConnection::getConnectionNames();
```

**`database.config.php` configuration:**

```php
return [
    'enabled' => true,
    'use' => 'default',
    'connections' => [
        'default' => [
            'driver'  => 'mysql',
            'host'    => 'localhost',
            'port'    => 3306,
            'dbname'  => 'myapp',
            'user'    => 'root',
            'pass'    => 'secret',
            'charset' => 'utf8mb4',
        ],
        'secondary' => [
            'driver'  => 'mysql',
            'host'    => 'replica.example.com',
            'port'    => 3306,
            'dbname'  => 'myapp_ro',
            'user'    => 'reader',
            'pass'    => 'secret',
            'charset' => 'utf8mb4',
        ],
    ],
];
```

**Persistent connections**

Each connection can enable `PDO::ATTR_PERSISTENT` via the `persistent` option:

```php
'connections' => [
    'default' => [
        // ...
        'persistent' => true,
    ],
],
```

On a PHP-FPM server with many short-lived requests, this avoids reopening a TCP connection/authentication on every request: the FPM worker reuses the same PDO connection from one request to the next.

> ⚠️ **Warning**: a persistent connection outlives the request that opened it. If a transaction is started (`beginTransaction()`) and is never explicitly ended with a `commit()` or a `rollback()` — particularly in the case of an unhandled exception — the state can leak into the next request handled by the same worker, with undefined behavior depending on the driver. Only enable `persistent` if the application code guarantees that every transaction is systematically closed (including on error paths).

---

## Seeding

The Seeder module allows populating the database with reference or demo data. It is structured around an interface, a configuration attribute, and a `SeedManager`.

```
Database/Seeder/
├── SeedManager.php                         # Discovery, filtering and execution of seeders
├── Interface/
│   └── SeedInterface.php                  # Contract for a seeder
├── Attribute/
│   └── Seeder.php                         # Configuration attribute
└── Commands/
    ├── DatabaseMakeSeedCommand.php         # Seeder generator
    └── DatabaseRunSeedCommand.php          # Running seeders
```

### SeedInterface

Every seeder must implement `SeedInterface`:

```php
namespace Neo\Core\Database\Seeder\Interface;

use Neo\Core\Database\ORM\Persistence\EntityManager;

interface SeedInterface
{
    public function run(EntityManager $entityManager): void;
}
```

The `run()` method directly receives the `EntityManager`, which allows persisting entities without manually instantiating the ORM.

### The `#[Seeder]` Attribute

The `#[Seeder]` attribute is placed on the class and configures two parameters:

```php
use Neo\Core\Database\Seeder\Attribute\Seeder;

#[Seeder(order: 10, group: 'reference')]
final class CountrySeeder implements SeedInterface { ... }
```

| Parameter | Type | Default | Description |
|-----------|------|--------|-------------|
| `order` | `int` | `0` | Execution order (ascending). Seeders with no dependencies can keep `0`. |
| `group` | `string` | `'reference'` | Seeder group. Conventional values: `'reference'` (stable data, always run) and `'demo'` (development/test data). |

A seeder without the `#[Seeder]` attribute is ignored by the `SeedManager`.

### SeedManager

`SeedManager` orchestrates discovery, filtering, and execution of seeders.

**Discovery** — `discover(string $directory, string $namespace): array`

Recursively scans a directory, loads the PHP classes found, checks for the presence of the `#[Seeder]` attribute and the `SeedInterface` interface, then returns the list sorted by ascending `order`:

```php
$manager = new SeedManager($container);

$seeders = $manager->discover(
    '/var/www/app/src/Blog/Database/Seeder',
    'Neo\Src\Blog\Database\Seeder'
);
// [['class' => 'Neo\Src\Blog\Database\Seeder\CountrySeeder', 'order' => 10, 'group' => 'reference'], ...]
```

**Filtering** — `filterByGroup(array $seeders, ?string $group, bool $includeDev): array`

| Call | Result |
|-------|----------|
| `filterByGroup($seeders, null, false)` | Only the `'reference'` group |
| `filterByGroup($seeders, null, true)` | All groups |
| `filterByGroup($seeders, 'demo', false)` | Only the `'demo'` group |

**Execution** — `run(array $seeders): list<string>`

Resolves each seeder through the DI container, calls `run($em)`, and returns the list of executed FQCNs.

### CLI Commands

#### `database:make:seed`

Generates a seeder file inside `src/<Project>/Database/Seeder/`.

```bash
php bin/neo database:make:seed CountrySeeder --project=Blog
php bin/neo database:make:seed CountrySeeder --project=Blog --order=10 --group=reference
php bin/neo database:make:seed DemoPostSeeder --project=Blog --order=50 --group=demo
php bin/neo database:make:seed CountrySeeder --project=Blog --force   # Overwrites if it exists
```

Options:

| Option | Description |
|--------|-------------|
| `--project` | Target project (required) |
| `--order` | Execution order (default: `0`) |
| `--group` | Group (default: `'reference'`) |
| `--force` | Overwrites the file without confirmation |

The name is automatically converted to PascalCase and suffixed with `Seeder` if it isn't already.

#### `database:run:seed`

Runs a project's seeders.

```bash
# Run only the 'reference' seeders (default behavior)
php bin/neo database:run:seed --project=Blog

# Include development seeders (all groups)
php bin/neo database:run:seed --project=Blog --dev

# Run only a specific group
php bin/neo database:run:seed --project=Blog --group=demo

# Preview without running
php bin/neo database:run:seed --project=Blog --dry-run
```

Options:

| Option | Description |
|--------|-------------|
| `--project` | Target project (required) |
| `--group` | Filter on a specific group |
| `--dev` | Includes every group (including `demo`) |
| `--dry-run` | Lists the seeders that would run, without running them |

The command displays the ordered list of seeders before execution and asks for interactive confirmation.

### Complete example

```php
<?php
declare(strict_types=1);

namespace Neo\Src\Blog\Database\Seeder;

use Neo\Core\Database\ORM\Persistence\EntityManager;
use Neo\Core\Database\Seeder\Attribute\Seeder;
use Neo\Core\Database\Seeder\Interface\SeedInterface;
use Neo\Src\Blog\Database\Entity\Country;

#[Seeder(order: 10, group: 'reference')]
final class CountrySeeder implements SeedInterface
{
    public function run(EntityManager $entityManager): void
    {
        $countries = [
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'ES', 'name' => 'Spain'],
        ];

        foreach ($countries as $data) {
            $country = new Country();
            $country->setCode($data['code']);
            $country->setName($data['name']);
            $entityManager->persist($country);
        }

        $entityManager->flush();
    }
}
```

Typical workflow:

```bash
# Generate a seeder
php bin/neo database:make:seed CountrySeeder --project=Blog --order=10

# Preview
php bin/neo database:run:seed --project=Blog --dry-run

# Run
php bin/neo database:run:seed --project=Blog

# Demo data only
php bin/neo database:run:seed --project=Blog --group=demo
```

---

## CLI Commands

| Command | Description |
|----------|-------------|
| `make:entity` | Interactively generates an entity and its repository |
| `database:create` | Creates the database defined in the configuration |
| `database:orm:diff` | Compares entities against the database, generates a migration |
| `database:migration:migrate` | Runs every pending migration |
| `database:migration:rollback` | Rolls back the last migration batch |
| `database:migration:status` | Shows the status of migrations |
| `database:make:seed` | Generates a seeder in the target project |
| `database:run:seed` | Runs a project's seeders |

### `make:entity`

```bash
php neo make:entity --project=Blog
# The tool asks interactive questions:
# - Entity name
# - Fields (type, nullable, length)
# - Relations (ManyToOne, OneToMany, ManyToMany, OneToOne)
# - Generates the entity and its repository
```

### `database:create`

```bash
php neo database:create --project=Blog
# Reads database.config.php and creates the database
```

### `database:orm:diff`

```bash
# Compare entities against the database and generate a migration
php neo database:orm:diff --project=Blog --name=add_posts_table

# Dry run (preview without writing)
php neo database:orm:diff --project=Blog --name=test --dry-run

# Use a specific connection
php neo database:orm:diff --project=Blog --name=secondary_update --connection=secondary
```

### `database:migration:migrate`

```bash
# Run pending migrations
php neo database:migration:migrate --project=Blog

# List without running
php neo database:migration:migrate --project=Blog --dry-run
```

### `database:migration:rollback`

```bash
php neo database:migration:rollback --project=Blog
```

### `database:migration:status`

```bash
php neo database:migration:status --project=Blog
```

---

## Important Technical Notes

### Lazy Loading with PHP 8.4

`ProxyFactory` uses `ReflectionClass::newLazyGhost()`, available since PHP 8.4. `ToOne` associations return transparent proxies:

```php
$post = $em->find(Post::class, 1);
$author = $post->getAuthor(); // Proxy, no SQL yet
echo $author->getId();        // Access to the ID without triggering the load
echo $author->getName();      // SQL triggered here: SELECT * FROM users WHERE id = ?
```

### Change Detection (Change Tracking)

The UoW uses **value-based comparison** (`$originalEntityData` vs current value). There is no need to explicitly mark modified fields. `flush()` automatically computes the diff.

```php
$user = $em->find(User::class, 1);
$user->setName('New Alice'); // Change detected on the next flush()
$em->flush();                // UPDATE users SET name = ? WHERE id = 1
```

### Insertion Order (topological sort)

The UoW computes insertion order via a topological sort (Kahn's algorithm) on `ManyToOne` and `OneToOne` dependencies. This guarantees that parent entities are inserted before their children.

### Idempotent Migrations

Every generated operation is idempotent: `IF NOT EXISTS`, `IF EXISTS`, checking column existence via `information_schema`. This allows replaying a migration without error.

### Cascade

Cascading is handled at the UoW level (`cascadePersist`, `cascadeRemove`) based on the attribute's metadata. The `orphanRemoval: true` option on `OneToMany` automatically removes dereferenced children.

### CSRF Protection in Forms

If a `CsrfManager` is provided to `FormFactory`, a `_csrf_token` field is automatically added and validated during `handleRequest()`.

### Strict Separation of Concerns (Data Mapper)

Entities know nothing about the ORM. They are plain POPOs. It is the `EntityManager` and its collaborators that handle persistence. Entities can be instantiated and tested without a database.