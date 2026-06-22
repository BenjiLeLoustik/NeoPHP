<?php
declare(strict_types=1);

namespace Neo\Core\Translation\Tests;

use Neo\Core\Translation\TranslationRegistry;
use PHPUnit\Framework\TestCase;

class TranslationRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        $ref = new \ReflectionProperty(TranslationRegistry::class, 'paths');
        $ref->setValue(null, []);
    }

    public function testRegisterPathTrimsTrailingSlash(): void
    {
        $path = sys_get_temp_dir() . '/neo-translation-registry-' . uniqid();

        TranslationRegistry::registerPath($path . '/');

        self::assertContains($path, TranslationRegistry::getPaths());
    }

    public function testGetPathsReturnsRegisteredPath(): void
    {
        $path = sys_get_temp_dir() . '/neo-translation-registry-' . uniqid();

        TranslationRegistry::registerPath($path);

        self::assertContains($path, TranslationRegistry::getPaths());
    }
}