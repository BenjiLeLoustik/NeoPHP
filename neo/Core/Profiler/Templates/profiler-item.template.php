<?php

declare(strict_types=1);

/**
 * @var string $key
 * @var array{title: string, badge: string|null, badgeType: string, metricsHtml: string, blocksHtml: string} $item
 * @var bool $active
 */
$activeClass = $active ? ' is-active' : '';
$badgeColor = ($item['badgeType'] ?? 'neutral') === 'alert' ? '#dc2626' : '#94a3b8';
?>
<style>
    .panel {
        display: none;
    }

    .panel.is-active {
        display: block;
    }

    .panel-heading {
        font-size: 1.15rem;
        font-weight: 700;
        margin: 0 0 1.6rem;
        padding-bottom: 0.9rem;
        border-bottom: 1px solid var(--border);
        letter-spacing: -0.01em;
    }

    .panel-badge {
        display: inline-block;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 0.1rem 0.5rem;
        border-radius: 999px;
        vertical-align: middle;
        margin-left: 0.5rem;
    }

    .panel-metrics {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 2.25rem;
    }

    .panel-body .group-label {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--text-faint);
        margin: 1.85rem 0 0.9rem;
    }

    .panel-body .group-label:first-child {
        margin-top: 0;
    }
</style>

<section class="panel<?= $activeClass ?>" data-section="<?= htmlspecialchars($key) ?>">
    <h2 class="panel-heading">
        <?= htmlspecialchars($item['title']) ?>
        <?php if ($item['badge'] !== null): ?>
            <span class="panel-badge" style="background: <?= htmlspecialchars($badgeColor) ?>33; color: <?= htmlspecialchars($badgeColor) ?>;"><?= htmlspecialchars($item['badge']) ?></span>
        <?php endif; ?>
    </h2>

    <?php if ($item['metricsHtml'] !== ''): ?>
        <div class="panel-metrics"><?= $item['metricsHtml'] ?></div>
    <?php endif; ?>

    <div class="panel-body">
        <?= $item['blocksHtml'] ?>
    </div>
</section>