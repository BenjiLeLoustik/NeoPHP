<?php

namespace Neo\Core\Utils\Scanner\Result;

class FileScanResult
{
    public function __construct(
        private string $fqcn,
        private string $filePath,
    ) {
    }

    public function getFqcn(): string
    {
        return $this->fqcn;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}