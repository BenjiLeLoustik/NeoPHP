<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(
    name: 'make:test',
    description: 'Générer un squelette de test PHPUnit pour un projet'
)]
final class MakeTestCommand implements CommandInterface
{
    private const VALID_TYPES = ['unit', 'feature', 'database', 'middleware'];

    public function getName(): string
    {
        return 'make:test';
    }

    public function getDescription(): string
    {
        return 'Générer un squelette de test PHPUnit pour un projet';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : make:test
Description : Génère un squelette de test PHPUnit dans le dossier Tests/ du projet.

Usage :
  php bin/neo make:test <TestName> --type=<type> --project=NomDuProjet

Arguments :
  TestName               Nom de la classe de test (ex: UserServiceTest)

Options :
  --type=unit            Teste une classe PHP isolée (service, model...)
  --type=feature         Teste une route HTTP de bout en bout
  --type=database        Teste un Repository avec rollback automatique
  --type=middleware      Teste qu'un middleware bloque ou laisse passer
  --force                Écraser le fichier existant
  --project=NomDuProjet  Nom du projet dans ./src/

Exemples :
  php bin/neo make:test UserServiceTest --type=unit --project=Blog
  php bin/neo make:test UserControllerTest --type=feature --project=Blog
  php bin/neo make:test UserRepositoryTest --type=database --project=Blog
  php bin/neo make:test AuthMiddlewareTest --type=middleware --project=Blog

Notes :
  - Les tests sont générés dans src/<Projet>/Tests/<Type>/
  - Un bootstrap.php et un phpunit.xml sont générés automatiquement au premier appel
  - Pour les tests Database, créez src/<Projet>/Tests/Config/database.config.test.php
HELP;
    }

    public function execute(array $args): void
    {
        $testName = $args[0] ?? null;
        $project  = $this->getOption($args, '--project');
        $type     = strtolower($this->getOption($args, '--type') ?? 'unit');
        $force    = $this->hasFlag($args, '--force');

        if (!$testName || !$project) {
            echo "Usage : php bin/neo make:test <TestName> --type=<type> --project=NomDuProjet\n";
            return;
        }

        if (!in_array($type, self::VALID_TYPES, true)) {
            echo "Type invalide '$type'. Types disponibles : " . implode(', ', self::VALID_TYPES) . "\n";
            return;
        }

        $basePath = ROOT_DIR . "/src/$project";

        if (!is_dir($basePath)) {
            echo "Le projet '$project' n'existe pas dans ./src/\n";
            return;
        }

        $testName = $this->normalizeTestName($testName);

        $this->ensureTestsBootstrap($basePath, $project);
        $this->ensurePhpUnitXml($basePath, $project);

        $this->generateTest($basePath, $project, $testName, $type, $force);
    }

    private function generateTest(
        string $basePath,
        string $project,
        string $testName,
        string $type,
        bool $force
    ): void {
        $typeDir   = ucfirst($type);
        $targetDir = "$basePath/Tests/$typeDir";

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $filePath = "$targetDir/{$testName}.php";

        if (file_exists($filePath) && !$force) {
            echo "Le test '$testName' existe déjà (utilise --force pour écraser).\n";
            return;
        }

        $namespace = "Neo\\Src\\$project\\Tests\\$typeDir";
        $content   = $this->buildTestContent($namespace, $testName, $type, $project);

        file_put_contents($filePath, $content);

        echo "Test '$testName' généré : src/$project/Tests/$typeDir/{$testName}.php\n";
    }

    private function buildTestContent(
        string $namespace,
        string $testName,
        string $type,
        string $project
    ): string {
        return match ($type) {
            'unit' => $this->unitTemplate($namespace, $testName, $project),
            'feature' => $this->featureTemplate($namespace, $testName, $project),
            'database' => $this->databaseTemplate($namespace, $testName, $project),
            'middleware' => $this->middlewareTemplate($namespace, $testName, $project),
            default => $this->unitTemplate($namespace, $testName, $project),
        };
    }

