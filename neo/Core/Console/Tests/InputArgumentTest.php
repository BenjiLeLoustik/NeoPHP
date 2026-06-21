<?php
declare(strict_types=1);

namespace Neo\Core\Console\Tests;

use Neo\Core\Console\Input\InputArgument;
use PHPUnit\Framework\TestCase;

final class InputArgumentTest extends TestCase
{
    public function testRequiredArgument(): void
    {
        $arg = new InputArgument('name', 'desc', InputArgument::REQUIRED);

        self::assertTrue($arg->isRequired());
        self::assertFalse($arg->isArray());
        self::assertSame('required', $arg->getModeLabel());
    }

    public function testOptionalArgumentWithDefault(): void
    {
        $arg = new InputArgument('name', 'desc', InputArgument::OPTIONAL, 'fallback');

        self::assertFalse($arg->isRequired());
        self::assertSame('fallback', $arg->getDefault());
        self::assertSame('optional', $arg->getModeLabel());
    }

    public function testArrayArgument(): void
    {
        $arg = new InputArgument('items', 'desc', InputArgument::IS_ARRAY | InputArgument::REQUIRED);

        self::assertTrue($arg->isRequired());
        self::assertTrue($arg->isArray());
        self::assertSame('required, array', $arg->getModeLabel());
    }

    public function testGettersReturnConstructorValues(): void
    {
        $arg = new InputArgument('project', 'Project name');

        self::assertSame('project', $arg->getName());
        self::assertSame('Project name', $arg->getDescription());
    }
}