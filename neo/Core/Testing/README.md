# Testing

Le module Testing fournit l'infrastructure complète de tests pour les applications NeoPHP. Il s'appuie sur PHPUnit et propose quatre classes de base spécialisées, un système d'attributs PHP 8 pour déclarer les tests directement sur les classes métier, ainsi qu'un générateur automatique de fichiers de test.

---

## Sommaire

1. [Structure du module](#structure-du-module)
2. [Classes de base](#classes-de-base)
   - [TestCase](#testcase)
   - [DatabaseTestCase](#databasetestcase)
   - [FeatureTestCase](#featuretestcase)
   - [MiddlewareTestCase](#middlewaretestcase)
3. [Attribut #[Test] et enum TestType](#attribut-test-et-enum-testtype)
4. [Scanner et Générateur automatique](#scanner-et-générateur-automatique)
5. [Commandes CLI](#commandes-cli)
6. [Conventions de nommage et structure](#conventions-de-nommage-et-structure)

---

## Structure du module

```
Testing/
├── TestCase.php                    # Classe de base pour les tests unitaires
├── DatabaseTestCase.php            # Classe de base pour les tests de base de données
├── FeatureTestCase.php             # Classe de base pour les tests HTTP / feature
├── MiddlewareTestCase.php          # Classe de base pour les tests de middleware
├── Attribute/
│   └── Test.php                   # Attribut PHP 8 #[Test]
├── Enum/
│   └── TestType.php               # Enum des types de tests (unit, feature, database, middleware)
├── Context/
│   ├── TestClassContext.php        # Contexte d'une classe analysée
│   └── TestMethodContext.php       # Contexte d'une méthode analysée
├── Scanner/
│   └── TestScanner.php            # Scanner de classes portant l'attribut #[Test]
├── Generator/
│   └── TestGenerator.php          # Générateur de fichiers de test
├── Scaffold/
│   └── TestScaffolder.php         # Création de la structure Tests/ initiale
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

## Classes de base

### TestCase

`Neo\Core\Testing\TestCase` est la classe mère pour tous les tests **unitaires**. Elle initialise l'application NeoPHP et expose le conteneur d'injection de dépendances.

```php
use Neo\Core\Testing\TestCase;

class MonServiceTest extends TestCase
{
    public function test_calcul(): void
    {
        $service = $this->get(MonService::class);
        $this->assertSame(42, $service->calculer());
    }
}
```

**Méthodes disponibles :**

| Méthode | Description |
|---|---|
| `get(string $id): mixed` | Résout un service depuis le conteneur DI |
| `swap(string $id, mixed $value): void` | Remplace un service dans le conteneur (mock) |

L'instance de `App` est partagée (propriété `static $app`) entre les méthodes d'un même test pour éviter de réinitialiser l'application à chaque test.

```php
class MonTest extends TestCase
{
    public function test_avec_mock(): void
    {
        // Remplace le mailer réel par un faux
        $this->swap(Mailer::class, new FakeMailer());

        $service = $this->get(MonService::class);
        $service->envoyerEmail('test@example.com');

        $this->assertTrue(true); // pas d'exception = succès
    }
}
```

---

### DatabaseTestCase

`Neo\Core\Testing\DatabaseTestCase` est spécialisée pour les tests qui interagissent avec la base de données. Chaque test s'exécute dans une **transaction automatiquement annulée** (`rollBack`) à la fin, garantissant l'isolation complète sans polluer la base.

```php
use Neo\Core\Testing\DatabaseTestCase;

class UserRepositoryTest extends DatabaseTestCase
{
    public function test_insertion_utilisateur(): void
    {
        $id = $this->insertFixture('users', [
            'name'  => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
        $this->assertIsInt((int) $id);
    }

    public function test_suppression_utilisateur(): void
    {
        $this->insertFixture('users', ['name' => 'Bob', 'email' => 'bob@example.com']);

        // Après suppression logique
        $this->assertDatabaseMissing('users', ['email' => 'inconnu@example.com']);
    }
}
```

**Méthodes disponibles :**

| Méthode | Description |
|---|---|
| `insertFixture(string $table, array $data): int\|string` | Insère une ligne dans la table et retourne le dernier ID inséré |
| `fetchAll(string $table, string $where, array $bindings): array` | Récupère toutes les lignes d'une table |
| `assertDatabaseHas(string $table, array $data): void` | Vérifie qu'une ligne correspondant aux critères existe |
| `assertDatabaseMissing(string $table, array $data): void` | Vérifie qu'aucune ligne ne correspond aux critères |
| `get(string $id): mixed` | Résout un service DI |
| `swap(string $id, mixed $value): void` | Remplace un service (mock) |

La propriété `$this->pdo` expose directement la connexion PDO active si des requêtes plus complexes sont nécessaires.

---

### FeatureTestCase

`Neo\Core\Testing\FeatureTestCase` permet de tester les routes HTTP de l'application en envoyant de **vraies requêtes** à travers le kernel NeoPHP. Les réponses sont des objets `Response` complets.

```php
use Neo\Core\Testing\FeatureTestCase;

class ArticleControllerTest extends FeatureTestCase
{
    public function test_liste_articles(): void
    {
        $response = $this->get('/articles');

        $this->assertStatus(200, $response);
        $this->assertSeeText('Mes articles', $response);
    }

    public function test_creation_article(): void
    {
        $response = $this->post('/articles', [
            'title'   => 'Mon article',
            'content' => 'Contenu de test',
        ]);

        $this->assertStatus(201, $response);
        $this->assertJsonKey('id', $response);
    }

    public function test_suppression_article(): void
    {
        $response = $this->delete('/articles/1', [
            'Authorization' => 'Bearer token123',
        ]);

        $this->assertStatus(200, $response);
    }

    public function test_mise_a_jour_article(): void
    {
        $response = $this->put('/articles/1', ['title' => 'Nouveau titre']);

        $this->assertStatus(200, $response);
    }
}
```

**Méthodes HTTP disponibles :**

| Méthode | Signature |
|---|---|
| `get` | `get(string $uri, array $headers = []): Response` |
| `post` | `post(string $uri, array $body = [], array $headers = []): Response` |
| `put` | `put(string $uri, array $body = [], array $headers = []): Response` |
| `delete` | `delete(string $uri, array $headers = []): Response` |
| `request` | `request(string $method, string $uri, array $body, array $headers): Response` |

**Assertions disponibles :**

| Assertion | Description |
|---|---|
| `assertStatus(int $expected, Response $response)` | Vérifie le code HTTP de la réponse |
| `assertSeeText(string $text, Response $response)` | Vérifie la présence d'un texte dans la réponse |
| `assertJsonKey(string $key, Response $response)` | Vérifie qu'une clé existe dans la réponse JSON |

Les exceptions `FrameworkException` sont interceptées et converties en réponses avec le code HTTP approprié (par défaut 500).

---

### MiddlewareTestCase

`Neo\Core\Testing\MiddlewareTestCase` est dédiée aux tests de **middlewares**. Elle permet d'instancier un middleware via le conteneur DI et de vérifier son comportement (passage ou blocage).

```php
use Neo\Core\Testing\MiddlewareTestCase;

class AuthMiddlewareTest extends MiddlewareTestCase
{
    public function test_middleware_autorise_utilisateur_connecte(): void
    {
        // Simuler un utilisateur connecté
        $this->swap(AuthService::class, new FakeAuthService(authenticated: true));

        $middleware = $this->makeMiddleware(AuthMiddleware::class);

        $this->assertMiddlewarePasses($middleware);
    }

    public function test_middleware_bloque_utilisateur_non_connecte(): void
    {
        $this->swap(AuthService::class, new FakeAuthService(authenticated: false));

        $middleware = $this->makeMiddleware(AuthMiddleware::class);

        $this->assertMiddlewareBlocksWithCode($middleware, 401);
    }
}
```

**Méthodes disponibles :**

| Méthode | Description |
|---|---|
| `makeMiddleware(string $class, array $params = []): MiddlewareInterface` | Instancie un middleware via le conteneur |
| `assertMiddlewarePasses(MiddlewareInterface $m)` | Vérifie que `handle()` retourne `true` |
| `assertMiddlewareBlocks(MiddlewareInterface $m)` | Vérifie que `handle()` retourne `false` ou lève une `FrameworkException` |
| `assertMiddlewareBlocksWithCode(MiddlewareInterface $m, int $code)` | Vérifie que le middleware lève une `FrameworkException` avec le code HTTP précis |
| `get(string $id): mixed` | Résout un service DI |
| `swap(string $id, mixed $value): void` | Remplace un service (mock) |

---

## Attribut #[Test] et enum TestType

### Attribut #[Test]

L'attribut `Neo\Core\Testing\Attribute\Test` peut être posé sur une **classe** ou une **méthode** pour indiquer au générateur automatique comment créer le test correspondant.

```php
use Neo\Core\Testing\Attribute\Test;

#[Test(type: 'unit')]
class MonService
{
    #[Test(cases: [['input' => 'foo', 'expected' => 'FOO']])]
    public function transformer(string $input): string
    {
        return strtoupper($input);
    }

    #[Test(skip: true)]
    public function methodeTropComplexe(): void
    {
        // ce test sera ignoré par le générateur
    }
}
```

**Paramètres de l'attribut :**

| Paramètre | Type | Défaut | Description |
|---|---|---|---|
| `type` | `string` | `'auto'` | Type de test : `unit`, `feature`, `database`, `middleware`, `auto` |
| `cases` | `array` | `[]` | Jeux de données pour les data providers |
| `route` | `?string` | `null` | Route HTTP à appeler (tests feature) |
| `httpMethod` | `string` | `'GET'` | Méthode HTTP (tests feature) |
| `dataset` | `array` | `[]` | Données statiques partagées |
| `skip` | `bool` | `false` | Ignore cette classe/méthode lors de la génération |
| `extends` | `?string` | `null` | Classe parente personnalisée à étendre |

### Enum TestType

`Neo\Core\Testing\Enum\TestType` détermine quel `TestCase` utiliser selon le contexte.

| Valeur | TestCase généré | Sous-dossier |
|---|---|---|
| `unit` | `TestCase` | `Unit/` |
| `feature` | `FeatureTestCase` | `Feature/` |
| `database` | `DatabaseTestCase` | `Database/` |
| `middleware` | `MiddlewareTestCase` | `Middleware/` |
| `auto` | Détecté depuis le FQCN | (varie) |

La détection automatique (`auto`) inspecte le nom de la classe :
- Contient `Repository` → `DatabaseTestCase`
- Contient `Controller` → `FeatureTestCase`
- Contient `Middleware` → `MiddlewareTestCase`
- Sinon → `TestCase`

---

## Scanner et Générateur automatique

### TestScanner

`Neo\Core\Testing\Scanner\TestScanner` parcourt récursivement le dossier source (`src/MonProjet/`) à la recherche de fichiers PHP portant l'attribut `#[Test]`. Il retourne une liste de `TestClassContext`.

```php
$scanner = new TestScanner();
$contexts = $scanner->scan('/chemin/vers/src/MonProjet');

foreach ($contexts as $ctx) {
    echo $ctx->fqcn;       // Nom complet de la classe
    echo $ctx->shortName;  // Nom court
    echo $ctx->type->value; // 'unit', 'feature', etc.
}
```

### TestGenerator

`Neo\Core\Testing\Generator\TestGenerator` utilise le `TestScanner` pour analyser le projet et génère les fichiers de test correspondants selon les templates disponibles.

```php
$generator = new TestGenerator($container);

$result = $generator->generate(
    force: false,      // Ne pas écraser les fichiers existants
    onlyType: 'unit',  // Générer uniquement les tests unitaires
    dryRun: true,      // Simuler sans écrire
);

// $result = ['generated' => [...], 'skipped' => [...]]
```

Les templates utilisés varient selon le type et le contexte de la classe :
- Classe dans un namespace `Model` (sans `Repository` ni `Controller`) → `ModelTestTemplate`
- Type `database` → `DatabaseTestTemplate`
- Type `feature` → `FeatureTestTemplate`
- Type `middleware` → `MiddlewareTestTemplate`
- Sinon → `UnitTestTemplate`

---

## Commandes CLI

### `make:test` — Générer un test manuellement

Crée un fichier de test PHPUnit à partir d'un squelette adapté au type choisi.

```bash
php neo make:test UserTest --project=Blog --type=unit
php neo make:test ArticleControllerTest --project=Blog --type=feature
php neo make:test UserRepositoryTest --project=Blog --type=database --force
```

**Options :**

| Option | Description |
|---|---|
| `testName` (argument) | Nom de la classe de test (suffixe `Test` ajouté automatiquement) |
| `--project` | Projet cible (dossier dans `src/`) |
| `--type` | Type : `unit`, `feature`, `database`, `middleware` |
| `--force` | Écrase le fichier s'il existe déjà |

Fichier généré dans : `src/MonProjet/Tests/Unit/UserTest.php`

### `make:test:auto` — Génération automatique depuis les attributs

Scanne le projet à la recherche de classes annotées `#[Test]` et génère les tests correspondants.

```bash
php neo make:test:auto --project=Blog
php neo make:test:auto --project=Blog --only=feature
php neo make:test:auto --project=Blog --dry-run
php neo make:test:auto --project=Blog --force
```

**Options :**

| Option | Description |
|---|---|
| `--project` | Projet cible |
| `--force` | Écrase les fichiers existants |
| `--only` | Filtre par type : `unit`, `feature`, `database`, `middleware` |
| `--dry-run` | Affiche ce qui serait généré sans écrire |

### `run:test` — Lancer un test ciblé

Exécute un fichier de test PHPUnit précis avec `--testdox`.

```bash
php neo run:test UserTest --project=Blog
php neo run:test UserTest --project=Blog --type=unit --filter=test_creation
```

**Options :**

| Option | Description |
|---|---|
| `testName` (argument) | Nom de la classe de test |
| `--project` | Projet cible |
| `--type` | Restreint la recherche au sous-dossier du type |
| `--filter` | Filtre PHPUnit sur le nom de méthode |

### `run:test:all` — Lancer tous les tests d'un projet

Exécute la suite complète via `phpunit.xml` du projet.

```bash
php neo run:test:all --project=Blog
php neo run:test:all --project=Blog --coverage
php neo run:test:all --project=Blog --format=html --stop-on-failure
```

**Options :**

| Option | Description |
|---|---|
| `--project` | Projet cible |
| `--format` | Format de sortie : `console`, `html`, `both` |
| `--coverage` | Génère un rapport de couverture (nécessite Xdebug ou PCOV) |
| `--stop-on-failure` | Arrête l'exécution au premier échec |

Les rapports HTML sont générés dans `src/MonProjet/Storage/reports/`.

---

## Conventions de nommage et structure

La structure de tests attendue dans chaque projet :

```
src/MonProjet/
└── Tests/
    ├── phpunit.xml
    ├── Unit/
    │   └── MonServiceTest.php
    ├── Feature/
    │   └── ArticleControllerTest.php
    ├── Database/
    │   └── UserRepositoryTest.php
    └── Middleware/
        └── AuthMiddlewareTest.php
```

- Le nom de la classe de test doit toujours se terminer par `Test` (ajouté automatiquement si absent).
- Les namespaces suivent la convention `Neo\Src\MonProjet\Tests\{Type}`.
- Chaque test doit étendre la classe de base correspondante à son type.
