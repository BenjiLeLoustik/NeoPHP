<?php

namespace Neo\Core\Translation\Extension;

use Neo\Core\Extension\Attribute\Extension;
use Neo\Core\Extension\Enum\ExtensionTypeEnum;
use Neo\Core\Translation\TranslationManager;
use Neo\Core\View\Interface\TwigExtensionInterface;

#[Extension(type: ExtensionTypeEnum::VIEW)]
class TranslationViewExtension implements TwigExtensionInterface
{
    public function __construct(
        private readonly TranslationManager $translator
    ) {}

    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
    public function getFunctions(): array
    {
        return [
            'translate' => [
                'callable' => fn(string $text, ?array $params = null, ?string $domain = null) => $this->translator->translate($text, $params ?? [], $domain),
                'options'  => [],
            ],
            'trans' => [
                'callable' => fn(string $text, ?array $params = null, ?string $domain = null) => $this->translator->translate($text, $params ?? [], $domain),
                'options'  => [],
            ],
            'getLocales' => [
                'callable' => [$this->translator, 'getLocales'],
                'options'  => [],
            ],
            'getLocale' => [
                'callable' => [$this->translator, 'getLocale'],
                'options'  => [],
            ],
            'isEnabledTranslation' => [
                'callable' => [$this->translator, 'isEnabledTranslation'],
                'options'  => [],
            ],
        ];
    }

    /**
     * @return array<string, array{callable: callable, options: array<string, mixed>}>
     */
    public function getFilters(): array
    {
        return [
            'trans' => [
                'callable' => fn(string $text, ?array $params = null, ?string $domain = null) => $this->translator->translate($text, $params ?? [], $domain),
                'options'  => [],
            ],
        ];
    }
}