<?php
declare(strict_types=1);

namespace Neo\Core\Console\Tests\Helper;

use Neo\Core\Console\Helper\Output;
use PHPUnit\Framework\TestCase;

class OutputTest extends TestCase
{
    // -------------------------------------------------------------------------
    // colorize()
    // -------------------------------------------------------------------------

    public function testColorizeReturnsStringWithAnsiCodes(): void
    {
        $result = Output::colorize('hello', 'green');

        $this->assertStringContainsString('hello', $result);
        $this->assertStringContainsString("\033[", $result);
    }

    public function testColorizeResetsAfterText(): void
    {
        $result = Output::colorize('hello', 'red');

        $this->assertStringEndsWith("\033[0m", $result);
    }

    public function testColorizeWithUnknownColorReturnsTextWithReset(): void
    {
        $result = Output::colorize('hello', 'unknown');

        $this->assertStringContainsString('hello', $result);
        $this->assertStringEndsWith("\033[0m", $result);
    }

    public function testColorizeHandlesAllKnownColors(): void
    {
        $colors = ['red', 'green', 'yellow', 'blue', 'magenta', 'cyan', 'white', 'bold', 'dim', 'black'];

        foreach ($colors as $color) {
            $result = Output::colorize('test', $color);
            $this->assertStringContainsString('test', $result, "Failed for color: $color");
            $this->assertStringContainsString("\033[", $result, "No ANSI code for color: $color");
        }
    }

    // -------------------------------------------------------------------------
    // badge()
    // -------------------------------------------------------------------------

    public function testBadgeContainsText(): void
    {
        $result = Output::badge('STATUS');

        $this->assertStringContainsString('STATUS', $result);
    }

    public function testBadgeDefaultsToBlue(): void
    {
        $blue = Output::badge('X');
        $explicit = Output::badge('X', 'blue');

        $this->assertSame($blue, $explicit);
    }

    public function testBadgeHandlesKnownColors(): void
    {
        $colors = ['green', 'red', 'yellow', 'cyan', 'blue'];

        foreach ($colors as $color) {
            $result = Output::badge('TAG', $color);
            $this->assertStringContainsString('TAG', $result, "Failed for color: $color");
        }
    }

    public function testBadgeUnknownColorFallsBackToBlue(): void
    {
        $unknown = Output::badge('X', 'purple');
        $blue    = Output::badge('X', 'blue');

        $this->assertSame($blue, $unknown);
    }
}