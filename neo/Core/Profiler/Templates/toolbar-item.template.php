<?php
/**
 * @var array{label: string, value: string, badge: string|null, badgeColor: string} $item
 */
?>
<div class="n-chip">
    <span class="n-label"><?= htmlspecialchars($item['label']) ?></span>
    <span class="n-value"><?= htmlspecialchars($item['value']) ?></span>
    <?php if ($item['badge'] !== null): ?>
        <span class="n-badge" style="background: <?= htmlspecialchars($item['badgeColor']) ?>33; color: <?= htmlspecialchars($item['badgeColor']) ?>;">
            <?= htmlspecialchars($item['badge']) ?>
        </span>
    <?php endif; ?>
</div>