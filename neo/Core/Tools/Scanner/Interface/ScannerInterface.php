<?php

namespace Neo\Core\Tools\Scanner\Interface;

interface ScannerInterface
{
    public function in(string $directory): static;
    public function inSubfolder(string $directory, string $subfolder): static;
    public function withSuffix(string $suffix): static;
    public function getResults(): array;
}