    private function unitTemplate(string $namespace, string $testName, string $project): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use Neo\Core\Testing\TestCase;

/**
 * Test unitaire : {$testName}
 *
 * Teste une classe PHP isolée (Service, Model, Util...).
 * Utilisez \$this->get(ServiceClass::class) pour récupérer un service du conteneur.
 * Utilisez \$this->swap(ServiceClass::class, \$mock) pour remplacer un service par un mock.
 */
class {$testName} extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Initialisation avant chaque test
    }

    public function test_example(): void
    {
        // Exemple : récupérer un service
        // \$service = \$this->get(\\Neo\\Src\\{$project}\\App\\Services\\ExampleService::class);
        // \$result  = \$service->doSomething();
        // \$this->assertSame('expected', \$result);

        \$this->assertTrue(true);
    }
}
PHP;
    }

    private function featureTemplate(string $namespace, string $testName, string $project): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use Neo\Core\Testing\FeatureTestCase;

/**
 * Test Feature : {$testName}
 *
 * Teste une route HTTP de bout en bout.
 * Méthodes disponibles : \$this->get(), ->post(), ->put(), ->delete()
 * Assertions : assertStatus(), assertSeeText(), assertJsonKey()
 */
class {$testName} extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_page_returns_200(): void
    {
        // Exemple : tester que la page d'accueil répond bien
        // \$response = \$this->get('/');
        // \$this->assertStatus(200, \$response);

        \$this->assertTrue(true);
    }

    public function test_post_endpoint(): void
    {
        // Exemple : tester un endpoint POST
        // \$response = \$this->post('/login', [
        //     'email'    => 'test@example.com',
        //     'password' => 'secret',
        // ]);
        // \$this->assertStatus(302, \$response);

        \$this->assertTrue(true);
    }
}
PHP;
    }

    private function databaseTemplate(string $namespace, string $testName, string $project): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use Neo\Core\Testing\DatabaseTestCase;

/**
 * Test Database : {$testName}
 *
 * Chaque test s'exécute dans une transaction automatiquement rollbackée.
 * La base de données n'est jamais modifiée de façon permanente.
 *
 * Config de test : créez src/{$project}/Tests/Config/database.config.test.php
 * pour surcharger la connexion (ex: base de test dédiée).
 *
 * Méthodes disponibles :
 *   \$this->insertFixture(table, data) : insère une ligne, retourne l'ID
 *   \$this->fetchAll(table, where, bindings) : récupère des lignes
 *   \$this->assertDatabaseHas(table, data) : vérifie qu'une ligne existe
 *   \$this->assertDatabaseMissing(table, data) : vérifie qu'une ligne est absente
 */
class {$testName} extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_insert_and_retrieve(): void
    {
        // Exemple : insérer une fixture et vérifier en base
        // \$id = \$this->insertFixture('users', [
        //     'name'  => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        //
        // \$this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        // \$this->assertIsNumeric(\$id);

        \$this->assertTrue(true);
    }
}
PHP;
    }

    private function middlewareTemplate(string $namespace, string $testName, string $project): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace {$namespace};

use Neo\Core\Testing\MiddlewareTestCase;

/**
 * Test Middleware : {$testName}
 *
 * Teste qu'un middleware bloque ou laisse passer correctement une requête.
 *
 * Méthodes disponibles :
 *   \$this->makeMiddleware(MiddlewareClass::class) : instancie le middleware
 *   \$this->assertMiddlewarePasses(\$middleware) : vérifie qu'il retourne true
 *   \$this->assertMiddlewareBlocks(\$middleware) : vérifie qu'il retourne false ou lève une exception
 *   \$this->assertMiddlewareBlocksWithCode(\$middleware, 403) : vérifie le code HTTP
 *   \$this->swap(ServiceClass::class, \$mock) : remplace un service (ex: simuler un user connecté)
 */
