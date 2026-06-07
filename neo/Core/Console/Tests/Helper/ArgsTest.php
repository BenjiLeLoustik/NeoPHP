<?php
declare(strict_types=1);

namespace Neo\Core\Console\Tests\Helper;

use Neo\Core\Console\Helper\Args;
use PHPUnit\Framework\TestCase;

class ArgsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // option()
    // -------------------------------------------------------------------------

    public function testOptionParsesEqualsFormat(): void
    {
        $result = Args::option(['--project=MyApp'], '--project');
        $this->assertSame('MyApp', $result);
    }

    public function testOptionParsesSpaceFormat(): void
    {
        $result = Args::option(['--project', 'MyApp'], '--project');
        $this->assertSame('MyApp', $result);
    }

    public function testOptionReturnsNullWhenAbsent(): void
    {
        $result = Args::option(['--other=value'], '--project');
        $this->assertNull($result);
    }

    public function testOptionDoesNotPickNextFlagAsValue(): void
    {
        $result = Args::option(['--project', '--force'], '--project');
        $this->assertNull($result);
    }

    public function testOptionHandlesEmptyArgs(): void
    {
        $result = Args::option([], '--project');
        $this->assertNull($result);
    }

    public function testOptionReturnsFirstMatchOnly(): void
    {
        $result = Args::option(['--project=First', '--project=Second'], '--project');
        $this->assertSame('First', $result);
    }

    // -------------------------------------------------------------------------
    // flag()
    // -------------------------------------------------------------------------

    public function testFlagReturnsTrueWhenPresent(): void
    {
        $this->assertTrue(Args::flag(['--force', '--verbose'], '--force'));
    }

    public function testFlagReturnsFalseWhenAbsent(): void
    {
        $this->assertFalse(Args::flag(['--verbose'], '--force'));
    }

    public function testFlagReturnsFalseOnEmptyArgs(): void
    {
        $this->assertFalse(Args::flag([], '--force'));
    }

    public function testFlagIsStrictMatch(): void
    {
        // --force=value n'est pas un flag --force
        $this->assertFalse(Args::flag(['--force=value'], '--force'));
    }

    // -------------------------------------------------------------------------
    // positional()
    // -------------------------------------------------------------------------

    public function testPositionalReturnsFirstNonFlagArg(): void
    {
        $result = Args::positional(['MyApp', '--force'], 0);
        $this->assertSame('MyApp', $result);
    }

    public function testPositionalSkipsFlags(): void
    {
        $result = Args::positional(['--project=MyApp', 'SomeArg'], 0);
        $this->assertSame('SomeArg', $result);
    }

    public function testPositionalReturnsNullWhenOutOfBounds(): void
    {
        $result = Args::positional(['MyApp'], 1);
        $this->assertNull($result);
    }

    public function testPositionalReturnsNullOnEmptyArgs(): void
    {
        $result = Args::positional([], 0);
        $this->assertNull($result);
    }

    public function testPositionalReturnsCorrectIndex(): void
    {
        $result = Args::positional(['--verbose', 'First', 'Second'], 1);
        $this->assertSame('Second', $result);
    }

    // -------------------------------------------------------------------------
    // positionals()
    // -------------------------------------------------------------------------

    public function testPositionalsFiltersOutFlags(): void
    {
        $result = Args::positionals(['--force', 'MyApp', '--project=X', 'SomeArg']);
        $this->assertSame(['MyApp', 'SomeArg'], $result);
    }

    public function testPositionalsReturnsEmptyArrayWhenAllFlags(): void
    {
        $result = Args::positionals(['--force', '--verbose']);
        $this->assertSame([], $result);
    }

    public function testPositionalsReturnsEmptyArrayOnEmptyInput(): void
    {
        $result = Args::positionals([]);
        $this->assertSame([], $result);
    }

    public function testPositionalsPreservesOrder(): void
    {
        $result = Args::positionals(['C', '--x', 'A', 'B']);
        $this->assertSame(['C', 'A', 'B'], $result);
    }
}