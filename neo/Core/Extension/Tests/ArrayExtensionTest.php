<?php
declare(strict_types=1);

namespace Neo\Core\Extension\Tests;

use Neo\Core\Extension\Array\ArrayExtension;
use PHPUnit\Framework\TestCase;

final class ArrayExtensionTest extends TestCase
{
    private ArrayExtension $arr;

    protected function setUp(): void
    {
        $this->arr = new ArrayExtension();
    }

    public function testGetReturnsValueByKey(): void
    {
        self::assertSame('bar', $this->arr->get(['foo' => 'bar'], 'foo'));
    }

    public function testGetReturnsDotNotationValue(): void
    {
        self::assertSame('baz', $this->arr->get(['a' => ['b' => 'baz']], 'a.b'));
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        self::assertSame('default', $this->arr->get([], 'missing', 'default'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        self::assertTrue($this->arr->has(['foo' => 'bar'], 'foo'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        self::assertFalse($this->arr->has([], 'foo'));
    }

    public function testFirstReturnsFirstElement(): void
    {
        self::assertSame(1, $this->arr->first([1, 2, 3]));
    }

    public function testFirstReturnsDefaultOnEmptyArray(): void
    {
        self::assertSame('x', $this->arr->first([], 'x'));
    }

    public function testLastReturnsLastElement(): void
    {
        self::assertSame(3, $this->arr->last([1, 2, 3]));
    }

    public function testFlattenFlattensNestedArray(): void
    {
        self::assertSame([1, 2, 3], $this->arr->flatten([1, [2, [3]]]));
    }

    public function testFlattenRespectsDepth(): void
    {
        self::assertSame([1, 2, [3]], $this->arr->flatten([1, [2, [3]]], 1));
    }

    public function testPluckExtractsKey(): void
    {
        $data = [['name' => 'Alice'], ['name' => 'Bob']];
        self::assertSame(['Alice', 'Bob'], $this->arr->pluck($data, 'name'));
    }

    public function testUniqueRemovesDuplicates(): void
    {
        self::assertSame([1, 2, 3], $this->arr->unique([1, 2, 2, 3]));
    }

    public function testChunkSplitsArray(): void
    {
        self::assertSame([[1, 2], [3]], $this->arr->chunk([1, 2, 3], 2));
    }

    public function testCompactRemovesFalsyValues(): void
    {
        self::assertSame([1, 'a'], $this->arr->compact([1, null, '', false, 'a']));
    }

    public function testContainsReturnsTrueWhenValuePresent(): void
    {
        self::assertTrue($this->arr->contains([1, 2, 3], 2));
    }

    public function testContainsReturnsFalseWhenValueAbsent(): void
    {
        self::assertFalse($this->arr->contains([1, 2], 5));
    }

    public function testWhereFiltersByKeyValue(): void
    {
        $data = [['active' => true], ['active' => false]];
        self::assertSame([['active' => true]], $this->arr->where($data, 'active', true));
    }

    public function testSumReturnsTotal(): void
    {
        self::assertSame(6, $this->arr->sum([1, 2, 3]));
    }

    public function testAvgReturnsAverage(): void
    {
        self::assertSame(2.0, $this->arr->avg([1, 2, 3]));
    }

    public function testOnlyKeepsSpecifiedKeys(): void
    {
        self::assertSame(['a' => 1], $this->arr->only(['a' => 1, 'b' => 2], ['a']));
    }

    public function testExceptRemovesSpecifiedKeys(): void
    {
        self::assertSame(['b' => 2], $this->arr->except(['a' => 1, 'b' => 2], ['a']));
    }

    public function testGroupByGroupsCorrectly(): void
    {
        $data = [['type' => 'a'], ['type' => 'b'], ['type' => 'a']];
        $result = $this->arr->groupBy($data, 'type');
        self::assertCount(2, $result['a']);
        self::assertCount(1, $result['b']);
    }

    public function testSortByAscending(): void
    {
        $data = [['n' => 3], ['n' => 1], ['n' => 2]];
        $sorted = $this->arr->sortBy($data, 'n');
        self::assertSame(1, $sorted[0]['n']);
    }

    public function testSortByDescending(): void
    {
        $data = [['n' => 1], ['n' => 3], ['n' => 2]];
        $sorted = $this->arr->sortBy($data, 'n', 'desc');
        self::assertSame(3, $sorted[0]['n']);
    }
}