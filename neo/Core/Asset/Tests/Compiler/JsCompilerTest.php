<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Tests\Compiler;

use Neo\Core\Asset\Compiler\JsCompiler;
use PHPUnit\Framework\TestCase;

class JsCompilerTest extends TestCase
{
    private string $tmpDir;
    private string $sourceFile;
    private string $targetFile;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/neo_asset_tests_js_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        $this->sourceFile = __DIR__ . '/../Fixtures/sample.js';
        $this->targetFile = $this->tmpDir . '/output.min.js';
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
        $compiler = new JsCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $this->assertFileExists($this->targetFile);
    }

    public function testCompileOutputIsNotEmpty(): void
    {
        $compiler = new JsCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $this->assertGreaterThan(0, filesize($this->targetFile));
    }

    public function testCompileOutputIsSmallerThanSource(): void
    {
        $compiler = new JsCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $this->assertLessThan(filesize($this->sourceFile), filesize($this->targetFile));
    }

    public function testCompileOutputContainsExpectedSymbols(): void
    {
        $compiler = new JsCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $output = file_get_contents($this->targetFile);

        $this->assertStringContainsString('greet', $output);
        $this->assertStringContainsString('app', $output);
    }

    public function testCompileOutputHasNoExtraNewlines(): void
    {
        $compiler = new JsCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $output = trim(file_get_contents($this->targetFile));

        $this->assertStringNotContainsString("\n\n", $output);
    }
}