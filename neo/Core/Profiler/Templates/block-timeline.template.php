<?php
/**
 * @var array{
 *     totalDuration: float,
 *     entries: list<array{category: string, label: string, offset: float, duration: float}>
 * } $block
 */
$entries = $block['entries'];
usort($entries, static fn (array $a, array $b) => $a['offset'] <=> $b['offset']);

$lastEnd = 0.0;
foreach ($entries as $entry) {
    $lastEnd = max($lastEnd, $entry['offset'] + $entry['duration']);
}

$scale = max($lastEnd, 1.0);

$colors = [
        'bootstrap' => '#6b7280',
        'boot' => '#94a3b8',
        'router' => '#2563eb',
        'controller' => '#111827',
        'database' => '#f97316',
        'view' => '#22c55e',
        'event' => '#38bdf8',
        'middleware' => '#ec4899',
        'http' => '#ef4444',
        'cache' => '#eab308',
        'response' => '#8b5cf6',
];

$usedCategories = array_unique(array_column($entries, 'category'));
$rowHeight = 30;
$trackWidth = 1400;
$id = 'wf-' . (
        array_column($entries, 'label')
            |> (fn (array $l): string => json_encode($l))
            |> md5(...)
            |> (fn (string $h): string => substr($h, 0, 8))
        );
?>
<style>
    .waterfall {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .waterfall-threshold {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.92rem;
        color: var(--text);
    }

    .waterfall-threshold input {
        width: 72px;
        padding: 0.4rem 0.55rem;
        border: 1px solid var(--border);
        border-radius: 5px;
        font-family: var(--mono);
        font-size: 0.92rem;
    }

    .waterfall-threshold-hint {
        color: var(--text-faint);
        font-size: 0.82rem;
    }

    .waterfall-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 1.2rem;
        padding: 0.9rem 1.25rem;
        border-bottom: 1px solid var(--border);
        background: var(--bg-subtle);
    }

    .waterfall-legend-item {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.82rem;
        color: var(--text-muted);
        text-transform: capitalize;
        cursor: pointer;
        user-select: none;
    }

    .waterfall-legend-item input {
        margin: 0;
        cursor: pointer;
        accent-color: var(--accent);
    }

    .waterfall-legend-item.is-disabled {
        opacity: 0.4;
    }

    .waterfall-legend-swatch {
        width: 13px;
        height: 13px;
        border-radius: 3px;
        flex-shrink: 0;
    }

    .waterfall-body {
        position: relative;
        overflow-x: auto;
        cursor: grab;
        user-select: none;
    }

    .waterfall-body.is-dragging {
        cursor: grabbing;
        user-select: none;
    }

    .waterfall-inner {
        position: relative;
        width: <?= $trackWidth ?>px;
    }

    .waterfall-grid {
        position: absolute;
        inset: 0;
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        pointer-events: none;
    }

    .waterfall-grid div {
        border-left: 1px solid var(--bg-muted);
    }

    .waterfall-ruler {
        position: relative;
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        font-family: var(--mono);
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text-muted);
        padding: 0.65rem 1.25rem 0.5rem;
        border-bottom: 1px solid var(--bg-muted);
    }

    .waterfall-rows {
        position: relative;
        padding: 0.6rem 1.25rem 1rem;
    }

    .waterfall-row {
        position: relative;
        height: <?= $rowHeight ?>px;
    }

    .waterfall-row-inner {
        position: absolute;
        top: 0;
        white-space: nowrap;
    }

    .waterfall-row-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: baseline;
        gap: 0.5rem;
        line-height: 1.15;
    }

    .waterfall-row-meta {
        font-size: 0.72rem;
        font-family: var(--mono);
        font-weight: 400;
        color: var(--text-faint);
    }

    .waterfall-row-bar {
        height: 6px;
        border-radius: 2px;
        margin-top: 3px;
        min-width: 24px;
    }

    .waterfall-row.is-hidden {
        display: none;
    }
