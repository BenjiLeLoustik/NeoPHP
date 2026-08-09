<?php
/** @var array{section?: string|null, rows: list<array{label: string, value: string}>} $block */
?>
<?php if (($block['section'] ?? null) !== null): ?>
    <div class="group-label"><?= htmlspecialchars($block['section']) ?></div>
<?php endif; ?>

<?php if ($block['rows'] === []): ?>
    <p class="empty-state">No data.</p>
<?php else: ?>
    <dl>
        <?php foreach ($block['rows'] as $row): ?>
            <dt><?= htmlspecialchars($row['label']) ?></dt>
            <dd><?= htmlspecialchars($row['value']) ?></dd>
        <?php endforeach; ?>
    </dl>
<?php endif; ?>