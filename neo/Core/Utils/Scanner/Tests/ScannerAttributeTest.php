<?php
declare(strict_types=1);

namespace Neo\Core\Utils\Scanner\Tests;

use Neo\Core\Utils\Scanner\Attribute\ScannerAttribute;
use Neo\Core\Utils\Scanner\Tests\Fixture\AnnotatedClass;
use Neo\Core\Utils\Scanner\Tests\Fixture\Tag;
use PHPUnit\Framework\TestCase;

final class ScannerAttributeTest extends TestCase
{
    public function testScanClassReturnsClassAttribute(): void
    {
        $results = new ScannerAttribute(AnnotatedClass::class)
            ->onClass()
            ->withAttribute(Tag::class)
            ->scan();

        self::assertCount(1, $results);
        self::assertSame('class', $results[0]['type']);
        self::assertInstanceOf(Tag::class, $results[0]['attribute']);
        self::assertSame('on-class', $results[0]['attribute']->value);
    }

    public function testScanMethodsReturnsMethodAttribute(): void
    {
        $results = new ScannerAttribute(AnnotatedClass::class)
            ->onMethods()
            ->withAttribute(Tag::class)
            ->scan();

        self::assertCount(1, $results);
        self::assertSame('method', $results[0]['type']);
        self::assertSame('on-method', $results[0]['attribute']->value);
    }

    public function testScanPropertiesReturnsPropertyAttribute(): void
    {
        $results = new ScannerAttribute(AnnotatedClass::class)
            ->onProperties()
            ->withAttribute(Tag::class)
            ->scan();

        self::assertCount(1, $results);
        self::assertSame('property', $results[0]['type']);
        self::assertSame('on-property', $results[0]['attribute']->value);
    }

    public function testScanParametersReturnsParameterAttribute(): void
    {
        $results = new ScannerAttribute(AnnotatedClass::class)
            ->onMethods()
            ->onParameters()
            ->withAttribute(Tag::class)
            ->scan();

        $params = array_filter($results, fn($r) => $r['type'] === 'parameter');

        self::assertCount(1, $params);
        self::assertSame('on-parameter', array_values($params)[0]['attribute']->value);
    }

    public function testScanAllReturnsAllAnnotatedTargets(): void
    {
        $results = new ScannerAttribute(AnnotatedClass::class)
            ->onAll()
            ->withAttribute(Tag::class)
            ->scan();

        $types = array_column($results, 'type');

        self::assertContains('class', $types);
        self::assertContains('method', $types);
        self::assertContains('property', $types);
        self::assertContains('parameter', $types);
    }

    public function testScanReturnsEmptyWhenNoAttributeMatches(): void
    {
        $results = new ScannerAttribute(AnnotatedClass::class)
            ->onMethods()
            ->withAttribute(\Deprecated::class)
            ->scan();

        self::assertSame([], $results);
    }

    public function testScanWithAllAttributesReturnsEverything(): void
    {
        $results = new ScannerAttribute(AnnotatedClass::class)
            ->onClass()
            ->withAllAttributes()
            ->scan();

        self::assertNotEmpty($results);
    }

    public function testResultContainsReflectionObject(): void
    {
        $results = new ScannerAttribute(AnnotatedClass::class)
            ->onClass()
            ->withAttribute(Tag::class)
            ->scan();

        self::assertInstanceOf(\ReflectionClass::class, $results[0]['reflection']);
    }

    public function testResultContainsArguments(): void
    {
        $results = new ScannerAttribute(AnnotatedClass::class)
            ->onClass()
            ->withAttribute(Tag::class)
            ->scan();

        self::assertSame(['on-class'], $results[0]['arguments']);
    }

    public function testResultContainsTargetLabel(): void
    {
        $results = new ScannerAttribute(AnnotatedClass::class)
            ->onClass()
            ->withAttribute(Tag::class)
            ->scan();

        self::assertSame('AnnotatedClass', $results[0]['target']);
    }
}