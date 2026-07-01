<?php
declare(strict_types=1);

namespace Neo\Core\Translation;

use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Profiler\Interface\CollectorAwareInterface;
use Neo\Core\Profiler\Interface\CollectorInterface;
use Neo\Core\Translation\Interface\TranslationCollectorInterface;

class TranslationCollector implements CollectorInterface, TranslationCollectorInterface, CollectorAwareInterface
{
    /** @var array<int, array{key: string, result: string, domain: string}> */
    private array $hits = [];

    /** @var array<int, array{key: string, result: string, domain: string}> */
    private array $misses = [];

    public function __construct(
        private readonly TranslationManager $manager
    ) {}

    public function boot(Container $container): void
    {
        $this->manager->setCollector($this);
    }

    public function getName(): string
    {
        return 'translation';
    }

    public function record(string $key, string $result, bool $found, string $domain = 'common'): void
    {
        if ($found) {
            $this->hits[] = [
                'key' => $key,
                'result' => $result,
                'domain' => $domain
            ];
        } else {
            $this->misses[] = [
                'key' => $key,
                'result' => $result,
                'domain' => $domain
            ];
        }
    }

    /**
     * @return array<string, mixed>
     * @throws ContainerException
     */
    public function collect(): array
    {
        return [
            'enabled' => $this->manager->isEnabledTranslation(),
            'locale' => $this->manager->getLocale(),
            'locales' => $this->manager->getLocales(),
            'hits_count' => count($this->hits),
            'misses_count' => count($this->misses),
            'hits' => $this->hits,
            'misses' => $this->misses,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderTab(array $data): string
    {
        $enabled = $data['enabled'] ?? false;
        $locale = $data['locale'] ?? null;
        $misses = $data['misses_count'] ?? 0;

        $color = !$enabled
            ? '#52525b'
            : ($misses > 0 ? '#fbbf24' : '#4ade80');

        $label = !$enabled
            ? 'Disabled'
            : htmlspecialchars(strtoupper($locale ?? '—'));

        return <<<HTML
<div class="n-tab" onclick="neoSwitch('translation')" title="Traductions">
    <span class="n-label">i18n</span>
    <span class="n-value" style="color:{$color}">{$label}</span>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPanel(array $data): string
    {
        if (!($data['enabled'] ?? false)) {
            return '<p class="n-empty">The translation system is disabled.</p>';
        }

        $locale = htmlspecialchars(strtoupper($data['locale'] ?? '—'));
        $hits = $data['hits_count'] ?? 0;
        $misses = $data['misses_count'] ?? 0;
        $locales = $data['locales'] ?? [];

        $localeRows = '';
        foreach ($locales as $code => $label) {
            $c = htmlspecialchars((string) $code);
            $l = htmlspecialchars((string) $label);
            $active = strtolower((string) $code) === strtolower($data['locale'] ?? '')
                ? 'style="color:#4ade80;font-weight:600"'
                : '';
            $localeRows .= "<tr><td {$active}>{$c}</td><td class=\"n-origin\" {$active}>{$l}</td></tr>";
        }

        $localesTable = $localeRows
            ? "<table><thead><tr><th>Code</th><th>Label</th></tr></thead><tbody>{$localeRows}</tbody></table>"
            : '<p class="n-empty">No locale configured.</p>';

        $missRows = '';
        foreach (($data['misses'] ?? []) as $m) {
            $k = htmlspecialchars($m['key']);
            $v = htmlspecialchars($m['result']);
            $d = htmlspecialchars($m['domain'] ?? 'common');
            $missRows .= "<tr><td style=\"color:#fbbf24\">{$k}</td><td class=\"n-origin\">{$v}</td><td class=\"n-origin\">{$d}</td></tr>";
        }

        $missesTable = $missRows
            ? "<table><thead><tr><th>Missing key</th><th>Fallback</th><th>Domain</th></tr></thead><tbody>{$missRows}</tbody></table>"
            : '<p class="n-empty">No missing keys.</p>';

        $hitRows = '';
        foreach (($data['hits'] ?? []) as $h) {
            $k = htmlspecialchars($h['key']);
            $v = htmlspecialchars($h['result']);
            $d = htmlspecialchars($h['domain'] ?? 'common');
            $hitRows .= "<tr><td class=\"n-event\">{$k}</td><td class=\"n-origin\">{$v}</td><td class=\"n-origin\">{$d}</td></tr>";
        }

        $hitsTable = $hitRows
            ? "<table><thead><tr><th>Key</th><th>Translation</th><th>Domain</th></tr></thead><tbody>{$hitRows}</tbody></table>"
            : '<p class="n-empty">No translation resolved.</p>';

        $missColor = $misses > 0 ? '#fbbf24' : '#4ade80';

        return <<<HTML
<dl class="n-kv" style="margin-bottom:12px">
    <dt>Active local</dt><dd style="color:#4ade80;font-weight:600">{$locale}</dd>
    <dt>Keys resolved</dt><dd style="color:#4ade80">{$hits}</dd>
    <dt>Missing keys</dt><dd style="color:{$missColor}">{$misses}</dd>
</dl>

<p class="n-section-title">Availables locales</p>
{$localesTable}

<p class="n-section-title">Missing keys ({$misses})</p>
{$missesTable}

<p class="n-section-title">Keys resolved ({$hits})</p>
{$hitsTable}
HTML;
    }
}