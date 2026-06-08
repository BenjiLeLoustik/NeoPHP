<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests\Json;

use Neo\Core\Extension\Json\JsonExtension;
use PHPUnit\Framework\TestCase;

class JsonExtensionTest extends TestCase
{
    private JsonExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new JsonExtension();
    }

    public function testEncodeAndDecode(): void
    {
        $data = ['name' => 'Neo', 'tags' => ['framework', 'php']];
        $json = $this->extension->encode($data);

        self::assertIsString($json);
        self::assertTrue($this->extension->isValid($json));
        self::assertSame($data, $this->extension->decode($json));
        self::assertFalse($this->extension->isValid('invalid-json'));
    }

    public function testGetAndHasAndKeysWithDotNotation(): void
    {
        $json = '{"user":{"profile":{"id":42,"role":"admin"}}}';

        self::assertTrue($this->extension->has($json, 'user.profile.id'));
        self::assertSame(42, $this->extension->get($json, 'user.profile.id'));
        self::assertSame('admin', $this->extension->get($json, 'user.profile.role'));
        self::assertSame('default', $this->extension->get($json, 'user.missing', 'default'));
        self::assertSame(['user'], $this->extension->keys($json));
    }

    public function testFlattenArrayStructureAndDiff(): void
    {
        $json = '{"a":1,"b":{"c":2}}';
        $flattened = $this->extension->flatten($json);

        self::assertSame(['a' => 1, 'b.c' => 2], $flattened);

        $json1 = '{"x":1,"y":2}';
        $json2 = '{"x":1,"y":99}';
        self::assertSame(['y' => 99], $this->extension->diff($json1, $json2));
    }
}