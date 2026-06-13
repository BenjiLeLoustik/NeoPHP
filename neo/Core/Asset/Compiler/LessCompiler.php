<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Compiler;

use Neo\Core\Asset\Compiler\Interface\CompilerInterface;

class LessCompiler implements CompilerInterface
{
    /**
     * @throws \Less_Exception_Parser
     */
    public function compile(string $source, string $target): void
    {
        $parser = new \Less_Parser();

        $parser->parseFile($source, dirname($source) . '/');

        $css = $parser->getCss();

        $css = preg_replace('/\s+/', ' ', $css);

        file_put_contents($target, $css);
    }
}
