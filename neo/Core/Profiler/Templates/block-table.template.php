<?php
/** @var array{section?: string|null, columns: list<string>, rows: list<list<mixed>>} $block */
?>
    <style>
        .data-table-wrap {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow-x: auto;
            margin-bottom: 1.5rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.83rem;
        }

        .data-table thead th {
            text-align: left;
            padding: 0.7rem 1rem;
            background: var(--bg-muted);
            border-bottom: 1px solid var(--border);
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .data-table tbody td {
            padding: 0.7rem 1rem;
            border-bottom: 1px solid var(--bg-muted);
            overflow-wrap: anywhere;
            vertical-align: top;
            color: var(--text);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table tbody tr:nth-child(even) td {
            background: var(--bg-subtle);
        }

        .data-table tbody tr:hover td {
            background: var(--bg-muted);
        }

        .data-table tbody td:first-child {
            font-weight: 600;
            color: var(--text-muted);
            white-space: nowrap;
        }
    </style>

<?php if (($block['section'] ?? null) !== null): ?>
    <div class="group-label"><?= htmlspecialchars($block['section']) ?></div>
<?php endif; ?>

<?php if ($block['rows'] === []): ?>
    <p class="empty-state">No data.</p>
<?php else: ?>
    <div class="data-table-wrap">
        <table class="data-table">
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
                        <td>
                            <?php if (is_array($cell) && ($cell['raw'] ?? false)): ?>
                                <?= $cell['html'] ?>
                            <?php else: ?>
                                <?= htmlspecialchars((string) $cell) ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>