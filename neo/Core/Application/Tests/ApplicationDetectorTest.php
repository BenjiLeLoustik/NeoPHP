<?php
declare(strict_types=1);

namespace Neo\Core\Application\Tests;

use Neo\Core\Application\ApplicationDetector;
use Neo\Core\Application\Exception\ApplicationException;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use PHPUnit\Framework\TestCase;

final class ApplicationDetectorTest extends TestCase
{
    /** @var list<string> */
    private array $originalArgv;
    private bool $hadTestProjectGlobal;
    private mixed $originalTestProjectGlobal;

    protected function setUp(): void
    {
        global $argv;

        $this->originalArgv = $argv;
        $this->hadTestProjectGlobal = array_key_exists('_NEO_TEST_PROJECT', $GLOBALS);
        $this->originalTestProjectGlobal = $GLOBALS['_NEO_TEST_PROJECT'] ?? null;

        unset($GLOBALS['_NEO_TEST_PROJECT']);
    }

    protected function tearDown(): void
    {
        global $argv;

        $argv = $this->originalArgv;

        if ($this->hadTestProjectGlobal) {
            $GLOBALS['_NEO_TEST_PROJECT'] = $this->originalTestProjectGlobal;
        } else {
            unset($GLOBALS['_NEO_TEST_PROJECT']);
        }
    }

    /**
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function testDetectUsesTestProjectGlobalWhenSet(): void
    {
        $GLOBALS['_NEO_TEST_PROJECT'] = 'GlobalTestApp';

        global $argv;
        $argv = ['bin/neo'];
        $container = new Container();
        $detector = new ApplicationDetector($container);

        $detector->detect();

        self::assertSame('GlobalTestApp', $container->get('application'));
    }

    /**
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function testDetectParsesProjectOptionFromArgv(): void
    {
        global $argv;
        $argv = ['bin/neo', 'some:command', '--project=MyApp'];

        $container = new Container();
        $detector = new ApplicationDetector($container);

        $detector->detect();

        self::assertSame('MyApp', $container->get('application'));
    }

    public function testDetectThrowsWhenNoProjectIsProvided(): void
    {
        global $argv;
        $argv = ['bin/neo', 'some:command'];

        $container = new Container();
        $detector = new ApplicationDetector($container);

        $this->expectException(ApplicationException::class);

        $detector->detect();
    }
}