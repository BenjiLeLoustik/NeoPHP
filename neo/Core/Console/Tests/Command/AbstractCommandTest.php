<?php
declare(strict_types=1);

namespace Neo\Core\Console\Tests\Command;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use PHPUnit\Framework\TestCase;

class AbstractCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Fixtures
    // -------------------------------------------------------------------------

    private function makeNamedCommand(): AbstractCommand
    {
        return new #[Command(name: 'test:run', description: 'Run tests', category: 'Test')]
        class extends AbstractCommand {};
    }

    private function makeUnnamedCommand(): AbstractCommand
    {
        return new class extends AbstractCommand {};
    }

    // -------------------------------------------------------------------------
    // getName()
    // -------------------------------------------------------------------------

    public function testGetNameReadsFromAttribute(): void
    {
        $this->assertSame('test:run', $this->makeNamedCommand()->getName());
    }

    public function testGetNameReturnsEmptyStringWhenNoAttribute(): void
    {
        $this->assertSame('', $this->makeUnnamedCommand()->getName());
    }

    // -------------------------------------------------------------------------
    // getDescription()
    // -------------------------------------------------------------------------

    public function testGetDescriptionReadsFromAttribute(): void
    {
        $this->assertSame('Run tests', $this->makeNamedCommand()->getDescription());
    }

    public function testGetDescriptionReturnsEmptyStringWhenNoAttribute(): void
    {
        $this->assertSame('', $this->makeUnnamedCommand()->getDescription());
    }

    // -------------------------------------------------------------------------
    // getHelp()
    // -------------------------------------------------------------------------

    public function testGetHelpReturnsEmptyStringByDefault(): void
    {
        $this->assertSame('', $this->makeNamedCommand()->getHelp());
    }

    // -------------------------------------------------------------------------
    // execute()
    // -------------------------------------------------------------------------

    public function testExecuteDoesNothingByDefault(): void
    {
        $this->expectNotToPerformAssertions();
        $command = $this->makeNamedCommand();
        $command->execute([]);
    }
}