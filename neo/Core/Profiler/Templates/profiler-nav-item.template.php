<?php
/**
 * @var string $key
 * @var array{title: string, badge: string|null} $item
 * @var bool $active
 */
$activeClass = $active ? ' is-active' : '';
?>
<button type="button" class="nav-item<?= $activeClass ?>" data-target="<?= htmlspecialchars($key) ?>">
    <span><?= htmlspecialchars($item['title']) ?></span>
    <?php if ($item['badge'] !== null): ?>
        <span class="nav-badge"><?= htmlspecialchars($item['badge']) ?></span>
    <?php endif; ?>
</button>