class {$testName} extends MiddlewareTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_middleware_passes_when_authenticated(): void
    {
        // Exemple : simuler un utilisateur connecté et vérifier que le middleware passe
        // \$authMock = \$this->createMock(\\Neo\\Core\\Security\\Auth\\AuthManager::class);
        // \$authMock->method('check')->willReturn(true);
        // \$this->swap(\\Neo\\Core\\Security\\Auth\\AuthManager::class, \$authMock);
        //
        // \$middleware = \$this->makeMiddleware(
        //     \\Neo\\Src\\{$project}\\App\\Middlewares\\AuthMiddleware::class
        // );
        // \$this->assertMiddlewarePasses(\$middleware);

        \$this->assertTrue(true);
    }

    public function test_middleware_blocks_when_unauthenticated(): void
    {
        // Exemple : simuler un utilisateur non connecté et vérifier le blocage
        // \$authMock = \$this->createMock(\\Neo\\Core\\Security\\Auth\\AuthManager::class);
        // \$authMock->method('check')->willReturn(false);
        // \$this->swap(\\Neo\\Core\\Security\\Auth\\AuthManager::class, \$authMock);
        //
        // \$middleware = \$this->makeMiddleware(
        //     \\Neo\\Src\\{$project}\\App\\Middlewares\\AuthMiddleware::class
        // );
        // \$this->assertMiddlewareBlocksWithCode(\$middleware, 403);

        \$this->assertTrue(true);
    }
}
PHP;
    }

    private function ensureTestsBootstrap(string $basePath, string $project): void
    {
        $bootstrapPath = "$basePath/Tests/bootstrap.php";

        if (file_exists($bootstrapPath)) {
            return;
        }

        if (!is_dir("$basePath/Tests")) {
            mkdir("$basePath/Tests", 0777, true);
        }

        $configDir = "$basePath/Tests/Config";
        if (!is_dir($configDir)) {
            mkdir($configDir, 0777, true);
        }

        $dbConfigPath = "$configDir/database.config.test.php";
        if (!file_exists($dbConfigPath)) {
            $dbConfigContent = <<<PHP
<?php
/**
 * Configuration de base de données pour les tests — projet : {$project}
 *
 * Ce fichier surcharge database.config.php uniquement pendant les tests.
 * Utilisez une base de données dédiée aux tests pour éviter de polluer
 * vos données de développement.
 *
 * Les transactions sont automatiquement rollbackées après chaque test
 * (DatabaseTestCase), la base ne sera donc jamais modifiée en permanence.
 */
return [
    'enabled' => true,
    'use' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'  => 'mysql',
            'host'    => 'localhost',
            'dbname'  => 'dbName_test',
            'user'    => 'root',
            'pass'    => '',
            'charset' => 'utf8mb4',
        ],
    ],
];
PHP;
            file_put_contents($dbConfigPath, $dbConfigContent);
            echo "Fichier généré : src/$project/Tests/Config/database.config.test.php\n";
        }

        $content = <<<PHP
<?php
declare(strict_types=1);

/**
 * Bootstrap de test NeoPHP — projet : {$project}
 *
 * Chargé automatiquement par PHPUnit avant les tests.
 * Initialise le framework en mode test, injecte le nom du projet
 * et surcharge les configs via Tests/Config/ si ce dossier existe.
 * Synchronise également le schéma de la BDD dev → BDD test.
 */

define('ROOT_DIR', dirname(__DIR__, 3));

require_once ROOT_DIR . '/vendor/autoload.php';

\$GLOBALS['_NEO_TEST_PROJECT'] = '{$project}';

\$_SERVER['SERVER_NAME'] = 'localhost';
\$_SERVER['SERVER_PORT'] = '80';
\$_SERVER['HTTP_HOST']   = 'localhost';

\$testConfigsPath = __DIR__ . '/Config';

if (is_dir(\$testConfigsPath)) {
    \$GLOBALS['_NEO_TEST_CONFIGS_PATH'] = \$testConfigsPath;
}

