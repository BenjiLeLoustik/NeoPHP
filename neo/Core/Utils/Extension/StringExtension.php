<?php

namespace Neo\Core\Utils\Extension;

use Neo\Core\DI\Container;
use Neo\Core\View\View;

class StringExtension
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->container->get(View::class)->registerTwigFunction(
            'slugify', fn(string $text) => $this->slugify($text)
        );

        $this->container->get(View::class)->registerTwigFilter(
            'slugify', fn(string $text) => $this->slugify($text)
        );
    }

    public function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        return (empty($text)) ? 'n-a' : $text;
    }
}