<?php
declare(strict_types=1);

namespace Neo\Core\Testing\Scaffold;

class TestScaffolder
{
    public function ensure(string $basePath, string $project): void
    {
        $this->ensureTestsBootstrap($basePath, $project);
        $this->ensurePhpUnitXml($basePath, $project);
    }

    public function ensureTestsBootstrap(string $basePath, string $project): void
    {
        $bootstrapPath = "$basePath/Tests/bootstrap.php";

        if (file_exists($bootstrapPath)) {
            return;
        }

        if (!is_dir("$basePath/Tests")) {
            mkdir("$basePath/Tests", 0777, true);
        }

        $configDir = "$basePath/Tests/ConfigManager";
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
    'use' => 'default',
    'connections' => [
        'default' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'dbName_test',
            'user' => 'root',
            'pass' => '',
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

    public function ensurePhpUnitXml(string $basePath, string $project): void
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
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/12.0/phpunit.xsd"
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
}