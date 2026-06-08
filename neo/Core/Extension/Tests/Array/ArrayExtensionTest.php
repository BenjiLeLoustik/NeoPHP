<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests\Array;

use Neo\Core\Extension\Array\ArrayExtension;
use PHPUnit\Framework\TestCase;

class ArrayExtensionTest extends TestCase
{
    private ArrayExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ArrayExtension();
    }

    public function testGetReturnsValueOrNestedValueOrDefault(): void
    {
        $array = [
            'simple' => 'value',
            'nested' => [
                'target' => 'found'
            ]
        ];

        self::assertSame('value', $this->extension->get($array, 'simple'));
        self::assertSame('found', $this->extension->get($array, 'nested.target'));
        self::assertSame('fallback', $this->extension->get($array, 'missing', 'fallback'));
        self::assertSame('fallback', $this->extension->get($array, 'nested.missing', 'fallback'));
    }

    public function testHasValidatesPresenceOfKey(): void
    {
        $array = ['foo' => 'bar', 'nested' => ['baz' => null]];

        self::assertTrue($this->extension->has($array, 'foo'));
        self::assertTrue($this->extension->has($array, 'nested.baz'));
        self::assertFalse($this->extension->has($array, 'missing'));
        self::assertFalse($this->extension->has($array, 'nested.missing'));
    }

    public function testFirstAndLastReturnCorrectElements(): void
    {
        $array = ['a', 'b', 'c'];

        self::assertSame('a', $this->extension->first($array));
        self::assertSame('c', $this->extension->last($array));
        self::assertSame('default', $this->extension->first([], 'default'));
        self::assertSame('default', $this->extension->last([], 'default'));
    }

    public function testMapFilterReduceAndEach(): void
    {
        $array = [1, 2, 3];

        self::assertSame([2, 4, 6], $this->extension->map($array, fn($v) => $v * 2));
        self::assertSame([2, 3], $this->extension->filter($array, fn($v) => $v > 1));
        self::assertSame(6, $this->extension->reduce($array, fn($acc, $v) => $acc + $v, 0));

        $triggered = 0;
        $this->extension->each($array, function($v) use (&$triggered) {
            $triggered += $v;
        });
        self::assertSame(6, $triggered);
    }

    public function testDiffIntersectAndMerge(): void
    {
        $a = [1, 2, 3];
        $b = [2, 3, 4];

        self::assertSame([1], $this->extension->diff($a, $b));
        self::assertSame([2, 3], $this->extension->intersect($a, $b));
        self::assertSame([1, 2, 3, 2, 3, 4], $this->extension->merge($a, $b));
    }
}