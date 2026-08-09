<?php
/** @var array{section?: string|null, columns: list<string>, rows: list<list<string>>} $block */
?>
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