<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Tests;

use Neo\Core\Cron\Exception\CronException;
use Neo\Core\Cron\Runner\CronRunner;
use Neo\Core\Cron\Tests\Fixture\FailingJob;
use Neo\Core\Cron\Tests\Fixture\SecondJob;
use Neo\Core\Cron\Tests\Fixture\SelfTrackingCronJob;
use Neo\Core\Cron\Tests\Fixture\SimpleJob;
use Neo\Core\DI\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionException;

final class CronRunnerTest extends TestCase
{
    private CronRunner $runner;
    private Container $container;

    protected function setUp(): void
    {
        $this->container = $this->createMock(Container::class);
        $this->runner = new CronRunner($this->container);

        SimpleJob::$calls = [];
        FailingJob::$called = false;
        SecondJob::$called  = false;
    }

    private function alwaysDueJob(string $class, string $method, bool $lock = false): array
    {
        return [
            'expression' => '* * * * *',
            'timezone' => 'UTC',
            'lock' => $lock,
            'class' => $class,
            'method' => $method,
        ];
    }

    private function neverDueJob(string $class, string $method): array
    {
        return [
            'expression' => '61 * * * *',
            'timezone' => 'UTC',
            'lock' => false,
            'class' => $class,
            'method' => $method,
        ];
    }

    /**
     * @throws CronException
     */
    public function testRunExecutesDueJob(): void
    {
        $this->container->method('get')->willReturn(new SimpleJob());

        $this->runner->run([$this->alwaysDueJob(SimpleJob::class, 'run')]);

        self::assertSame(['simple'], SimpleJob::$calls);
    }

    /**
     * @throws CronException
     */
    public function testRunSkipsJobThatIsNotDue(): void
    {
        $this->container->method('get')->willReturn(new SimpleJob());

        $this->runner->run([$this->neverDueJob(SimpleJob::class, 'run')]);

        self::assertSame([], SimpleJob::$calls);
    }

    /**
     * @throws CronException
     */
    public function testRunExecutesMultipleDueJobs(): void
    {
        $this->container
            ->method('get')
            ->willReturnMap([
                [SimpleJob::class, new SimpleJob()],
                [SecondJob::class, new SecondJob()],
            ]);

        $this->runner->run([
            $this->alwaysDueJob(SimpleJob::class, 'run'),
            $this->alwaysDueJob(SecondJob::class, 'run'),
        ]);

        self::assertContains('simple', SimpleJob::$calls);
        self::assertTrue(SecondJob::$called);
    }

    /**
     * @throws CronException
     */
    public function testRunContinuesAfterJobThrows(): void
    {
        $this->container
            ->method('get')
            ->willReturnMap([
                [FailingJob::class, new FailingJob()],
                [SecondJob::class,  new SecondJob()],
            ]);

        $this->runner->run([
            $this->alwaysDueJob(FailingJob::class, 'run'),
            $this->alwaysDueJob(SecondJob::class,  'run'),
        ]);

        self::assertTrue(SecondJob::$called);
    }

    /**
     * @throws CronException
     */
    public function testRunInstantiatesClassDirectlyWhenContainerThrows(): void
    {
        $this->container
            ->method('get')
            ->willThrowException(new \RuntimeException('not bound'));

        SelfTrackingCronJob::$called = false;

        $this->runner->run([$this->alwaysDueJob(SelfTrackingCronJob::class, 'execute')]);

        self::assertTrue(SelfTrackingCronJob::$called);
    }

    /**
     * @throws CronException
     */
    public function testRunWithEmptyJobListDoesNothing(): void
    {
        $this->container->expects(self::never())->method('get');

        $this->runner->run([]);
    }

    /**
     * @throws CronException
     */
    public function testRunCreatesAndRemovesLockFile(): void
    {
        $job = new SimpleJob();
        $jobArray = $this->alwaysDueJob(SimpleJob::class, 'run', lock: true);
        $lockFile = sys_get_temp_dir() . '/neo_cron_' . md5($jobArray['class'] . $jobArray['method']) . '.lock';

        $this->container->method('get')->willReturn($job);

        $this->runner->run([$jobArray]);

        self::assertFileDoesNotExist($lockFile);
    }

    public function testRunSkipsJobWhenLockFileAlreadyExists(): void
    {
        $jobArray = $this->alwaysDueJob(SimpleJob::class, 'run', lock: true);
        $lockFile = sys_get_temp_dir() . '/neo_cron_' . md5($jobArray['class'] . $jobArray['method']) . '.lock';
        touch($lockFile);

        try {
            $this->container->method('get')->willReturn(new SimpleJob());
            $this->runner->run([$jobArray]);
            self::assertSame([], SimpleJob::$calls);
        } catch (CronException $e) {
        } finally {
            @unlink($lockFile);
        }
    }

    /**
     * @throws CronException
     */
    public function testRunRemovesLockFileEvenWhenJobThrows(): void
    {
        $jobArray = $this->alwaysDueJob(FailingJob::class, 'run', lock: true);
        $lockFile = sys_get_temp_dir() . '/neo_cron_' . md5($jobArray['class'] . $jobArray['method']) . '.lock';

        $this->container->method('get')->willReturn(new FailingJob());

        $this->runner->run([$jobArray]);

        self::assertFileDoesNotExist($lockFile);
    }

    public function testRunThrowsCronExceptionForMalformedExpression(): void
    {
        try {
            $this->container->method('get')->willReturn(new SimpleJob());

            $this->runner->run([[
                'expression' => 'bad',
                'timezone' => 'UTC',
                'lock' => false,
                'class' => SimpleJob::class,
                'method' => 'run',
            ]]);

            self::fail('Expected CronException was not thrown.');
        } catch (CronException $e) {
            self::assertSame("Cron expression 'bad' is invalid. Expected 5 parts.", $e->getMessage());
        }
    }

    /**
     * @throws ReflectionException
     */
    #[DataProvider('matchesPartProvider')]
    public function testMatchesPart(string $part, int $value, bool $expected): void
    {
        $method = new \ReflectionMethod(CronRunner::class, 'matchesPart');
        $result = $method->invoke($this->runner, $part, $value);

        self::assertSame($expected, $result);
    }

    public static function matchesPartProvider(): array
    {
        return [
            'wildcard matches any' => ['*', 42, true],
            'exact match' => ['30', 30, true],
            'exact no match' => ['30', 29, false],
            'step matches' => ['*/15', 0, true],
            'step matches 15' => ['*/15', 15, true],
            'step matches 30' => ['*/15', 30, true],
            'step no match' => ['*/15', 7, false],
            'range matches lower bound' => ['10-20', 10, true],
            'range matches middle' => ['10-20', 15, true],
            'range matches upper bound' => ['10-20', 20, true],
            'range no match above' => ['10-20', 21, false],
            'list matches first' => ['5,10,55', 5, true],
            'list matches last' => ['5,10,55', 55, true],
            'list no match' => ['5,10,55', 6, false],
        ];
    }
}