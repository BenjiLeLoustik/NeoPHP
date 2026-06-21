<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests;

use Neo\Core\Extension\Json\JsonExtension;
use PHPUnit\Framework\TestCase;

final class JsonExtensionTest extends TestCase
{
    private JsonExtension $json;

    protected function setUp(): void
    {
        $this->json = new JsonExtension();
    }

    public function testEncode(): void
    {
        self::assertSame('{"foo":"bar"}', $this->json->encode(['foo' => 'bar']));
    }

    public function testDecode(): void
    {
        self::assertSame(['foo' => 'bar'], $this->json->decode('{"foo":"bar"}'));
    }

    public function testIsValid(): void
    {
        self::assertTrue($this->json->isValid('{"foo":"bar"}'));
        self::assertFalse($this->json->isValid('not json'));
    }

    public function testGet(): void
    {
        self::assertSame('baz', $this->json->get('{"a":{"b":"baz"}}', 'a.b'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        self::assertSame('default', $this->json->get('{}', 'missing', 'default'));
    }

    public function testHas(): void
    {
        self::assertTrue($this->json->has('{"foo":"bar"}', 'foo'));
        self::assertFalse($this->json->has('{}', 'foo'));
    }

    public function testKeys(): void
    {
        self::assertSame(['a', 'b'], $this->json->keys('{"a":1,"b":2}'));
    }

    public function testMerge(): void
    {
        $result = $this->json->merge('{"a":1}', '{"b":2}');
        self::assertSame(['a' => 1, 'b' => 2], $this->json->decode($result));
    }

    public function testFlatten(): void
    {
        $result = $this->json->flatten('{"a":{"b":"c"}}');
        self::assertSame(['a.b' => 'c'], $result);
    }

    public function testToArray(): void
    {
        self::assertSame(['foo' => 'bar'], $this->json->toArray('{"foo":"bar"}'));
    }
}