<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Compiler;

use MatthiasMullie\Minify\CSS;
use Neo\Core\Asset\Compiler\Interface\CompilerInterface;

class CssCompiler implements CompilerInterface
{
    public function compile(string $source, string $target): void
    {
        $minifier = new CSS($source);
        $minifier->minify($target);
    }
}
