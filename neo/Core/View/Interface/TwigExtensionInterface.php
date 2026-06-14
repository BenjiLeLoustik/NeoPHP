<?php

namespace Neo\Core\View\Interface;

interface TwigExtensionInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getFunctions(): array;

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array;
}