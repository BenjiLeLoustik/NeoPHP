<?php
/**
 * @var array{label: string, value: string, badge: string|null} $item
 */
?>
<div class="n-chip">
    <span class="n-label"><?= htmlspecialchars($item['label']) ?></span>
    <span class="n-value"><?= htmlspecialchars($item['value']) ?></span>
    <?php if ($item['badge'] !== null): ?>
        <span class="n-badge"><?= htmlspecialchars($item['badge']) ?></span>
    <?php endif; ?>
</div>