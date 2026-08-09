<?php
/**
 * @var string|null $headerHtml
 * @var string $childrenHtml
 * @var bool $hasChildren
 */
?>
<div class="nav-group">
    <div class="nav-group-header">
        <?php if ($headerHtml !== null): ?>
            <?= $headerHtml ?>
        <?php else: ?>
            <div class="nav-group-title">Packages</div>
        <?php endif; ?>

        <?php if ($hasChildren): ?>
            <button type="button" class="nav-group-toggle" aria-label="Toggle packages list">▾</button>
        <?php endif; ?>
    </div>

    <?php if ($hasChildren): ?>
        <div class="nav-group-children">
            <?= $childrenHtml ?>
        </div>
    <?php endif; ?>
</div>