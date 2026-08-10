<?php

declare(strict_types=1);

/**
 * @var string $title
 * @var string|null $headerHtml
 * @var string $childrenHtml
 * @var bool $hasChildren
 */
?>
<style>
    .nav-group {
        margin-bottom: 0.15rem;
    }

    .nav-group-header {
        display: flex;
        align-items: stretch;
        gap: 0.15rem;
    }

    .nav-group-title {
        flex: 1 1 auto;
        padding: 0.6rem 0.9rem;
        font-size: 0.87rem;
        color: var(--text-muted);
    }

    .nav-group-toggle {
        background: none;
        border: none;
        color: var(--text-faint);
        cursor: pointer;
        padding: 0 0.6rem;
        font-size: 0.7rem;
        transition: transform 0.12s;
    }

    .nav-group-toggle.is-open {
        transform: rotate(180deg);
    }

    .nav-group-children {
        display: none;
        padding-left: 0.7rem;
        border-left: 1px solid var(--border);
        margin-left: 0.9rem;
    }

    .nav-group-children.is-open {
        display: block;
    }

    .nav-group-children .nav-item {
        font-size: 0.8rem;
    }
</style>

<div class="nav-group">
    <div class="nav-group-header">
        <?php if ($headerHtml !== null): ?>
            <?= $headerHtml ?>
        <?php else: ?>
            <div class="nav-group-title"><?= htmlspecialchars($title) ?></div>
        <?php endif; ?>

        <?php if ($hasChildren): ?>
            <button type="button" class="nav-group-toggle" aria-label="Toggle <?= htmlspecialchars($title) ?> list">▾</button>
        <?php endif; ?>
    </div>

    <?php if ($hasChildren): ?>
        <div class="nav-group-children">
            <?= $childrenHtml ?>
        </div>
    <?php endif; ?>
</div>