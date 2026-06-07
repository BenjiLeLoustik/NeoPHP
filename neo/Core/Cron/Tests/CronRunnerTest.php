<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Tests;

use Neo\Core\Cron\CronRunner;
use Neo\Core\Cron\Exception\CronException;
use Neo\Core\DI\Container;
use PHPUnit\Framework\TestCase;

class CronRunnerTest extends TestCase
{
    private function makeRunner(Container $container): CronRunner
    {
        return new CronRunner($container);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makeJob(array $overrides = []): array
    {
        return array_merge([
            'class' => \stdClass::class,
            'method' => 'handle',
            'expression' => '* * * * *',
            'description' => 'Test job',
            'timezone' => 'UTC',
            'lock' => false,
        ], $overrides);
    }

    public function testRunExecutesDueJob(): void
    {
        $spy = new class { public bool $ran = false; public function handle(): void { $this->ran = true; } };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($spy);

        $this->makeRunner($container)->run([$this->makeJob(['class' => $spy::class])]);

        $this->assertTrue($spy->ran);
    }

    public function testRunSkipsJobNotDue(): void
    {
        $spy = new class { public bool $ran = false; public function handle(): void { $this->ran = true; } };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($spy);

        $this->makeRunner($container)->run([$this->makeJob([
            'class' => $spy::class,
            'expression' => '59 23 31 12 6',
        ])]);

        $this->assertFalse($spy->ran);
    }

    public function testRunThrowsOnInvalidExpression(): void
    {
        $this->expectException(CronException::class);

        $this->makeRunner($this->createStub(Container::class))->run([$this->makeJob(['expression' => 'bad'])]);
    }

    public function testRunHandlesJobExceptionGracefully(): void
    {
        $target = new class { public function handle(): void { throw new \RuntimeException('boom'); } };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($target);

        $this->expectNotToPerformAssertions();
        $this->makeRunner($container)->run([$this->makeJob(['class' => $target::class])]);
    }

    public function testRunCreatesAndDeletesLockFile(): void
    {
        $target = new class { public function handle(): void {} };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($target);

        $job = $this->makeJob(['class' => $target::class, 'lock' => true]);
        $lockFile = sys_get_temp_dir() . '/neo_cron_' . md5($job['class'] . $job['method']) . '.lock';

        $this->makeRunner($container)->run([$job]);

        $this->assertFileDoesNotExist($lockFile);
    }

    public function testRunSkipsJobWhenLockFileExists(): void
    {
        $spy = new class { public bool $ran = false; public function handle(): void { $this->ran = true; } };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($spy);

        $job = $this->makeJob(['class' => $spy::class, 'lock' => true]);
        $lockFile = sys_get_temp_dir() . '/neo_cron_' . md5($job['class'] . $job['method']) . '.lock';

        touch($lockFile);

        try {
            $this->makeRunner($container)->run([$job]);
            $this->assertFalse($spy->ran);
        } finally {
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }

    public function testRunDeletesLockFileEvenOnException(): void
    {
        $target = new class { public function handle(): void { throw new \RuntimeException('fail'); } };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($target);

        $job = $this->makeJob(['class' => $target::class, 'lock' => true]);
        $lockFile = sys_get_temp_dir() . '/neo_cron_' . md5($job['class'] . $job['method']) . '.lock';

        $this->makeRunner($container)->run([$job]);

        $this->assertFileDoesNotExist($lockFile);
    }

    public function testRunProcessesMultipleJobs(): void
    {
        $spy = new class { public int $count = 0; public function handle(): void { $this->count++; } };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($spy);

        $this->makeRunner($container)->run([
            $this->makeJob(['class' => $spy::class]),
            $this->makeJob(['class' => $spy::class]),
        ]);

        $this->assertSame(2, $spy->count);
    }

    public function testRunWithEmptyJobListDoesNothing(): void
    {
        $this->expectNotToPerformAssertions();
        $this->makeRunner($this->createStub(Container::class))->run([]);
    }

    public function testIsDueMatchesWildcardExpression(): void
    {
        $spy = new class { public bool $ran = false; public function handle(): void { $this->ran = true; } };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($spy);

        $this->makeRunner($container)->run([$this->makeJob(['class' => $spy::class, 'expression' => '* * * * *'])]);

        $this->assertTrue($spy->ran);
    }

    public function testIsDueMatchesStepExpression(): void
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $minute = (int) $now->format('i');

        if ($minute % 2 !== 0) {
            $this->markTestSkipped('Minute is odd — */2 step would not match right now.');
        }

        $spy = new class { public bool $ran = false; public function handle(): void { $this->ran = true; } };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($spy);

        $this->makeRunner($container)->run([$this->makeJob(['class' => $spy::class, 'expression' => '*/2 * * * *'])]);

        $this->assertTrue($spy->ran);
    }

    public function testIsDueMatchesRangeExpression(): void
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $minute = (int) $now->format('i');
        $from = max(0, $minute - 1);
        $to = min(59, $minute + 1);

        $spy = new class { public bool $ran = false; public function handle(): void { $this->ran = true; } };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($spy);

        $this->makeRunner($container)->run([$this->makeJob(['class' => $spy::class, 'expression' => "{$from}-{$to} * * * *"])]);

        $this->assertTrue($spy->ran);
    }

    public function testIsDueMatchesListExpression(): void
    {
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $minute = (int) $now->format('i');
        $other = ($minute + 1) % 60;

        $spy = new class { public bool $ran = false; public function handle(): void { $this->ran = true; } };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($spy);

        $this->makeRunner($container)->run([$this->makeJob(['class' => $spy::class, 'expression' => "{$minute},{$other} * * * *"])]);

        $this->assertTrue($spy->ran);
    }

    public function testIsDueRespectsTimezone(): void
    {
        $spy = new class { public bool $ran = false; public function handle(): void { $this->ran = true; } };

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturn($spy);

        $this->makeRunner($container)->run([$this->makeJob([
            'class' => $spy::class,
            'expression' => '* * * * *',
            'timezone' => 'Europe/Paris',
        ])]);

        $this->assertTrue($spy->ran);
    }
}