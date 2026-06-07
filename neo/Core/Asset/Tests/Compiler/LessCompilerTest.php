<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Tests\Compiler;

use Neo\Core\Asset\Compiler\LessCompiler;
use PHPUnit\Framework\TestCase;

class LessCompilerTest extends TestCase
{
    private string $tmpDir;
    private string $sourceFile;
    private string $targetFile;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/neo_asset_tests_less_' . uniqid();
        mkdir($this->tmpDir, 0777, true);

        $this->sourceFile = __DIR__ . '/../Fixtures/sample.less';
        $this->targetFile = $this->tmpDir . '/output.css';
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
        $compiler = new LessCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $this->assertFileExists($this->targetFile);
    }

    public function testCompileOutputIsNotEmpty(): void
    {
        $compiler = new LessCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $this->assertGreaterThan(0, filesize($this->targetFile));
    }

    public function testCompileResolvesVariables(): void
    {
        $compiler = new LessCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $output = file_get_contents($this->targetFile);

        // @primary-color: #4a90e2 doit être résolu
        $this->assertStringContainsString('#4a90e2', $output);
        // Les variables LESS ne doivent plus apparaître dans l'output
        $this->assertStringNotContainsString('@primary-color', $output);
    }

    public function testCompileFlattensNestedRules(): void
    {
        $compiler = new LessCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $output = file_get_contents($this->targetFile);

        // Le sélecteur imbriqué .container .header doit être aplati
        $this->assertStringContainsString('.container .header', $output);
    }

    public function testCompileOutputIsMinified(): void
    {
        $compiler = new LessCompiler();
        $compiler->compile($this->sourceFile, $this->targetFile);

        $output = file_get_contents($this->targetFile);

        // Le LessCompiler applique un preg_replace sur les espaces
        $this->assertStringNotContainsString('  ', $output);
    }
}