<?php
/** @var array{section?: string|null, html: string} $block */
?>
<?php if (($block['section'] ?? null) !== null): ?>
    <div class="group-label"><?= htmlspecialchars($block['section']) ?></div>
<?php endif; ?>

<?= $block['html'] ?>