<?php
/** @var array{label: string, value: string, unit?: string} $metric */
?>
<div class="metric">
    <div class="metric-label"><?= htmlspecialchars($metric['label']) ?></div>
    <div class="metric-value">
        <?= htmlspecialchars($metric['value']) ?>
        <?php if (!empty($metric['unit'])): ?>
            <small><?= htmlspecialchars($metric['unit']) ?></small>
        <?php endif; ?>
    </div>
</div>