<?php
declare(strict_types=1);

namespace Neo\Core\Http\Tests\Client\Flash;

use Neo\Core\Http\Client\Flash\Flash;
use Neo\Core\Http\Client\Flash\FlashViewExtension;
use PHPUnit\Framework\TestCase;

class FlashViewExtensionTest extends TestCase
{
    public function testFlashViewExtensionRegistersTwigFunction(): void
    {
        $flashMock = $this->createMock(Flash::class);
        $flashMock->method('render')->willReturn('<span class="flash-message">Hello</span>');

        $extension = new FlashViewExtension($flashMock);
        $functions = $extension->getFunctions();

        self::assertArrayHasKey('flashes', $functions);
        self::assertSame(['html'], $functions['flashes']['options']['is_safe']);
        self::assertEmpty($extension->getFilters());

        $callable = $functions['flashes']['callable'];
        self::assertSame('<span class="flash-message">Hello</span>', $callable());
    }
}