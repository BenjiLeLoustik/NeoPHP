<?php
/** @var array{section?: string|null, columns: list<string>, rows: list<list<string>>} $block */
?>
    <style>
        .panel-body table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.83rem;
            margin-bottom: 1.5rem;
        }

        .panel-body th {
            text-align: left;
            padding: 0.65rem 0.8rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .panel-body td {
            padding: 0.65rem 0.8rem;
            border-bottom: 1px solid var(--bg-muted);
            overflow-wrap: anywhere;
            vertical-align: top;
        }

        .panel-body tbody tr:hover td {
            background: var(--bg-subtle);
        }
    </style>

<?php if (($block['section'] ?? null) !== null): ?>
    <div class="group-label"><?= htmlspecialchars($block['section']) ?></div>
<?php endif; ?>

<?php if ($block['rows'] === []): ?>
    <p class="empty-state">No data.</p>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <?php foreach ($block['columns'] as $col): ?>
                <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($block['rows'] as $row): ?>
            <tr>
                <?php foreach ($row as $cell): ?>
                    <td><?= htmlspecialchars((string) $cell) ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>