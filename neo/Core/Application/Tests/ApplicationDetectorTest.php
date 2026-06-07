<?php
declare(strict_types=1);

namespace Neo\Core\Application\Tests;

use Neo\Core\Application\ApplicationDetector;
use Neo\Core\Application\Exception\ApplicationException;
use Neo\Core\DI\Container;
use Neo\Core\Http\Request;
use PHPUnit\Framework\TestCase;

class ApplicationDetectorTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $registered = [];

    private function makeContainer(?Request $request = null): Container
    {
        $this->registered = [];

        $container = $this->createStub(Container::class);
        $container->method('get')->willReturnCallback(function ($key) use ($request) {
            if ($key === Request::class) {
                return $request;
            }
            return $this->registered[$key] ?? null;
        });
        $container->method('set')->willReturnCallback(function ($key, $value): void {
            $this->registered[$key] = $value;
        });

        return $container;
    }

    private function makeRequest(array $server): Request
    {
        $request = $this->createStub(Request::class);
        $request->method('getServer')->willReturn($server);
        return $request;
    }

    private function invokeDetectFromHttp(ApplicationDetector $detector): void
    {
        $ref = new \ReflectionClass($detector);
        $method = $ref->getMethod('detectFromHttp');
        $method->invoke($detector);
    }

    public function testDetectFromCliUsesTestProjectGlobal(): void
    {
        $GLOBALS['_NEO_TEST_PROJECT'] = 'TestApp';

        $detector = new ApplicationDetector($this->makeContainer());
        $detector->detect();

        $this->assertSame('TestApp', $this->registered['application']);

        unset($GLOBALS['_NEO_TEST_PROJECT']);
    }

    public function testDetectFromCliParsesProjectArg(): void
    {
        unset($GLOBALS['_NEO_TEST_PROJECT']);

        $backup = $GLOBALS['argv'] ?? [];
        $GLOBALS['argv'] = ['bin/neo', '--project=MyApp'];

        $detector = new ApplicationDetector($this->makeContainer());
        $detector->detect();

        $this->assertSame('MyApp', $this->registered['application']);

        $GLOBALS['argv'] = $backup;
    }

    public function testDetectFromCliThrowsWithoutProjectArg(): void
    {
        unset($GLOBALS['_NEO_TEST_PROJECT']);

        $backup = $GLOBALS['argv'] ?? [];
        $GLOBALS['argv'] = ['bin/neo', 'some:command'];

        $this->expectException(ApplicationException::class);

        try {
            $detector = new ApplicationDetector($this->makeContainer());
            $detector->detect();
        } finally {
            $GLOBALS['argv'] = $backup;
        }
    }

    public function testDetectFromHttpThrowsWhenNoServerName(): void
    {
        $request = $this->makeRequest([]);
        $detector = new ApplicationDetector($this->makeContainer($request));

        $this->expectException(ApplicationException::class);

        $this->invokeDetectFromHttp($detector);
    }

    private function assertServerStringInException(array $serverData, string $expected, ?string $notExpected = null): void
    {
        $request = $this->makeRequest($serverData);
        $detector = new ApplicationDetector($this->makeContainer($request));

        try {
            $this->invokeDetectFromHttp($detector);
            $this->assertNotEmpty($this->registered['application'] ?? null);
            $this->markTestSkipped('A project matched the access config — cannot assert exception message.');
        } catch (ApplicationException $e) {
            $this->assertStringContainsString($expected, $e->getMessage());
            if ($notExpected !== null) {
                $this->assertStringNotContainsString($notExpected, $e->getMessage());
            }
        }
    }

    public function testDetectFromHttpIncludesNonStandardPort(): void
    {
        $this->assertServerStringInException(
            ['SERVER_NAME' => 'neo-test-unlikely-host.local', 'SERVER_PORT' => '8080'],
            expected: 'neo-test-unlikely-host.local:8080'
        );
    }

    public function testDetectFromHttpIgnoresStandardPort80(): void
    {
        $this->assertServerStringInException(
            ['SERVER_NAME' => 'neo-test-unlikely-host.local', 'SERVER_PORT' => '80'],
            expected: 'neo-test-unlikely-host.local',
            notExpected: ':80'
        );
    }

    public function testDetectFromHttpIgnoresStandardPort443(): void
    {
        $this->assertServerStringInException(
            ['SERVER_NAME' => 'neo-test-unlikely-host.local', 'SERVER_PORT' => '443'],
            expected: 'neo-test-unlikely-host.local',
            notExpected: ':443'
        );
    }

    public function testDetectFromHttpUsesHttpHostAsFallback(): void
    {
        $this->assertServerStringInException(
            ['HTTP_HOST' => 'neo-test-unlikely-host.local'],
            expected: 'neo-test-unlikely-host.local'
        );
    }
}