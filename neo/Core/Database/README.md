# Database & ORM

Ce module fournit l'ensemble de la couche d'accès aux données du framework NeoPHP : un ORM complet de type Data Mapper, un système de migrations, un gestionnaire de formulaires et l'accès bas niveau à la base de données.

---

## Sommaire

1. [Architecture générale](#architecture-générale)
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
3. [Mapping — Attributs PHP](#mapping--attributs-php)
   - [ClassMetaData et MetadataFactory](#classmetadata-et-metadatafactory)
   - [Attributs disponibles](#attributs-disponibles)
   - [Types ORM](#types-orm)
   - [Platform](#platform)
4. [Migrations](#migrations)
   - [DatabaseIntrospector](#databaseintrospector)
   - [MigrationRunner](#migrationrunner)
   - [MigrationGenerator](#migrationgenerator)
   - [SchemaDiffer](#schemadiffer)
   - [SchemaTool](#schematool)
5. [Formulaires](#formulaires)
   - [FormFactory et FormBuilder](#formfactory-et-formbuilder)
   - [Form et FormField](#form-et-formfield)
   - [FormRenderer](#formrenderer)
6. [Accès base de données](#accès-base-de-données)
   - [DatabaseManager](#databasemanager)
   - [DatabaseConnection](#databaseconnection)
7. [Seeding](#seeding)
   - [SeedInterface](#seedinterface)
   - [Attribut #[Seeder]](#attribut-seeder)
   - [SeedManager](#seedmanager)
8. [Commandes CLI](#commandes-cli)
9. [Points techniques importants](#points-techniques-importants)

---

## Architecture générale

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
│   └── Extension/          # PaginationViewExtension (fonction Twig paginator_links)
├── Commands/               # Commandes CLI
└── DatabaseManager.php
```

---

## ORM — Object-Relational Mapper

L'ORM de NeoPHP suit le patron **Data Mapper** : les entités sont de simples objets PHP (POPO) sans héritage obligatoire. La persistance est gérée entièrement en dehors de l'entité.

### EntityManager

**Fichier :** `ORM/Persistence/EntityManager.php`

Point d'entrée principal de l'ORM. Il orchestre toutes les opérations de persistance.

| Méthode | Description |
|---------|-------------|
| `persist(object $entity)` | Enregistre une entité pour insertion ou suivi |
| `remove(object $entity)` | Marque une entité pour suppression |
| `flush()` | Exécute toutes les opérations en attente en base |
| `find(string $class, mixed $id)` | Recherche une entité par son identifiant |
| `getReference(string $class, mixed $id)` | Retourne un proxy sans charger les données |
| `getRepository(string $class)` | Retourne le dépôt associé à une entité |
| `contains(object $entity)` | Vérifie si une entité est gérée |
| `clear()` | Vide l'identity map et l'unit of work |
| `wrapInTransaction(callable $cb)` | Exécute un callback dans une transaction |

```php
// Injection dans un contrôleur ou service
use Neo\Core\Database\ORM\Persistence\EntityManager;

$em = $container->get(EntityManager::class);

// Créer et persister une entité
$user = new User();
$user->setName('Alice')->setEmail('alice@example.com');

$em->persist($user);
$em->flush(); // INSERT INTO users ...

// Retrouver une entité par son ID
$user = $em->find(User::class, 1);

// Modifier et sauvegarder
$user->setName('Alice Updated');
$em->flush(); // UPDATE users SET name = ? WHERE id = ?

// Supprimer
$em->remove($user);
$em->flush(); // DELETE FROM users WHERE id = ?

// Transaction explicite
$result = $em->wrapInTransaction(function (EntityManager $em) {
    $order = new Order();
    $em->persist($order);
    return $order;
});
```

### UnitOfWork

**Fichier :** `ORM/Persistence/UnitOfWork.php`

Implémente le patron **Unit of Work**. Il maintient une **identity map** et gère les états des entités.

**États d'une entité :**

| Constante | Valeur | Description |
|-----------|--------|-------------|
| `STATE_MANAGED` | 1 | Entité suivie, sera synchronisée au flush |
| `STATE_NEW` | 2 | Nouvelle entité, non encore persistée |
| `STATE_DETACHED` | 3 | Entité déconnectée de l'UoW |
| `STATE_REMOVED` | 4 | Entité marquée pour suppression |

**Fonctionnement interne :**

- L'identity map `$identityMap[className][idHash] = entity` garantit l'unicité des instances.
- Au moment du `flush()`, `computeChangeSets()` détecte les champs modifiés par comparaison avec `$originalEntityData`.
- L'ordre d'insertion est déterminé par un tri topologique (`getCommitOrder()`) basé sur les dépendances entre entités (clés étrangères).
- Les transactions PDO sont automatiquement gérées : si aucune transaction n'est active, l'UoW en ouvre une.

```php
// Accès à l'UoW via l'EntityManager
$uow = $em->getUnitOfWork();

// Vérifier si une entité est gérée
$uow->isManaged($entity); // bool

// Forcer l'enregistrement d'une entité comme gérée
$uow->registerManaged($entity, $id, $originalData);
```

### EntityRepository

**Fichier :** `ORM/Persistence/EntityRepository.php`

Classe générique de dépôt, extensible par entité.

```php
$repo = $em->getRepository(User::class);

// Trouver par ID
$user = $repo->find(1);

// Trouver tous
$users = $repo->findAll();

// Trouver par critères
$users = $repo->findBy(
    criteria: ['role' => 'admin'],
    orderBy: ['name' => 'ASC'],
    limit: 10,
    offset: 0
);

// Trouver un seul
$user = $repo->findOneBy(['email' => 'alice@example.com']);

// Compter
$count = $repo->count(['active' => true]);

// Pagination
$paginator = $repo->paginate(page: 1, perPage: 20, criteria: ['role' => 'admin'], orderBy: ['name' => 'ASC']);
```

Voir la section [Pagination](#pagination) pour l'API complète de l'objet retourné.

**Dépôt personnalisé :**

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
        // Accès au persister pour des requêtes personnalisées
        return $this->persister()->loadAll(['email_domain' => $domain]);
    }
}
```

### EntityPersister

**Fichier :** `ORM/Persistence/EntityPersister.php`

Exécute les opérations SQL réelles (INSERT, UPDATE, DELETE, SELECT) pour une classe d'entité donnée.

- **`insert(object $entity)`** — construit et exécute un INSERT, retourne le dernier ID inséré.
- **`update(object $entity, array $changeSet)`** — génère un UPDATE uniquement pour les champs modifiés.
- **`delete(object $entity)`** — exécute un DELETE par clé primaire.
- **`loadById(array $criteria)`** — SELECT avec hydratation.
- **`loadAll(array $criteria, array $orderBy, ?int $limit, ?int $offset)`** — SELECT avec filtres, tri et pagination.
- **`loadCollection(array $assoc, mixed $ownerId)`** — chargement des associations OneToMany et ManyToMany.

### ObjectHydrator

**Fichier :** `ORM/Persistence/ObjectHydrator.php`

Convertit une ligne SQL (tableau associatif) en objet PHP. Pour chaque ligne :

1. Convertit chaque valeur de colonne via le `TypeRegistry`.
2. Pour les associations `ToOne` (owning side), crée un proxy lazy.
3. Pour les associations `ToMany`, crée une `LazyCollection`.
4. Enregistre l'entité dans l'UoW via `registerManaged()`.

### ProxyFactory

**Fichier :** `ORM/Persistence/ProxyFactory.php`

Utilise la fonctionnalité PHP 8.4 **Lazy Ghosts** (`ReflectionClass::newLazyGhost`) pour créer des proxies transparents.

```php
// Un proxy est un objet "fantôme" : ses données ne sont chargées
// qu'au premier accès à une propriété non-ID.
$proxy = $em->getReference(User::class, 42);
// Aucune requête SQL ici

echo $proxy->getName(); // Déclenche le chargement lazy
// SELECT * FROM users WHERE id = 42
```

### QueryBuilder

**Fichier :** `ORM/Query/QueryBuilder.php`

Constructeur de requêtes SQL fluide, indépendant de l'ORM, opérant directement sur le `DatabaseManager`.

```php
use Neo\Core\Database\Query\QueryBuilder;

$qb = QueryBuilder::for($db, 'users');

// SELECT avec jointure et pagination
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

// Compter
$count = QueryBuilder::for($db, 'users')
    ->where('role', '=', 'admin')
    ->count();

// Insérer et récupérer l'ID
$id = QueryBuilder::for($db, 'users')
    ->insertGetId(['name' => 'Bob', 'email' => 'bob@example.com']);

// Mettre à jour
QueryBuilder::for($db, 'users')
    ->where('id', '=', 5)
    ->update(['name' => 'Robert']);

// Supprimer
QueryBuilder::for($db, 'users')
    ->where('active', '=', false)
    ->delete();

// Inspecter le SQL généré
echo $qb->toSql();
```

### Pagination

**Fichiers :** `Database/Pagination/Paginator.php`, `Database/Pagination/Extension/PaginationViewExtension.php`

`Paginator` est un conteneur standalone qui porte les éléments d'une page et les métadonnées associées (page courante, nombre total, etc.). Il est produit soit par `QueryBuilder::paginate()`, soit par `EntityRepository::paginate()`.

**Depuis le QueryBuilder :**

```php
$paginator = QueryBuilder::for($db, 'posts')
    ->where('published', '=', true)
    ->orderBy('created_at', 'DESC')
    ->paginate(page: 2, perPage: 10);
```

`paginate()` clone le builder pour exécuter le `COUNT()` séparément — le builder d'origine (colonnes, `LIMIT`/`OFFSET`) n'est pas altéré et peut continuer à être utilisé après.

**Depuis un EntityRepository :**

```php
$paginator = $repo->paginate(page: 1, perPage: 20, criteria: ['role' => 'admin'], orderBy: ['name' => 'ASC']);
```

**API de `Paginator` :**

| Méthode | Description |
|---------|-------------|
| `getItems()` | Éléments de la page courante (`list<T>`) |
| `getCurrentPage()` | Numéro de la page courante |
| `getPerPage()` | Nombre d'éléments par page |
| `getTotalItems()` | Nombre total d'éléments (toutes pages confondues) |
| `getTotalPages()` | Nombre total de pages |
| `hasNextPage()` / `hasPreviousPage()` | `bool` |
| `getNextPage()` / `getPreviousPage()` | Numéro de page suivante/précédente, ou `null` |
| `getLinks(int $onEachSide = 2)` | Fenêtre glissante de numéros de page pour la navigation, ex. `[1, null, 4, 5, 6, null, 12]` (`null` = `...`) |

`Paginator` implémente aussi `\IteratorAggregate` et `\Countable` : on peut itérer directement dessus (`foreach ($paginator as $item)`) ou faire `count($paginator)`.

**Rendu dans un template Twig :**

La fonction `paginator_links()` génère la navigation HTML. Aucun texte n'est codé en dur dans l'extension : les libellés sont des paramètres, avec des symboles neutres par défaut.

```twig
{# Navigation basique, symboles par défaut (« / » / …) #}
{{ paginator_links(paginator) }}

{# Base URL explicite (sinon le chemin de la requête courante est utilisé) #}
{{ paginator_links(paginator, '/posts') }}

{# Libellés personnalisés / traduits via le module i18n #}
{{ paginator_links(
    paginator,
    prevLabel: translate('pagination.prev'),
    nextLabel: translate('pagination.next'),
    gapLabel: translate('pagination.gap')
) }}
```

La navigation ajoute un paramètre `?page=N` (ou `&page=N` si l'URL de base contient déjà une query string) à chaque lien, et ne s'affiche pas si le paginator ne contient qu'une seule page.

### Collections

**Fichier :** `ORM/Collection/Collection.php`, `ORM/Collection/LazyCollection.php`

La `Collection` est un conteneur typé avec des méthodes standard (`add`, `remove`, `contains`, `count`, `toArray`, etc.).

La `LazyCollection` étend `Collection` : elle reçoit un `Closure` au lieu de données et ne charge les éléments qu'à la première utilisation.

```php
// Dans une entité, après hydratation
$user = $em->find(User::class, 1);
$posts = $user->getPosts(); // LazyCollection non initialisée

foreach ($posts as $post) {
    // Premier accès : SELECT * FROM posts WHERE user_id = 1
    echo $post->getTitle();
}
```

---

## Mapping — Attributs PHP

### ClassMetaData et MetadataFactory

**Fichier :** `ORM/Mapping/ClassMetaData.php`, `ORM/Mapping/MetadataFactory.php`

La `MetadataFactory` lit les attributs PHP d'une classe d'entité et construit un objet `ClassMetaData` mis en cache.

**Règles automatiques :**
- Si aucun `#[Table]` n'est spécifié, le nom de table est déduit : `UserProfile` → `user_profiles`.
- Les types de colonnes sont inférés depuis les types PHP (`int` → `integer`, `bool` → `boolean`, `array` → `json`, etc.).
- Les colonnes de jointure par défaut pour `ManyToOne` sont `{field}_id`.

### Attributs disponibles

#### `#[Entity]`

```php
use Neo\Core\Database\ORM\Mapping\Attribute\Entity;

#[Entity(repositoryClass: UserRepository::class, readOnly: false)]
final class User { }
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `repositoryClass` | `?string` | FQCN du dépôt personnalisé |
| `readOnly` | `bool` | Entité en lecture seule |

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

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `type` | `string` | `'string'` | Type ORM (voir TypeRegistry) |
| `name` | `?string` | nom de la propriété | Nom de la colonne SQL |
| `length` | `?int` | null | Longueur (VARCHAR) |
| `nullable` | `bool` | `false` | Colonne nullable |
| `unique` | `bool` | `false` | Contrainte UNIQUE |
| `unsigned` | `bool` | `false` | Entier non signé |
| `default` | `mixed` | null | Valeur par défaut |
| `precision` | `?int` | null | Précision (DECIMAL) |
| `scale` | `?int` | null | Échelle (DECIMAL) |
| `columnDefinition` | `?string` | null | SQL brut de définition |

#### `#[Id]` et `#[GeneratedValue]`

```php
use Neo\Core\Database\ORM\Mapping\Attribute\Id;
use Neo\Core\Database\ORM\Mapping\Attribute\GeneratedValue;

#[Id]
#[GeneratedValue]  // strategy: 'AUTO' par défaut
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

| Paramètre | Description |
|-----------|-------------|
| `targetEntity` | FQCN de l'entité cible |
| `inversedBy` | Nom du champ inverse sur la cible |
| `fetch` | `'LAZY'` (défaut) |
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
// Côté propriétaire (détient la FK)
#[OneToOne(targetEntity: Profile::class, inversedBy: 'user')]
#[JoinColumn(name: 'profile_id', unique: true, nullable: true)]
private ?Profile $profile = null;

// Côté inverse
#[OneToOne(targetEntity: User::class, mappedBy: 'profile')]
private ?User $user = null;
```

#### `#[ManyToMany]`

```php
use Neo\Core\Database\ORM\Mapping\Attribute\ManyToMany;
use Neo\Core\Database\ORM\Mapping\Attribute\JoinTable;
use Neo\Core\Database\ORM\Mapping\Attribute\JoinColumn;

// Côté propriétaire
/** @var Collection<Tag> */
#[ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
#[JoinTable(
    name: 'article_tag',
    joinColumns: [new JoinColumn(name: 'article_id')],
    inverseJoinColumns: [new JoinColumn(name: 'tag_id')]
)]
private Collection $tags;

// Côté inverse
/** @var Collection<Article> */
#[ManyToMany(targetEntity: Article::class, mappedBy: 'tags')]
private Collection $articles;
```

### Exemple complet d'entité

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

### Types ORM

| Nom ORM | Type PHP | SQL MySQL |
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

**Enregistrer un type personnalisé :**

```php
use Neo\Core\Database\ORM\Type\TypeRegistry;

TypeRegistry::register(new MyCustomType());
```

### Platform

`AbstractPlatform` définit le contrat pour la génération SQL spécifique à un SGBD. `MySQLPlatform` est l'implémentation par défaut.

```php
$platform = $em->getPlatform();
$platform->quoteIdentifier('my_table'); // `my_table`
$platform->getName();                   // 'mysql'
```

---

## Migrations

### DatabaseIntrospector

**Fichier :** `Access/Introspector/DatabaseIntrospector.php`

Lit la structure réelle de la base de données (tables, colonnes, clés étrangères, index) via `information_schema` et `SHOW`/`DESCRIBE`. C'est la source de vérité utilisée par le pipeline de migration pour comparer l'état actuel de la base à l'état souhaité (entités ORM).

Les résultats sont retournés sous forme de DTOs typés, dans `Access/Introspector/Metadata/` :

| Méthode | Retour |
|---------|--------|
| `getTables()` | `list<string>` |
| `getColumns(string $table)` | `list<ColumnMetadata>` |
| `getForeignKeys(string $table)` | `list<ForeignKeyMetadata>` |
| `getIndexes(string $table)` | `list<IndexMetadata>` |

```php
$introspector = new DatabaseIntrospector($container);
// Ou sur une connexion spécifique :
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

Chaque DTO expose aussi une méthode `toArray()` qui reconstruit la forme `array{...}` d'origine. Elle est utilisée à la frontière avec les composants qui manipulent encore des tableaux génériques — notamment `MigrationSchemaSnapshot`, qui sérialise le schéma en JSON pour le stocker en base (`neo_schema_snapshots`), et `MigrationGenerator::generate()`, qui réutilise la même logique de construction de SQL que `generateDiff()`. `SchemaDiffer` continue de travailler exclusivement sur des tableaux, puisqu'il compare aussi bien le schéma courant (introspecté) que le schéma désiré (issu de `SchemaTool`, côté ORM) ou un ancien schéma relu depuis un snapshot JSON.

### MigrationRunner

**Fichier :** `Migration/Runner/MigrationRunner.php`

Gère l'exécution et le rollback des migrations. Maintient la table `neo_migrations` en base.

```php
$runner = new MigrationRunner($db, 'default');

// Lister les migrations en attente
$pending = $runner->getPending('/path/to/migrations');

// Exécuter toutes les migrations en attente
$runner->run('/path/to/migrations');

// Dry run (liste sans exécuter)
$runner->run('/path/to/migrations', dryRun: true);

// Rollback du dernier batch
$runner->rollback('/path/to/migrations');
```

### MigrationGenerator

**Fichier :** `Migration/Generator/MigrationGenerator.php`

Génère des fichiers de migration PHP à partir d'un diff de schéma.

Chaque migration générée suit le format `MigrationVersion_YYYYMMDD_HHmmss.php` et contient :
- `up(DatabaseManager $db)` — applique les changements.
- `down(DatabaseManager $db)` — annule les changements.
- Des helpers `tableExists()` et `columnExists()` pour des opérations idempotentes.

**Exemple de migration générée :**

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

**Fichier :** `Migration/SchemaDiffer.php`

Compare deux schémas et produit un diff structuré :

```php
$differ = new SchemaDiffer();
$diff = $differ->diff($previousSchema, $currentSchema);

// $diff contient :
// - tablesToCreate : nouvelles tables
// - tablesToDrop   : tables supprimées
// - tableChanges   : modifications par table (added, removed, modified)

// Détecter si le diff est vide
$differ->isEmpty($diff); // bool

// Candidats au renommage de table (par signature de colonnes)
$candidates = $differ->findTableRenameCandidates($tablesToCreate, $tablesToDrop);

// Candidats au renommage de colonne
$candidates = $differ->findColumnRenameCandidates($removed, $added);

// Changements risqués NOT NULL
$risks = $differ->findRiskyNotNullChanges($tablesToCreate, $tableChanges);
```

### SchemaTool

**Fichier :** `ORM/Schema/SchemaTool.php`

Traduit les métadonnées ORM en schéma de base de données.

```php
$schemaTool = new SchemaTool($em);

// Obtenir le schéma souhaité depuis les entités
$schema = $schemaTool->getSchema([User::class, Post::class, Comment::class]);

// Obtenir les clés étrangères
$fks = $schemaTool->getForeignKeys([User::class, Post::class]);

// Obtenir les index
$indexes = $schemaTool->getIndexes([User::class]);
```

---

## Formulaires

### FormFactory et FormBuilder

**Fichiers :** `Form/FormFactory.php`, `Form/FormBuilder.php`

```php
$factory = $container->get(FormFactory::class);

// Formulaire simple
$builder = $factory->create('register');
$builder
    ->add('username', 'text', ['required' => true, 'maxLength' => 50])
    ->add('email', 'email', ['required' => true])
    ->add('password', 'password', ['required' => true, 'minLength' => 8])
    ->add('role', 'select', ['choices' => ['user' => 'Utilisateur', 'admin' => 'Administrateur']])
    ->add('newsletter', 'checkbox', ['label' => 'Recevoir la newsletter']);

$form = $builder->getForm();

// Formulaire lié à une entité
$user = $em->find(User::class, 1);
$builder = $factory->createFor($user, 'edit_user');
$builder->add('name', 'text', ['required' => true]);

$form = $builder->getForm(); // Les valeurs sont pré-remplies depuis $user
```

**Options disponibles pour `add()` :**

| Option | Type | Description |
|--------|------|-------------|
| `label` | `string` | Label du champ (auto-généré si absent) |
| `required` | `bool` | Ajoute la contrainte `NotBlank` |
| `requiredMessage` | `string` | Message de validation |
| `minLength` | `int` | Longueur minimale |
| `maxLength` | `int` | Longueur maximale |
| `constraints` | `array` | Contraintes de validation supplémentaires |
| `mapped` | `bool` | Lier au champ d'entité (défaut: `true`) |
| `choices` | `array` | Options pour `select` |
| `attr` | `array` | Attributs HTML supplémentaires |
| `placeholder` | `string` | Placeholder HTML |

### Form et FormField

**Fichiers :** `Form/Form.php`, `Form/Field/FormField.php`

```php
// Gérer une soumission
$form->handleRequest($_POST);

if ($form->isSubmitted() && $form->isValid()) {
    // Les données sont automatiquement mappées vers l'entité liée
    $user = $form->getEntity();
    $em->persist($user);
    $em->flush();
} else {
    // Accéder aux erreurs
    $errors = $form->getErrors();
    // ['email' => ['Ce champ est requis.'], ...]
}

// Accéder aux données brutes
$data = $form->getData();
// ['username' => 'Alice', 'email' => 'alice@example.com', ...]
```

**Types de champs (`FieldType`) :**

| Valeur | Type HTML |
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

**Fichier :** `Form/FormRenderer.php`

```php
$renderer = new FormRenderer();

// Rendu complet du formulaire
$html = $renderer->render($form, action: '/register', method: 'POST', attributes: ['class' => 'form-card']);

// Rendu partiel
$html  = $renderer->start($form, '/register');
$html .= $renderer->field($form->getField('email'));
$html .= $renderer->field($form->getField('password'));
$html .= $renderer->end();
```

Le rendu produit de l'HTML structuré :

```html
<form action="/register" method="POST">
   <input type="hidden" name="_csrf_token" value="abc123">

   <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="" required="required">
      <ul class="form-errors">
         <li>Ce champ est requis.</li>
      </ul>
   </div>
</form>
```

---

## Accès base de données

### DatabaseManager

**Fichier :** `DatabaseManager.php`

Interface bas niveau pour les requêtes PDO.

```php
$db = $container->get(DatabaseManager::class);

// Requête retournant une ligne
$row = $db->fetch('SELECT * FROM users WHERE id = :id', ['id' => 1]);

// Requête retournant plusieurs lignes
$rows = $db->fetchAll('SELECT * FROM users WHERE role = ?', ['admin']);

// Exécuter sans résultat (INSERT, UPDATE, DELETE)
$db->execute('UPDATE users SET active = 1 WHERE id = ?', [42]);

// Requête avec objet PDOStatement
$stmt = $db->query('SELECT COUNT(*) FROM users');

// Dernier ID inséré
$id = $db->lastInsertId();

// Connexion spécifique (multi-connexion)
$dbSecondary = DatabaseManager::on('secondary');
```

### DatabaseConnection

**Fichier :** `Access/Connection/DatabaseConnection.php`

Gestionnaire de connexions PDO. Supporte plusieurs connexions nommées.

```php
// Connexion par défaut (gérée automatiquement par le framework)
$pdo = DatabaseConnection::getPdo();

// Connexion nommée
$pdo = DatabaseConnection::connectTo('reporting');

// Vérifier l'état de connexion
DatabaseConnection::isConnected();          // connexion par défaut
DatabaseConnection::isConnected('reporting'); // connexion nommée

// Noms des connexions actives
$names = DatabaseConnection::getConnectionNames();
```

**Configuration `database.config.php` :**

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

**Connexions persistantes**

Chaque connexion peut activer `PDO::ATTR_PERSISTENT` via l'option `persistent` :

```php
'connections' => [
    'default' => [
        // ...
        'persistent' => true,
    ],
],
```

Sur un serveur PHP-FPM avec beaucoup de requêtes courtes, cela évite de rouvrir une connexion TCP/authentification à chaque requête : le worker FPM réutilise la même connexion PDO d'une requête à l'autre.

> ⚠️ **Attention** : une connexion persistante survit à la requête qui l'a ouverte. Si une transaction est démarrée (`beginTransaction()`) et n'est jamais explicitement terminée par un `commit()` ou un `rollback()` — notamment en cas d'exception non gérée — l'état peut fuiter vers la requête suivante traitée par le même worker, avec un comportement indéterminé selon le driver. N'activez `persistent` que si le code applicatif garantit la fermeture systématique de toutes les transactions (y compris sur les chemins d'erreur).

---

## Seeding

Le module Seeder permet de peupler la base de données avec des données de référence ou de démonstration. Il est structuré autour d'une interface, d'un attribut de configuration et d'un `SeedManager`.

```
Database/Seeder/
├── SeedManager.php                         # Découverte, filtrage et exécution des seeders
├── Interface/
│   └── SeedInterface.php                  # Contrat d'un seeder
├── Attribute/
│   └── Seeder.php                         # Attribut de configuration
└── Commands/
    ├── DatabaseMakeSeedCommand.php         # Générateur de seeder
    └── DatabaseRunSeedCommand.php          # Exécution des seeders
```

### SeedInterface

Tout seeder doit implémenter `SeedInterface` :

```php
namespace Neo\Core\Database\Seeder\Interface;

use Neo\Core\Database\ORM\Persistence\EntityManager;

interface SeedInterface
{
    public function run(EntityManager $entityManager): void;
}
```

La méthode `run()` reçoit directement l'`EntityManager`, ce qui permet de persister des entités sans instancier manuellement l'ORM.

### Attribut `#[Seeder]`

L'attribut `#[Seeder]` se pose sur la classe et configure deux paramètres :

```php
use Neo\Core\Database\Seeder\Attribute\Seeder;

#[Seeder(order: 10, group: 'reference')]
final class CountrySeeder implements SeedInterface { ... }
```

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `order` | `int` | `0` | Ordre d'exécution (croissant). Les seeders sans dépendances peuvent garder `0`. |
| `group` | `string` | `'reference'` | Groupe du seeder. Valeurs conventionnelles : `'reference'` (données stables, toujours exécutées) et `'demo'` (données de développement/test). |

Un seeder sans l'attribut `#[Seeder]` est ignoré par le `SeedManager`.

### SeedManager

`SeedManager` orchestre la découverte, le filtrage et l'exécution des seeders.

**Découverte** — `discover(string $directory, string $namespace): array`

Scanne un répertoire récursivement, charge les classes PHP trouvées, vérifie la présence de l'attribut `#[Seeder]` et de l'interface `SeedInterface`, puis retourne la liste triée par `order` croissant :

```php
$manager = new SeedManager($container);

$seeders = $manager->discover(
    '/var/www/app/src/Blog/Database/Seeder',
    'Neo\Src\Blog\Database\Seeder'
);
// [['class' => 'Neo\Src\Blog\Database\Seeder\CountrySeeder', 'order' => 10, 'group' => 'reference'], ...]
```

**Filtrage** — `filterByGroup(array $seeders, ?string $group, bool $includeDev): array`

| Appel | Résultat |
|-------|----------|
| `filterByGroup($seeders, null, false)` | Uniquement le groupe `'reference'` |
| `filterByGroup($seeders, null, true)` | Tous les groupes |
| `filterByGroup($seeders, 'demo', false)` | Uniquement le groupe `'demo'` |

**Exécution** — `run(array $seeders): list<string>`

Résout chaque seeder via le conteneur DI, appelle `run($em)` et retourne la liste des FQCN exécutés.

### Commandes CLI

#### `database:make:seed`

Génère un fichier de seeder dans `src/<Projet>/Database/Seeder/`.

```bash
php bin/neo database:make:seed CountrySeeder --project=Blog
php bin/neo database:make:seed CountrySeeder --project=Blog --order=10 --group=reference
php bin/neo database:make:seed DemoPostSeeder --project=Blog --order=50 --group=demo
php bin/neo database:make:seed CountrySeeder --project=Blog --force   # Écrase si existant
```

Options :

| Option | Description |
|--------|-------------|
| `--project` | Projet cible (obligatoire) |
| `--order` | Ordre d'exécution (défaut : `0`) |
| `--group` | Groupe (défaut : `'reference'`) |
| `--force` | Écrase le fichier sans confirmation |

Le nom est automatiquement converti en PascalCase et suffixé par `Seeder` s'il ne l'est pas déjà.

#### `database:run:seed`

Exécute les seeders d'un projet.

```bash
# Exécuter les seeders 'reference' uniquement (comportement par défaut)
php bin/neo database:run:seed --project=Blog

# Inclure les seeders de développement (tous les groupes)
php bin/neo database:run:seed --project=Blog --dev

# Exécuter uniquement un groupe spécifique
php bin/neo database:run:seed --project=Blog --group=demo

# Prévisualiser sans exécuter
php bin/neo database:run:seed --project=Blog --dry-run
```

Options :

| Option | Description |
|--------|-------------|
| `--project` | Projet cible (obligatoire) |
| `--group` | Filtre sur un groupe précis |
| `--dev` | Inclut tous les groupes (dont `demo`) |
| `--dry-run` | Liste les seeders qui seraient exécutés sans les lancer |

La commande affiche la liste ordonnée des seeders avant exécution et demande une confirmation interactive.

### Exemple complet

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
            ['code' => 'DE', 'name' => 'Allemagne'],
            ['code' => 'ES', 'name' => 'Espagne'],
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

Workflow type :

```bash
# Générer un seeder
php bin/neo database:make:seed CountrySeeder --project=Blog --order=10

# Prévisualiser
php bin/neo database:run:seed --project=Blog --dry-run

# Exécuter
php bin/neo database:run:seed --project=Blog

# Données de demo uniquement
php bin/neo database:run:seed --project=Blog --group=demo
```

---

## Commandes CLI

| Commande | Description |
|----------|-------------|
| `make:entity` | Génère une entité et son dépôt de manière interactive |
| `database:create` | Crée la base de données définie dans la configuration |
| `database:orm:diff` | Compare les entités et la base, génère une migration |
| `database:migration:migrate` | Applique toutes les migrations en attente |
| `database:migration:rollback` | Annule le dernier batch de migrations |
| `database:migration:status` | Affiche l'état des migrations |
| `database:make:seed` | Génère un seeder dans le projet cible |
| `database:run:seed` | Exécute les seeders d'un projet |

### `make:entity`

```bash
php neo make:entity --project=Blog
# L'outil pose des questions interactives :
# - Nom de l'entité
# - Champs (type, nullable, longueur)
# - Relations (ManyToOne, OneToMany, ManyToMany, OneToOne)
# - Génère l'entité et son repository
```

### `database:create`

```bash
php neo database:create --project=Blog
# Lit database.config.php et crée la base de données
```

### `database:orm:diff`

```bash
# Comparer les entités avec la base et générer une migration
php neo database:orm:diff --project=Blog --name=add_posts_table

# Dry run (prévisualiser sans écrire)
php neo database:orm:diff --project=Blog --name=test --dry-run

# Utiliser une connexion spécifique
php neo database:orm:diff --project=Blog --name=secondary_update --connection=secondary
```

### `database:migration:migrate`

```bash
# Appliquer les migrations en attente
php neo database:migration:migrate --project=Blog

# Lister sans appliquer
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

## Points techniques importants

### Lazy Loading avec PHP 8.4

Le `ProxyFactory` utilise `ReflectionClass::newLazyGhost()`, disponible depuis PHP 8.4. Les associations `ToOne` retournent des proxies transparents :

```php
$post = $em->find(Post::class, 1);
$author = $post->getAuthor(); // Proxy, pas de SQL encore
echo $author->getId();        // Accès à l'ID sans déclencher le chargement
echo $author->getName();      // SQL déclenché ici : SELECT * FROM users WHERE id = ?
```

### Détection de changements (Change Tracking)

L'UoW utilise une **comparaison par valeur** (`$originalEntityData` vs valeur actuelle). Il n'est pas nécessaire de marquer explicitement les champs modifiés. Le `flush()` calcule automatiquement le diff.

```php
$user = $em->find(User::class, 1);
$user->setName('Alice Nouveau'); // Modification détectée au prochain flush()
$em->flush();                    // UPDATE users SET name = ? WHERE id = 1
```

### Ordre d'insertion (topological sort)

L'UoW calcule l'ordre des insertions via un tri topologique (algorithme de Kahn) sur les dépendances `ManyToOne` et `OneToOne`. Cela garantit que les entités parentes sont insérées avant leurs enfants.

### Migrations idempotentes

Toutes les opérations générées sont idempotentes : `IF NOT EXISTS`, `IF EXISTS`, contrôle de l'existence de colonnes via `information_schema`. Cela permet de rejouer une migration sans erreur.

### Cascade

La cascade est gérée au niveau de l'UoW (`cascadePersist`, `cascadeRemove`) à partir des métadonnées de l'attribut. L'option `orphanRemoval: true` sur `OneToMany` supprime automatiquement les enfants déréférencés.

### Protection CSRF dans les formulaires

Si un `CsrfManager` est fourni à la `FormFactory`, un champ `_csrf_token` est automatiquement ajouté et validé lors du `handleRequest()`.

### Séparation stricte des responsabilités (Data Mapper)

Les entités ne connaissent pas l'ORM. Elles sont de simples POPO. C'est l'`EntityManager` et ses collaborateurs qui gèrent la persistance. On peut instancier et tester les entités sans base de données.