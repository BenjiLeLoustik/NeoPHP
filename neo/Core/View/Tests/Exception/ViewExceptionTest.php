<?php
declare(strict_types=1);

namespace Neo\Core\View\Tests\Exception;

use Neo\Core\Error\Exception\FrameworkException;
use Neo\Core\View\Exception\ViewException;
use PHPUnit\Framework\TestCase;

class ViewExceptionTest extends TestCase
{
    public function testViewExceptionInheritance(): void
    {
        $exception = new ViewException(
            title: 'Template Error',
            message: 'An error occurred during rendering',
            code: 500
        );

        self::assertInstanceOf(FrameworkException::class, $exception);
        self::assertSame('Template Error', $exception->getTitle());
        self::assertSame('An error occurred during rendering', $exception->getMessage());
        self::assertSame(500, $exception->getCode());
    }
}