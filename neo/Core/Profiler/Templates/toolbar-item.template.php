<?php

declare(strict_types=1);

/**
 * @var array{label: string, value: string, badge: string|null, badgeColor: string} $item
 */
?>
<style>
    .n-chip {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 0 12px;
        white-space: nowrap;
        border-right: 1px solid #f3f4f6;
    }

    .n-label {
        color: #9ca3af;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .n-value {
        color: #111827;
        font-weight: 600;
    }

    .n-badge {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 0.05rem 0.4rem;
        border-radius: 999px;
    }
</style>

<div class="n-chip">
    <span class="n-label"><?= htmlspecialchars($item['label']) ?></span>
    <span class="n-value"><?= htmlspecialchars($item['value']) ?></span>
    <?php if ($item['badge'] !== null): ?>
        <span class="n-badge" style="background: <?= htmlspecialchars($item['badgeColor']) ?>33; color: <?= htmlspecialchars($item['badgeColor']) ?>;">
            <?= htmlspecialchars($item['badge']) ?>
        </span>
    <?php endif; ?>
</div>