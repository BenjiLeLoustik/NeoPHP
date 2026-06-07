<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Tests\Compiler;

use Neo\Core\Asset\Compiler\CssCompiler;
use PHPUnit\Framework\TestCase;

class CssCompilerTest extends TestCase
{
    private string $tmpDir;
    private string $sourceFile;
    private string $targetFile;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/neo_asset_tests_css_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        $this->sourceFile = __DIR__ . '/../Fixtures/sample.css';
        $this->targetFile = $this->tmpDir . '/output.min.css';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->targetFile)) {
            unlink($this->targetFile);
        }

        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    public function testCompileProducesOutputFile(): void
    {
        $compiler = new CssCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $this->assertFileExists($this->targetFile);
    }

    public function testCompileOutputIsNotEmpty(): void
    {
        $compiler = new CssCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $this->assertGreaterThan(0, filesize($this->targetFile));
    }

    public function testCompileOutputIsSmallerThanSource(): void
    {
        $compiler = new CssCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $this->assertLessThan(filesize($this->sourceFile), filesize($this->targetFile));
    }

    public function testCompileOutputContainsExpectedSelectors(): void
    {
        $compiler = new CssCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $output = file_get_contents($this->targetFile);

        $this->assertStringContainsString('body', $output);
        $this->assertStringContainsString('.container', $output);
    }

    public function testCompileOutputHasNoExtraWhitespace(): void
    {
        $compiler = new CssCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $output = file_get_contents($this->targetFile);

        $this->assertStringNotContainsString("\n", $output);
    }
}