<?php
/**
 * @var string $key
 * @var array{title: string, badge: string|null, badgeType: string} $item
 * @var bool $active
 */
$activeClass = $active ? ' is-active' : '';
$badgeColor = ($item['badgeType'] ?? 'neutral') === 'alert' ? '#dc2626' : '#94a3b8';
?>
<button type="button" class="nav-item<?= $activeClass ?>" data-target="<?= htmlspecialchars($key) ?>">
    <span><?= htmlspecialchars($item['title']) ?></span>
    <?php if ($item['badge'] !== null): ?>
        <span class="nav-badge" style="background: <?= htmlspecialchars($badgeColor) ?>33; color: <?= htmlspecialchars($badgeColor) ?>;"><?= htmlspecialchars($item['badge']) ?></span>
    <?php endif; ?>
</button>