// — Synchronisation du schéma dev → test (une seule fois avant toute la suite) —
(static function (): void {
    \$app = new \\Neo\\App();
    \$container = \$app->getContainer();
    \$container->get(\\Neo\\Core\\Database\\DatabaseConnection::class);
    \$testPdo = \\Neo\\Core\\Database\\DatabaseConnection::getPdo();

    \$configsPath = \$container->get('configsPath');
    \$devConfig = require \$configsPath . '/database.config.php';
    \$useDriver = \$devConfig['use'];
    \$connConfig = \$devConfig['connections'][\$useDriver];

    \$devDsn = sprintf(
        '%s:host=%s;dbname=%s;charset=%s',
        \$connConfig['driver'],
        \$connConfig['host'],
        \$connConfig['dbname'],
        \$connConfig['charset']
    );

    \$devPdo = new \\PDO(
        \$devDsn,
        \$connConfig['user'],
        \$connConfig['pass'],
        [
            \\PDO::ATTR_ERRMODE => \\PDO::ERRMODE_EXCEPTION,
            \\PDO::ATTR_DEFAULT_FETCH_MODE => \\PDO::FETCH_ASSOC,
        ]
    );

    \$tables = \$devPdo->query("SHOW TABLES")->fetchAll(\\PDO::FETCH_COLUMN);

    \$testPdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach (\$tables as \$table) {
        \$row       = \$devPdo->query("SHOW CREATE TABLE `\$table`")->fetch(\\PDO::FETCH_ASSOC);
        \$createSql = \$row['Create Table'];
        \$createSql = preg_replace('/^CREATE TABLE (`[^`]+`)/', 'CREATE TABLE IF NOT EXISTS \$1', \$createSql);
        \$testPdo->exec(\$createSql);
    }

    \$testPdo->exec('SET FOREIGN_KEY_CHECKS = 1');
})();
PHP;

        file_put_contents($bootstrapPath, $content);
        echo "Fichier généré : src/$project/Tests/bootstrap.php\n";
    }

    private function ensurePhpUnitXml(string $basePath, string $project): void
    {
        $xmlPath = "$basePath/Tests/phpunit.xml";

        foreach (['Unit', 'Feature', 'Database', 'Middleware'] as $dir) {
            $dirPath = "$basePath/Tests/$dir";
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0777, true);
            }
        }

        if (file_exists($xmlPath)) {
            return;
        }

        $content = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.0/phpunit.xsd"
    bootstrap="bootstrap.php"
    colors="true"
    stopOnFailure="false"
>
    <testsuites>
        <testsuite name="{$project} Tests">
            <directory>Unit</directory>
            <directory>Feature</directory>
            <directory>Database</directory>
            <directory>Middleware</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <report>
            <html outputDirectory="../Storage/reports/coverage"/>
        </report>
    </coverage>

    <source>
        <include>
            <directory suffix=".php">../App</directory>
            <directory suffix=".php">../Model</directory>
            <directory suffix=".php">../Repository</directory>
        </include>
    </source>

    <logging>
        <junit outputFile="../Storage/reports/junit.xml"/>
    </logging>
</phpunit>
XML;

        file_put_contents($xmlPath, $content);
        echo "Fichier généré : src/$project/Tests/phpunit.xml\n";
    }

    private function normalizeTestName(string $input): string
    {
        $input = preg_replace('/[^a-zA-Z0-9]+/', ' ', $input);
        $input = str_replace(' ', '', ucwords($input));

        if (!str_ends_with($input, 'Test')) {
            $input .= 'Test';
        }

        return $input;
    }

    private function hasFlag(array $args, string $flag): bool
    {
        return in_array($flag, $args, true);
    }

    private function getOption(array $args, string $option): ?string
    {
        $count = count($args);

        for ($i = 0; $i < $count; $i++) {
            if (str_starts_with($args[$i], $option . '=')) {
                return explode('=', $args[$i], 2)[1];
            }

            if ($args[$i] === $option && isset($args[$i + 1])) {
                return $args[$i + 1];
            }
        }

        return null;
    }
}
