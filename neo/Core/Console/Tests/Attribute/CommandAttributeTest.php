<?php
declare(strict_types=1);

namespace Neo\Core\Console\Tests\Attribute;

use Neo\Core\Console\Attribute\Command;
use PHPUnit\Framework\TestCase;

class CommandAttributeTest extends TestCase
{
    public function testInstantiatesWithAllParameters(): void
    {
        $cmd = new Command(name: 'app:test', description: 'Test command', category: 'Test');

        $this->assertSame('app:test', $cmd->name);
        $this->assertSame('Test command', $cmd->description);
        $this->assertSame('Test', $cmd->category);
    }

    public function testInstantiatesWithNullDefaults(): void
    {
        $cmd = new Command();

        $this->assertNull($cmd->name);
        $this->assertNull($cmd->description);
        $this->assertNull($cmd->category);
    }

    public function testInstantiatesWithPartialParameters(): void
    {
        $cmd = new Command(name: 'app:foo');

        $this->assertSame('app:foo', $cmd->name);
        $this->assertNull($cmd->description);
        $this->assertNull($cmd->category);
    }

    public function testIsAttribute(): void
    {
        $reflection = new \ReflectionClass(Command::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertNotEmpty($attributes);
    }
}