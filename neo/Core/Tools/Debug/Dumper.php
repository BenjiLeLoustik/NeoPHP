<?php

declare(strict_types=1);

namespace Neo\Core\Tools\Debug;

final class Dumper
{
    private const array COLORS = [
        'null' => '#569cd6',
        'bool' => '#569cd6',
        'int' => '#b5cea8',
        'float' => '#b5cea8',
        'string' => '#ce9178',
        'key' => '#9cdcfe',
        'class' => '#4ec9b0',
        'punct' => '#808080',
    ];

    private int $idCounter = 0;

    /**
     * @param list<mixed> $vars
     */
    public function render(array $vars): string
    {
        $this->idCounter = 0;

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        $caller = $this->resolveCaller($trace);

        $blocks = '';
        foreach ($vars as $var) {
            $blocks .= '<div class="neo-dd-block">' . $this->renderValue($var, 0) . '</div>';
        }

        return $this->wrap($blocks, $caller);
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private function resolveCaller(array $trace): ?string
    {
        foreach ($trace as $frame) {
            $file = $frame['file'] ?? null;

            if ($file !== null && !str_contains($file, __DIR__)) {
                return $file . ':' . ($frame['line'] ?? '?');
            }
        }

        return null;
    }

    private function renderValue(mixed $value, int $depth): string
    {
        return match (true) {
            $value === null => $this->span('null', 'null'),
            is_bool($value) => $this->span('bool', $value ? 'true' : 'false'),
            is_int($value) => $this->span('int', (string) $value),
            is_float($value) => $this->span('float', $this->formatFloat($value)),
            is_string($value) => $this->renderString($value),
            is_array($value) => $this->renderArray($value, $depth),
            is_object($value) => $this->renderObject($value, $depth),
            default => $this->span('punct', gettype($value)),
        };
    }

    private function formatFloat(float $value): string
    {
        $formatted = (string) $value;

        return str_contains($formatted, '.') || str_contains($formatted, 'E')
            ? $formatted
            : $formatted . '.0';
    }

    private function renderString(string $value): string
    {
        $length = strlen($value);
        $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<span style="color:%s;">"%s"</span><span style="color:%s;font-size:0.75em;"> (%d)</span>',
            self::COLORS['string'],
            $escaped,
            self::COLORS['punct'],
            $length
        );
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private function renderArray(array $value, int $depth): string
    {
        $count = count($value);

        if ($count === 0) {
            return $this->span('punct', 'array:0') . ' ' . $this->punct('[]');
        }

        $id = 'neo-dd-' . (++$this->idCounter);
        $open = $depth < 1 ? ' open' : '';

        $rows = '';
        foreach ($value as $key => $item) {
            $keyHtml = is_int($key)
                ? $this->span('int', (string) $key)
                : sprintf('<span style="color:%s;">"%s"</span>', self::COLORS['key'], htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'));

            $rows .= '<div class="neo-dd-row"><span class="neo-dd-key">' . $keyHtml . $this->punct(' => ') . '</span>'
                . $this->renderValue($item, $depth + 1) . '</div>';
        }

        return sprintf(
            '<details id="%s" class="neo-dd-details"%s><summary>%s %s</summary><div class="neo-dd-indent">%s</div></details>',
            $id,
            $open,
            $this->span('punct', 'array:' . $count),
            $this->punct('[…]'),
            $rows
        );
    }

    private function renderObject(object $value, int $depth): string
    {
        $class = $value::class;

        if ($value instanceof \UnitEnum) {
            $case = $value instanceof \BackedEnum ? $value->name . ' = ' . var_export($value->value, true) : $value->name;
            return $this->span('class', $class) . $this->punct('::') . $this->span('int', $case);
        }

        $ref = new \ReflectionObject($value);
        $props = $ref->getProperties();

        if ($props === []) {
            return $this->span('class', $class) . ' ' . $this->punct('{}');
        }

        $id = 'neo-dd-' . (++$this->idCounter);
        $open = $depth < 1 ? ' open' : '';

        $rows = '';
        foreach ($props as $prop) {
            $prop->setAccessible(true);

            if (!$prop->isInitialized($value)) {
                $rows .= '<div class="neo-dd-row"><span class="neo-dd-key">' . $this->renderPropName($prop) . $this->punct(' => ') . '</span>'
                    . $this->span('punct', 'uninitialized') . '</div>';
                continue;
            }

            $rows .= '<div class="neo-dd-row"><span class="neo-dd-key">' . $this->renderPropName($prop) . $this->punct(' => ') . '</span>'
                . $this->renderValue($prop->getValue($value), $depth + 1) . '</div>';
        }

        return sprintf(
            '<details id="%s" class="neo-dd-details"%s><summary>%s %s</summary><div class="neo-dd-indent">%s</div></details>',
            $id,
            $open,
            $this->span('class', $class),
            $this->punct('{…}'),
            $rows
        );
    }

    private function renderPropName(\ReflectionProperty $prop): string
    {
        $visibility = match (true) {
            $prop->isPrivate() => '-',
            $prop->isProtected() => '#',
            default => '+',
        };

        return $this->punct($visibility) . sprintf(
                '<span style="color:%s;">%s</span>',
                self::COLORS['key'],
                htmlspecialchars($prop->getName(), ENT_QUOTES, 'UTF-8')
            );
    }

    private function span(string $type, string $text): string
    {
        return sprintf('<span style="color:%s;">%s</span>', self::COLORS[$type] ?? '#d4d4d4', htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    private function punct(string $text): string
    {
        return sprintf('<span style="color:%s;">%s</span>', self::COLORS['punct'], htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    private function wrap(string $blocks, ?string $caller): string
    {
        $callerHtml = $caller !== null
            ? '<div class="neo-dd-caller">' . htmlspecialchars($caller, ENT_QUOTES, 'UTF-8') . '</div>'
            : '';

        return <<<HTML
<div class="neo-dd-wrapper">
    <style>
        .neo-dd-wrapper {
            font-family: "SF Mono", "Cascadia Code", Consolas, monospace;
            font-size: 13px;
            line-height: 1.6;
            background: #1e1e2e;
            color: #d4d4d4;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin: 1rem;
            overflow-x: auto;
            box-shadow: 0 4px 16px rgba(0,0,0,0.3);
        }
        .neo-dd-caller {
            color: #6b7280;
            font-size: 11px;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #313244;
        }
        .neo-dd-block {
            margin-bottom: 0.5rem;
        }
        .neo-dd-block:last-child {
            margin-bottom: 0;
        }
        .neo-dd-details summary {
            cursor: pointer;
            list-style: none;
        }
        .neo-dd-details summary::-webkit-details-marker {
            display: none;
        }
        .neo-dd-details summary::before {
            content: "▸";
            display: inline-block;
            width: 1em;
            color: #6b7280;
        }
        .neo-dd-details[open] > summary::before {
            content: "▾";
        }
        .neo-dd-indent {
            margin-left: 1.4em;
            border-left: 1px solid #313244;
            padding-left: 0.75em;
        }
        .neo-dd-row {
            padding: 0.1rem 0;
        }
        .neo-dd-key {
            margin-right: 0.1em;
        }
    </style>
    {$callerHtml}
    {$blocks}
</div>
HTML;
    }
}