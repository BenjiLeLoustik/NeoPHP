<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Tests;

use Neo\Core\Cron\Scanner\CronScanner;
use PHPUnit\Framework\TestCase;
use ReflectionException;

final class CronScannerTest extends TestCase
{
    private string $tmpDir;
    private CronScanner $scanner;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/neo-cron-test-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->scanner = new CronScanner();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tmpDir);
    }

    /**
     * @throws ReflectionException
     */
    public function testScanReturnsEmptyArrayWhenDirectoryDoesNotExist(): void
    {
        $jobs = $this->scanner->scan('/non/existent/path');

        self::assertSame([], $jobs);
    }

    /**
     * @throws ReflectionException
     */
    public function testScanReturnsEmptyArrayWhenNoPhpFilesPresent(): void
    {
        file_put_contents($this->tmpDir . '/readme.txt', 'hello');

        $jobs = $this->scanner->scan($this->tmpDir);

        self::assertSame([], $jobs);
    }

    /**
     * @throws ReflectionException
     */
    public function testScanReturnsEmptyArrayWhenClassHasNoCronMethods(): void
    {
        file_put_contents($this->tmpDir . '/NoCron.php', <<<PHP
<?php
namespace Neo\Core\Cron\Tests\Dynamic;

class NoCronClass
{
    public function doSomething(): void {}
}
PHP);

        $jobs = $this->scanner->scan($this->tmpDir);

        self::assertSame([], $jobs);
    }

    /**
     * @throws ReflectionException
     */
    public function testScanDetectsSingleCronMethod(): void
    {
        file_put_contents($this->tmpDir . '/SingleCron.php', <<<PHP
<?php
namespace Neo\Core\Cron\Tests\Dynamic;

use Neo\Core\Cron\Attribute\Cron;

class SingleCronClass
{
    #[Cron(expression: '* * * * *', description: 'Every minute')]
    public function tick(): void {}
}
PHP);

        $jobs = $this->scanner->scan($this->tmpDir);

        self::assertCount(1, $jobs);
        self::assertSame('Neo\Core\Cron\Tests\Dynamic\SingleCronClass', $jobs[0]['class']);
        self::assertSame('tick', $jobs[0]['method']);
        self::assertSame('* * * * *', $jobs[0]['expression']);
        self::assertSame('Every minute', $jobs[0]['description']);
        self::assertSame('UTC', $jobs[0]['timezone']);
        self::assertFalse($jobs[0]['lock']);
    }

    /**
     * @throws ReflectionException
     */
    public function testScanDetectsMultipleCronMethodsInSameClass(): void
    {
        file_put_contents($this->tmpDir . '/MultiCron.php', <<<PHP
<?php
namespace Neo\Core\Cron\Tests\Dynamic;

use Neo\Core\Cron\Attribute\Cron;

class MultiCronClass
{
    #[Cron(expression: '* * * * *', description: 'First')]
    public function first(): void {}

    #[Cron(expression: '0 * * * *', description: 'Second')]
    public function second(): void {}
}
PHP);

        $jobs = $this->scanner->scan($this->tmpDir);

        self::assertCount(2, $jobs);

        $methods = array_column($jobs, 'method');
        self::assertContains('first', $methods);
        self::assertContains('second', $methods);
    }

    public function testScanRespectsCronAttributeOptions(): void
    {
        file_put_contents($this->tmpDir . '/OptionsCron.php', <<<PHP
<?php
namespace Neo\Core\Cron\Tests\Dynamic;

use Neo\Core\Cron\Attribute\Cron;

class OptionsCronClass
{
    #[Cron(expression: '30 8 * * 1', description: 'Weekly report', timezone: 'Europe/Paris', lock: true)]
    public function weeklyReport(): void {}
}
PHP);

        $jobs = $this->scanner->scan($this->tmpDir);

        self::assertCount(1, $jobs);
        self::assertSame('30 8 * * 1', $jobs[0]['expression']);
        self::assertSame('Weekly report', $jobs[0]['description']);
        self::assertSame('Europe/Paris', $jobs[0]['timezone']);
        self::assertTrue($jobs[0]['lock']);
    }

    /**
     * @throws ReflectionException
     */
    public function testScanSkipsFilesWithNoClassDeclaration(): void
    {
        file_put_contents($this->tmpDir . '/helpers.php', <<<PHP
<?php
namespace Neo\Core\Cron\Tests\Dynamic;

function helperFunction(): void {}
PHP);

        $jobs = $this->scanner->scan($this->tmpDir);

        self::assertSame([], $jobs);
    }

    /**
     * @throws ReflectionException
     */
    public function testScanIgnoresNonPublicMethods(): void
    {
        file_put_contents($this->tmpDir . '/PrivateCron.php', <<<PHP
<?php
namespace Neo\Core\Cron\Tests\Dynamic;

use Neo\Core\Cron\Attribute\Cron;

class PrivateCronClass
{
    #[Cron(expression: '* * * * *', description: 'Private')]
    private function secretTask(): void {}
}
PHP);

        $jobs = $this->scanner->scan($this->tmpDir);

        self::assertSame([], $jobs);
    }
}