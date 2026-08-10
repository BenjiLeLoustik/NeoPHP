<?php

declare(strict_types=1);

/** @var array{section?: string|null, rows: list<array{label: string, value: string}>} $block */
?>
    <style>
        .panel-body dl {
            display: grid;
            grid-template-columns: 160px minmax(0, 1fr);
            row-gap: 0.7rem;
            column-gap: 1.5rem;
            margin: 0 0 1.5rem;
            font-size: 0.86rem;
        }

        .panel-body dt {
            color: var(--text-muted);
        }

        .panel-body dd {
            margin: 0;
            overflow-wrap: anywhere;
        }
    </style>

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