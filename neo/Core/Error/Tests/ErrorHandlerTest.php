<?php
declare(strict_types=1);

namespace Neo\Core\Error\Tests;

use Neo\Core\DI\Container;
use Neo\Core\Error\ErrorHandler;
use Neo\Core\Error\Exception\FrameworkException;
use PHPUnit\Framework\TestCase;

class ErrorHandlerTest extends TestCase
{
    private function makeContainer(): Container
    {
        $container = new Container();
        $container->set('storagePath', sys_get_temp_dir());
        $container->set('viewsPath', sys_get_temp_dir());
        return $container;
    }

    private function makeHandler(string $env = 'prod'): ErrorHandler
    {
        $handler = new ErrorHandler($this->makeContainer());
        $handler->setEnv($env);
        return $handler;
    }

    private function renderHtml(ErrorHandler $handler, FrameworkException $exception): string
    {
        $getEnv = new \ReflectionMethod(ErrorHandler::class, 'getEnv');
        $env = (string) $getEnv->invoke($handler);

        $render = new \ReflectionMethod(ErrorHandler::class, 'renderFallbackHtml');
        return (string) $render->invoke(null, $exception, $env);
    }

    private function renderHtmlWith(string $env, FrameworkException $exception): string
    {
        return $this->renderHtml($this->makeHandler($env), $exception);
    }

    public function testHandleErrorReturnsTrueWhenSuppressed(): void
    {
        $handler = $this->makeHandler();
        $old = error_reporting(0);
        try {
            $result = $handler->handleError(E_USER_ERROR, 'suppressed', __FILE__, __LINE__);
            $this->assertTrue($result);
        } finally {
            error_reporting($old);
        }
    }

    public function testHandleExceptionOutputsHtml(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('Test Error', 'Something went wrong', 500));

        $this->assertStringContainsString('500', $output);
        $this->assertStringContainsString('Test Error', $output);
    }

    public function testHandleExceptionInDevShowsMessage(): void
    {
        $output = $this->renderHtmlWith('dev', new FrameworkException('Dev Error', 'Detailed dev message', 500));

        $this->assertStringContainsString('Detailed dev message', $output);
    }

    public function testHandleExceptionInProdHidesMessage(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('Prod Error', 'Internal secret details', 500));

        $this->assertStringNotContainsString('Internal secret details', $output);
        $this->assertStringContainsString('An internal error has occurred', $output);
    }

    public function testHandleExceptionWrapsGenericThrowable(): void
    {
        $exception = FrameworkException::fromThrowable(new \RuntimeException('raw runtime error', 500));
        $output = $this->renderHtmlWith('prod', $exception);

        $this->assertStringContainsString('500', $output);
    }

    public function testHandleException404ShowsNotFoundMessage(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('Not Found', 'Page missing', 404));

        $this->assertStringContainsString('404', $output);
        $this->assertStringContainsString('could not be found', $output);
    }

    public function testHandleException403ShowsForbiddenMessage(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('Forbidden', 'Forbidden access', 403));

        $this->assertStringContainsString('403', $output);
        $this->assertStringContainsString('permission', $output);
    }

    public function testHandleException401ShowsUnauthorizedMessage(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('Unauthorized', 'Auth required', 401));

        $this->assertStringContainsString('401', $output);
        $this->assertStringContainsString('authenticated', $output);
    }

    public function testHandleException405ShowsMethodNotAllowedMessage(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('Method Not Allowed', 'bad method', 405));

        $this->assertStringContainsString('405', $output);
        $this->assertStringContainsString('method', $output);
    }

    public function testHandleException419ShowsSessionExpiredMessage(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('Session Expired', 'expired', 419));

        $this->assertStringContainsString('419', $output);
        $this->assertStringContainsString('session', $output);
    }

    public function testHandleException422ShowsInvalidDataMessage(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('Unprocessable', 'invalid payload', 422));

        $this->assertStringContainsString('422', $output);
        $this->assertStringContainsString('invalid', $output);
    }

    public function testHandleException429ShowsRateLimitMessage(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('Too Many Requests', 'Rate limit exceeded', 429));

        $this->assertStringContainsString('429', $output);
        $this->assertStringContainsString('Too many requests', $output);
    }

    public function testHandleExceptionInDevShowsStackTrace(): void
    {
        $output = $this->renderHtmlWith('dev', new FrameworkException('Trace Test', 'some message', 500));

        $this->assertStringContainsString('Stack trace', $output);
    }

    public function testHandleExceptionInDevShowsContext(): void
    {
        $output = $this->renderHtmlWith('dev', new FrameworkException('Context Test', 'msg', 500, ['key' => 'value']));

        $this->assertStringContainsString('Contexte', $output);
    }

    public function testHandleExceptionInProdHidesContext(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('Context Test', 'msg', 500, ['secret' => 'data']));

        $this->assertStringNotContainsString('secret', $output);
    }

    public function testHandleExceptionOutputsValidHtmlStructure(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('HTML Test', 'msg', 500));

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('<html', $output);
        $this->assertStringContainsString('</html>', $output);
    }

    public function testHandleExceptionEscapesXssInTitle(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('<script>alert(1)</script>', 'msg', 500));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testHandleExceptionDevEscapesXssInMessage(): void
    {
        $output = $this->renderHtmlWith('dev', new FrameworkException('Title', '<img src=x onerror=alert(1)>', 500));

        $this->assertStringNotContainsString('<img src=x', $output);
    }

    public function testHandleExceptionUsesCodeZeroAsFiveHundred(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('No Code', 'msg', 0));

        $this->assertStringContainsString('500', $output);
    }

    public function testHandleExceptionContainsNeoPHPBranding(): void
    {
        $output = $this->renderHtmlWith('prod', new FrameworkException('Brand Test', 'msg', 500));

        $this->assertStringContainsString('NeoPHP', $output);
    }

    public function testSetEnvIsReflectedInOutput(): void
    {
        $handler = $this->makeHandler('dev');
        $output = $this->renderHtml($handler, new FrameworkException('Env Test', 'msg', 500));

        $this->assertStringContainsString('dev', $output);
    }
}