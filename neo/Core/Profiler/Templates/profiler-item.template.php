<?php
/**
 * @var string $key
 * @var array{title: string, badge: string|null, badgeType: string, metricsHtml: string, blocksHtml: string} $item
 * @var bool $active
 */
$activeClass = $active ? ' is-active' : '';
$badgeColor = ($item['badgeType'] ?? 'neutral') === 'alert' ? '#dc2626' : '#94a3b8';
?>
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