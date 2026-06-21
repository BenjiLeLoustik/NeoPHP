<?php
declare(strict_types=1);

namespace Neo\Core\Console\Tests;

use Neo\Core\Console\Input\InputOption;
use PHPUnit\Framework\TestCase;

final class InputOptionTest extends TestCase
{
    public function testFlagOption(): void
    {
        $opt = new InputOption('force', mode: InputOption::NONE);

        self::assertTrue($opt->isFlag());
        self::assertFalse($opt->requiresValue());
        self::assertSame('--force', $opt->getSynopsis());
    }

    public function testRequiredValueOption(): void
    {
        $opt = new InputOption('project', mode: InputOption::REQUIRED);

        self::assertTrue($opt->requiresValue());
        self::assertSame('--project=<project>', $opt->getSynopsis());
    }

    public function testOptionalValueOption(): void
    {
        $opt = new InputOption('dir', mode: InputOption::OPTIONAL);

        self::assertTrue($opt->isValueOptional());
        self::assertSame('--dir[=<dir>]', $opt->getSynopsis());
    }

    public function testSynopsisIncludesShortcutWhenPresent(): void
    {
        $opt = new InputOption('dir', shortcut: 'd', mode: InputOption::REQUIRED);

        self::assertSame('d', $opt->getShortcut());
        self::assertSame('-d, --dir=<dir>', $opt->getSynopsis());
    }

    public function testArrayOption(): void
    {
        $opt = new InputOption('tag', mode: InputOption::IS_ARRAY | InputOption::REQUIRED);

        self::assertTrue($opt->isArray());
        self::assertTrue($opt->requiresValue());
    }

    public function testDefaultValue(): void
    {
        $opt = new InputOption('env', mode: InputOption::REQUIRED, default: 'prod');

        self::assertSame('prod', $opt->getDefault());
    }
}