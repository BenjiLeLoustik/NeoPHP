<?php
/**
 * @var string $id
 * @var array{section?: string|null, tabs: list<array{label: string, badge: string|null, badgeType: string, blocksHtml: string}>} $block
 */
?>
<?php if (($block['section'] ?? null) !== null): ?>
    <div class="group-label"><?= htmlspecialchars($block['section']) ?></div>
<?php endif; ?>

<div class="tabs" data-tabgroup="<?= htmlspecialchars($id) ?>">
    <div class="tabs-nav">
        <?php foreach ($block['tabs'] as $i => $tab): ?>
            <?php
            $tabId = $id . '-' . $i;
            $color = $tab['badgeType'] === 'alert' ? '#dc2626' : '#94a3b8';
            ?>
            <button
                type="button"
                class="tab-item<?= $i === 0 ? ' is-active' : '' ?>"
                data-tabgroup="<?= htmlspecialchars($id) ?>"
                data-tab-target="<?= htmlspecialchars($tabId) ?>"
            >
                <?= htmlspecialchars($tab['label']) ?>
                <?php if ($tab['badge'] !== null): ?>
                    <span class="tab-badge" style="background: <?= $color ?>33; color: <?= $color ?>;"><?= htmlspecialchars($tab['badge']) ?></span>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="tabs-body">
        <?php foreach ($block['tabs'] as $i => $tab): ?>
            <?php $tabId = $id . '-' . $i; ?>
            <div class="tab-panel<?= $i === 0 ? ' is-active' : '' ?>" data-tab-panel="<?= htmlspecialchars($tabId) ?>">
                <?= $tab['blocksHtml'] ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>