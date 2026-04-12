<?php
declare(strict_types=1);

namespace Neo\Core\Asset\Compiler;

use MatthiasMullie\Minify\JS;
use Neo\Core\Asset\Compiler\Interface\CompilerInterface;

class JsCompiler implements CompilerInterface
{
    public function compile(string $source, string $target): void
    {
        $minifier = new JS($source);
        $minifier->minify($target);
    }
}
