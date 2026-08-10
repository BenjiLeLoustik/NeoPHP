<?php

declare(strict_types=1);

/**
 * @var string $id
 * @var array{section?: string|null, tabs: list<array{label: string, badge: string|null, badgeType: string, blocksHtml: string}>} $block
 */
?>
<style>
    .tabs {
        margin-bottom: 1.5rem;
    }

    .tabs-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        background: var(--bg-subtle);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 0.35rem;
        margin-bottom: 1.5rem;
    }

    .tab-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: none;
        border: none;
        border-radius: var(--radius-sm);
        padding: 0.55rem 1rem;
        font-family: inherit;
        font-size: 0.85rem;
        color: var(--text-muted);
        cursor: pointer;
        transition: background 0.12s, color 0.12s;
    }

    .tab-item:hover {
        color: var(--text);
    }

    .tab-item.is-active {
        background: #ffffff;
        color: var(--text);
        font-weight: 600;
        box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--border);
    }

    .tab-badge {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 0.05rem 0.45rem;
        border-radius: 999px;
    }

    .tab-panel {
        display: none;
    }

    .tab-panel.is-active {
        display: block;
    }
</style>

<?php if (($block['section'] ?? null) !== null): ?>
    <div class="group-label"><?= htmlspecialchars($block['section']) ?></div>
<?php endif; ?>

<div class="tabs" data-tabgroup="<?= htmlspecialchars($id) ?>">
    <div class="tabs-nav">
        <?php foreach ($block['tabs'] as $i => $tab): ?>
            <?php
            $tabId = $id . '-' . $i;
            $color = $tab['badgeType'] === 'alert' ? '#dc2626' : '#94a3b8';
            ?>
            <button
                    type="button"
                    class="tab-item<?= $i === 0 ? ' is-active' : '' ?>"
                    data-tabgroup="<?= htmlspecialchars($id) ?>"
                    data-tab-target="<?= htmlspecialchars($tabId) ?>"
            >
                <?= htmlspecialchars($tab['label']) ?>
                <?php if ($tab['badge'] !== null): ?>
                    <span class="tab-badge" style="background: <?= $color ?>33; color: <?= $color ?>;"><?= htmlspecialchars($tab['badge']) ?></span>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="tabs-body">
        <?php foreach ($block['tabs'] as $i => $tab): ?>
            <?php $tabId = $id . '-' . $i; ?>
            <div class="tab-panel<?= $i === 0 ? ' is-active' : '' ?>" data-tab-panel="<?= htmlspecialchars($tabId) ?>">
                <?= $tab['blocksHtml'] ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>