<?php

namespace Neo\Core\View\Interface;

interface TwigExtensionInterface
{
    public function getFunctions(): array;
    public function getFilters(): array;
}