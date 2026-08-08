<?php
/**
 * @var array{title: string, badge: string|null, blocks: list<array<string, mixed>>} $item
 * @var string $key
 * @var bool $active
 */
$activeClass = $active ? ' is-active' : '';
?>
<section class="panel<?= $activeClass ?>" data-section="<?= htmlspecialchars($key) ?>">
    <h2 class="panel-heading">
        <?= htmlspecialchars($item['title']) ?>
        <?php if ($item['badge'] !== null): ?>
            <span class="panel-badge"><?= htmlspecialchars($item['badge']) ?></span>
        <?php endif; ?>
    </h2>

    <div class="panel-body">
        <?php if ($item['blocks'] === []): ?>
            <p class="empty-state">No data.</p>
        <?php endif; ?>

        <?php foreach ($item['blocks'] as $block): ?>
            <?php if (($block['section'] ?? null) !== null): ?>
                <div class="group-label"><?= htmlspecialchars($block['section']) ?></div>
            <?php endif; ?>

            <?php if ($block['type'] === 'kv'): ?>
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
            <?php endif; ?>

            <?php if ($block['type'] === 'table'): ?>
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
                                    <td><?= htmlspecialchars((string)$cell) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>