</style>

<div class="waterfall" id="<?= $id ?>">
    <div class="waterfall-threshold">
        <label for="<?= $id ?>-threshold"><strong>Threshold</strong></label>
        <input type="number" id="<?= $id ?>-threshold" min="0" step="0.5" value="0">
        <span>ms</span>
        <span class="waterfall-threshold-hint">(hides events shorter than this duration)</span>
    </div>

    <div class="waterfall-legend">
        <?php foreach ($colors as $category => $color): ?>
            <?php if (!in_array($category, $usedCategories, true)) continue; ?>
            <label class="waterfall-legend-item" data-category="<?= htmlspecialchars($category) ?>">
                <input type="checkbox" checked data-category-toggle="<?= htmlspecialchars($category) ?>">
                <span class="waterfall-legend-swatch" style="background: <?= $color ?>;"></span>
                <?= htmlspecialchars($category) ?>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="waterfall-body">
        <div class="waterfall-inner">
            <div class="waterfall-ruler">
                <?php for ($i = 0; $i <= 5; $i++): ?>
                    <span><?= number_format($scale * ($i / 5), 0) ?> ms</span>
                <?php endfor; ?>
            </div>

            <div class="waterfall-grid">
                <div></div><div></div><div></div><div></div><div></div><div></div>
            </div>

            <div class="waterfall-rows">
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $color = $colors[$entry['category']] ?? '#94a3b8';
                    $leftPx = ($entry['offset'] / $scale) * $trackWidth;
                    $widthPx = max(24, ($entry['duration'] / $scale) * $trackWidth);
                    ?>
                    <div class="waterfall-row" data-duration="<?= $entry['duration'] ?>" data-category="<?= htmlspecialchars($entry['category']) ?>">
                        <div class="waterfall-row-inner" style="left: <?= $leftPx ?>px;">
                            <div class="waterfall-row-label">
                                <?= htmlspecialchars($entry['label']) ?>
                                <span class="waterfall-row-meta"><?= number_format($entry['duration'], 2) ?> ms</span>
                            </div>
                            <div class="waterfall-row-bar" style="width: <?= $widthPx ?>px; background: <?= $color ?>;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var container = document.getElementById('<?= $id ?>');
        if (!container) return;

        var thresholdInput = document.getElementById('<?= $id ?>-threshold');
        var rows = container.querySelectorAll('.waterfall-row');
        var categoryCheckboxes = container.querySelectorAll('[data-category-toggle]');

        var hiddenCategories = {};

        function applyFilters() {
            var threshold = parseFloat(thresholdInput.value) || 0;

            rows.forEach(function (row) {
                var duration = parseFloat(row.dataset.duration) || 0;
                var category = row.dataset.category;
                var hiddenByThreshold = duration < threshold;
                var hiddenByCategory = !!hiddenCategories[category];

                row.classList.toggle('is-hidden', hiddenByThreshold || hiddenByCategory);
            });
        }

        thresholdInput.addEventListener('input', applyFilters);

        categoryCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                var category = checkbox.dataset.categoryToggle;
                hiddenCategories[category] = !checkbox.checked;

                var legendItem = container.querySelector('.waterfall-legend-item[data-category="' + category + '"]');
                if (legendItem) legendItem.classList.toggle('is-disabled', !checkbox.checked);

                applyFilters();
            });
        });

        var body = container.querySelector('.waterfall-body');
        var isDragging = false;
        var startX = 0;
        var startScrollLeft = 0;

        body.addEventListener('mousedown', function (e) {
            isDragging = true;
            body.classList.add('is-dragging');
            startX = e.pageX;
            startScrollLeft = body.scrollLeft;
        });

        window.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            e.preventDefault();
            var delta = e.pageX - startX;
            body.scrollLeft = startScrollLeft - delta;
        });

        window.addEventListener('mouseup', function () {
            isDragging = false;
            body.classList.remove('is-dragging');
        });
    })();
</script>