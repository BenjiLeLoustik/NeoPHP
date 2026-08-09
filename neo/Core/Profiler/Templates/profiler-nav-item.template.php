<?php
/**
 * @var string $key
 * @var array{title: string, badge: string|null, badgeType: string} $item
 * @var bool $active
 */
$activeClass = $active ? ' is-active' : '';
$badgeColor = ($item['badgeType'] ?? 'neutral') === 'alert' ? '#dc2626' : '#94a3b8';
?>
<style>
    .nav-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex: 1 1 auto;
        width: 100%;
        padding: 0.6rem 0.9rem;
        margin-bottom: 0.15rem;
        background: none;
        border: none;
        border-radius: var(--radius-sm);
        color: var(--text-muted);
        font-family: inherit;
        font-size: 0.87rem;
        text-align: left;
        cursor: pointer;
        transition: background 0.12s, color 0.12s;
    }

    .nav-item:hover {
        background: var(--bg-muted);
        color: var(--text);
    }

    .nav-item.is-active {
        background: #ffffff;
        color: var(--text);
        font-weight: 600;
        box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--border);
    }

    .nav-badge {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 0.05rem 0.4rem;
        border-radius: 999px;
    }
</style>

<button type="button" class="nav-item<?= $activeClass ?>" data-target="<?= htmlspecialchars($key) ?>">
    <span><?= htmlspecialchars($item['title']) ?></span>
    <?php if ($item['badge'] !== null): ?>
        <span class="nav-badge" style="background: <?= htmlspecialchars($badgeColor) ?>33; color: <?= htmlspecialchars($badgeColor) ?>;"><?= htmlspecialchars($item['badge']) ?></span>
    <?php endif; ?>
</button>