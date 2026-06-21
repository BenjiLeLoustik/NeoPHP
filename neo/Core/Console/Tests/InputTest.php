<?php
declare(strict_types=1);

namespace Neo\Core\Console\Tests;

use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputArgument;
use Neo\Core\Console\Input\InputOption;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{

    public function testPositionalArgumentsAreMappedInOrder(): void
    {
        $input = new Input(
            ['MyProject', 'src/Foo.php'],
            [
                new InputArgument('project'),
                new InputArgument('path'),
            ]
        );

        self::assertSame('MyProject', $input->getArgument('project'));
        self::assertSame('src/Foo.php', $input->getArgument('path'));
    }

    public function testMissingOptionalArgumentUsesDefault(): void
    {
        $input = new Input(
            [],
            [new InputArgument('project', default: 'DefaultApp')]
        );

        self::assertSame('DefaultApp', $input->getArgument('project'));
    }

    public function testArrayArgumentCollectsAllRemainingPositionalValues(): void
    {
        $input = new Input(
            ['one', 'two', 'three'],
            [new InputArgument('items', mode: InputArgument::IS_ARRAY)]
        );

        self::assertSame(['one', 'two', 'three'], $input->getArgument('items'));
    }

    public function testUndefinedArgumentReturnsNull(): void
    {
        $input = new Input([], []);

        self::assertNull($input->getArgument('whatever'));
    }

    public function testLongOptionWithEqualsSign(): void
    {
        $input = new Input(
            ['--project=MyApp'],
            [],
            [new InputOption('project', mode: InputOption::REQUIRED)]
        );

        self::assertSame('MyApp', $input->getOption('project'));
    }

    public function testLongFlagOptionWithoutValue(): void
    {
        $input = new Input(
            ['--force'],
            [],
            [new InputOption('force', mode: InputOption::NONE)]
        );

        self::assertTrue($input->getOption('force'));
        self::assertTrue($input->hasOption('force'));
    }

    public function testLongOptionRequiringValueConsumesNextToken(): void
    {
        $input = new Input(
            ['--project', 'MyApp'],
            [],
            [new InputOption('project', mode: InputOption::REQUIRED)]
        );

        self::assertSame('MyApp', $input->getOption('project'));
    }

    public function testLongOptionRequiringValueFallsBackToTrueWhenNextTokenIsAnOption(): void
    {
        $input = new Input(
            ['--project', '--force'],
            [],
            [
                new InputOption('project', mode: InputOption::REQUIRED),
                new InputOption('force', mode: InputOption::NONE),
            ]
        );

        self::assertTrue($input->getOption('project'));
    }

    public function testShortOptionResolvesToCanonicalName(): void
    {
        $input = new Input(
            ['-d', 'Utils'],
            [],
            [new InputOption('dir', shortcut: 'd', mode: InputOption::REQUIRED)]
        );

        self::assertSame('Utils', $input->getOption('dir'));
        self::assertSame('Utils', $input->getOption('d'));
    }

    public function testUnknownShortOptionUsesRawKeyAsCanonical(): void
    {
        $input = new Input(['-x'], [], []);

        self::assertTrue($input->getOption('x'));
    }

    public function testFlagOptionDefaultsToFalseWhenNotProvided(): void
    {
        $input = new Input([], [], [new InputOption('force', mode: InputOption::NONE)]);

        self::assertFalse($input->getOption('force'));
        self::assertFalse($input->hasOption('force'));
    }

    public function testValueOptionDefaultsToDefinedDefaultWhenNotProvided(): void
    {
        $input = new Input([], [], [new InputOption('env', mode: InputOption::REQUIRED, default: 'prod')]);

        self::assertSame('prod', $input->getOption('env'));
    }

    public function testHasOptionIsFalseForNullOrFalseValues(): void
    {
        $input = new Input(
            [],
            [],
            [new InputOption('force', mode: InputOption::NONE)]
        );

        self::assertFalse($input->hasOption('force'));
        self::assertFalse($input->hasOption('unknown'));
    }

    public function testHasOptionIsTrueForNonEmptyValue(): void
    {
        $input = new Input(
            ['--project=MyApp'],
            [],
            [new InputOption('project', mode: InputOption::REQUIRED)]
        );

        self::assertTrue($input->hasOption('project'));
    }
}