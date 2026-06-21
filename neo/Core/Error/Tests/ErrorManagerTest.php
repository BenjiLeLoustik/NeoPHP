<?php
declare(strict_types=1);

namespace Neo\Core\Error\Tests;

use Neo\Core\Error\ErrorManager;
use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\DI\Container;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionMethod;

final class ErrorManagerTest extends TestCase
{
    private Container $container;
    private ErrorManager $manager;

    protected function setUp(): void
    {
        $this->container = $this->createMock(Container::class);
        $this->manager = new ErrorManager($this->container);
    }

    /**
     * @throws ReflectionException
     */
    public function testSetEnvIsUsedInsteadOfContainer(): void
    {
        $this->container->expects(self::never())->method('get');

        $this->manager->setEnv('dev');

        $html = $this->renderFallbackHtml(new FrameworkException('Oops', 'msg', 500), 'dev');

        self::assertStringContainsString('dev', $html);
    }

    /**
     * @throws ReflectionException
     */
    public function testRenderFallbackHtmlContainsStatusCode(): void
    {
        $e = new FrameworkException('Not Found', 'Page missing', 404);
        $html = $this->renderFallbackHtml($e, 'prod');

        self::assertStringContainsString('404', $html);
    }

    /**
     * @throws ReflectionException
     */
    public function testRenderFallbackHtmlShowsMessageInDev(): void
    {
        $e = new FrameworkException('Error', 'Secret details', 500);
        $html = $this->renderFallbackHtml($e, 'dev');

        self::assertStringContainsString('Secret details', $html);
    }

    /**
     * @throws ReflectionException
     */
    public function testRenderFallbackHtmlHidesMessageInProd(): void
    {
        $e = new FrameworkException('Error', 'Secret details', 500);
        $html = $this->renderFallbackHtml($e, 'prod');

        self::assertStringNotContainsString('Secret details', $html);
    }

    /**
     * @throws ReflectionException
     */
    public function testRenderFallbackHtmlContainsTitle(): void
    {
        $e = new FrameworkException('My Title', 'msg', 500);
        $html = $this->renderFallbackHtml($e, 'prod');

        self::assertStringContainsString('My Title', $html);
    }

    /**
     * @throws ReflectionException
     */
    public function testRenderFallbackHtmlContainsStackTraceInDev(): void
    {
        $e = new FrameworkException('Error', 'msg', 500);
        $html = $this->renderFallbackHtml($e, 'dev');

        self::assertStringContainsString('Stack trace', $html);
    }

    /**
     * @throws ReflectionException
     */
    public function testRenderFallbackHtmlDoesNotContainStackTraceInProd(): void
    {
        $e = new FrameworkException('Error', 'msg', 500);
        $html = $this->renderFallbackHtml($e, 'prod');

        self::assertStringNotContainsString('Stack trace', $html);
    }

    /**
     * @throws ReflectionException
     */
    public function testDetectBootstrapEnvReturnsDevForLocalhost(): void
    {
        $_SERVER['SERVER_NAME'] = 'localhost';

        $env = $this->detectBootstrapEnv();

        self::assertSame('dev', $env);

        unset($_SERVER['SERVER_NAME']);
    }

    /**
     * @throws ReflectionException
     */
    public function testDetectBootstrapEnvReturnsProdForPublicHost(): void
    {
        $_SERVER['SERVER_NAME'] = 'example.com';

        $env = $this->detectBootstrapEnv();

        self::assertSame('prod', $env);

        unset($_SERVER['SERVER_NAME']);
    }

    /**
     * @throws ReflectionException
     */
    private function renderFallbackHtml(FrameworkException $e, string $env): string
    {
        $method = new ReflectionMethod(ErrorManager::class, 'renderFallbackHtml');
        return $method->invoke(null, $e, $env);
    }

    /**
     * @throws ReflectionException
     */
    private function detectBootstrapEnv(): string
    {
        $method = new ReflectionMethod(ErrorManager::class, 'detectBootstrapEnv');
        return $method->invoke(null);
    }
}