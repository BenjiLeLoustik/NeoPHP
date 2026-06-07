<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Tests;

use Neo\Core\Cron\CronScanner;
use PHPUnit\Framework\TestCase;

class CronScannerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/neo_cron_scanner_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function writeCronClass(string $className, string $expression = '* * * * *', string $description = 'Test', bool $lock = false, string $timezone = 'UTC'): void
    {
        $lockStr = $lock ? 'true' : 'false';
        $content = <<<PHP
<?php
declare(strict_types=1);

use Neo\Core\Cron\Attribute\Cron;

final class {$className}
{
    #[Cron(expression: '{$expression}', description: '{$description}', timezone: '{$timezone}', lock: {$lockStr})]
    public function handle(): void {}
}
PHP;
        file_put_contents($this->tmpDir . "/{$className}.php", $content);
    }

    public function testScanReturnsEmptyArrayWhenDirectoryDoesNotExist(): void
    {
        $scanner = new CronScanner();

        $this->assertSame([], $scanner->scan('/nonexistent/path'));
    }

    public function testScanReturnsEmptyArrayOnEmptyDirectory(): void
    {
        $scanner = new CronScanner();

        $this->assertSame([], $scanner->scan($this->tmpDir));
    }

    public function testScanDetectsOneCronJob(): void
    {
        $this->writeCronClass('ScanOneCron');

        $scanner = new CronScanner();
        $jobs = $scanner->scan($this->tmpDir);

        $this->assertCount(1, $jobs);
    }

    public function testScanJobHasExpectedKeys(): void
    {
        $this->writeCronClass('ScanKeysCron');

        $scanner = new CronScanner();
        $jobs    = $scanner->scan($this->tmpDir);

        $this->assertArrayHasKey('class', $jobs[0]);
        $this->assertArrayHasKey('method', $jobs[0]);
        $this->assertArrayHasKey('expression', $jobs[0]);
        $this->assertArrayHasKey('description', $jobs[0]);
        $this->assertArrayHasKey('timezone', $jobs[0]);
        $this->assertArrayHasKey('lock', $jobs[0]);
    }

    public function testScanJobExpressionIsCorrect(): void
    {
        $this->writeCronClass('ScanExprCron', '0 0 * * *');

        $scanner = new CronScanner();
        $jobs = $scanner->scan($this->tmpDir);

        $this->assertSame('0 0 * * *', $jobs[0]['expression']);
    }

    public function testScanJobDescriptionIsCorrect(): void
    {
        $this->writeCronClass('ScanDescCron', '* * * * *', 'My description');

        $scanner = new CronScanner();
        $jobs = $scanner->scan($this->tmpDir);

        $this->assertSame('My description', $jobs[0]['description']);
    }

    public function testScanJobTimezoneIsCorrect(): void
    {
        $this->writeCronClass('ScanTzCron', '* * * * *', 'tz', false, 'Europe/Paris');

        $scanner = new CronScanner();
        $jobs = $scanner->scan($this->tmpDir);

        $this->assertSame('Europe/Paris', $jobs[0]['timezone']);
    }

    public function testScanJobLockIsCorrect(): void
    {
        $this->writeCronClass('ScanLockCron', '* * * * *', 'lock', true);

        $scanner = new CronScanner();
        $jobs = $scanner->scan($this->tmpDir);

        $this->assertTrue($jobs[0]['lock']);
    }

    public function testScanJobMethodIsCorrect(): void
    {
        $this->writeCronClass('ScanMethodCron');

        $scanner = new CronScanner();
        $jobs = $scanner->scan($this->tmpDir);

        $this->assertSame('handle', $jobs[0]['method']);
    }

    public function testScanIgnoresFilesWithoutCronAttribute(): void
    {
        file_put_contents($this->tmpDir . '/NoCron.php', '<?php final class NoCron { public function handle(): void {} }');

        $scanner = new CronScanner();

        $this->assertSame([], $scanner->scan($this->tmpDir));
    }

    public function testScanIgnoresNonPhpFiles(): void
    {
        file_put_contents($this->tmpDir . '/readme.txt', 'hello');

        $scanner = new CronScanner();

        $this->assertSame([], $scanner->scan($this->tmpDir));
    }

    public function testScanDetectsMultipleJobsInSameClass(): void
    {
        $content = <<<'PHP'
<?php
declare(strict_types=1);

use Neo\Core\Cron\Attribute\Cron;

final class MultiMethodCron
{
    #[Cron(expression: '* * * * *', description: 'First')]
    public function first(): void {}

    #[Cron(expression: '0 * * * *', description: 'Second')]
    public function second(): void {}
}
PHP;
        file_put_contents($this->tmpDir . '/MultiMethodCron.php', $content);

        $scanner = new CronScanner();
        $jobs = $scanner->scan($this->tmpDir);

        $this->assertCount(2, $jobs);
    }

    public function testScanDetectsJobsAcrossMultipleFiles(): void
    {
        $this->writeCronClass('ScanMultiA_' . uniqid('', false));
        $this->writeCronClass('ScanMultiB_' . uniqid('', false));

        $scanner = new CronScanner();
        $jobs = $scanner->scan($this->tmpDir);

        $this->assertCount(2, $jobs);
    }

    public function testScanScansSubdirectories(): void
    {
        $subDir = $this->tmpDir . '/sub';
        mkdir($subDir, 0777, true);

        $content = <<<'PHP'
<?php
declare(strict_types=1);

use Neo\Core\Cron\Attribute\Cron;

final class SubDirCron
{
    #[Cron(expression: '* * * * *', description: 'Sub')]
    public function handle(): void {}
}
PHP;
        file_put_contents($subDir . '/SubDirCron.php', $content);

        $scanner = new CronScanner();
        $jobs = $scanner->scan($this->tmpDir);

        $this->assertCount(1, $jobs);
    }
}