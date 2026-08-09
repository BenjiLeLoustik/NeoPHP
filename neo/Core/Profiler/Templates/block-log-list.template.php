<?php
/**
 * @var array{
 *     section?: string|null,
 *     rows: list<array{time: string, channel: string, origin: string, message: string, context: string}>
 * } $block
 */
?>
<style>
    .log-list {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .log-list-header {
        display: grid;
        grid-template-columns: 140px 1fr;
        background: var(--bg-muted);
        padding: 0.65rem 1rem;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted);
    }

    .log-entry {
        display: grid;
        grid-template-columns: 140px 1fr;
        padding: 0.85rem 1rem;
        border-top: 1px solid var(--bg-muted);
    }

    .log-entry:hover {
        background: var(--bg-subtle);
    }

    .log-entry-time {
        font-family: var(--mono);
        font-size: 0.78rem;
        color: var(--text-muted);
    }

    .log-entry-message {
        font-size: 0.87rem;
        color: var(--text);
        overflow-wrap: anywhere;
    }

    .log-entry-meta {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        margin-top: 0.5rem;
    }

    .log-entry-tag {
        display: inline-flex;
        align-items: center;
        font-family: var(--mono);
        font-size: 0.68rem;
        font-weight: 600;
        color: var(--text-muted);
        background: var(--bg-muted);
        padding: 0.15rem 0.5rem;
        border-radius: var(--radius-sm);
    }

    .log-entry-toggle {
        background: none;
        border: none;
        color: var(--accent);
        font-size: 0.78rem;
        cursor: pointer;
        padding: 0;
        text-decoration: none;
    }

    .log-entry-toggle:hover {
        text-decoration: underline;
    }

    .log-entry-context {
        display: none;
        grid-column: 1 / -1;
        margin-top: 0.75rem;
        padding: 0.75rem 0.9rem;
        background: var(--bg-subtle);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        font-family: var(--mono);
        font-size: 0.76rem;
        color: var(--text-muted);
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .log-entry-context.is-open {
        display: block;
    }
</style>

<?php if (($block['section'] ?? null) !== null): ?>
    <div class="group-label"><?= htmlspecialchars($block['section']) ?></div>
<?php endif; ?>

<?php if ($block['rows'] === []): ?>
    <p class="empty-state">No data.</p>
<?php else: ?>
    <div class="log-list">
        <div class="log-list-header">
            <span>Time</span>
            <span>Message</span>
        </div>

        <?php foreach ($block['rows'] as $i => $row): ?>
            <?php $contextId = 'log-ctx-' . substr(md5($row['time'] . $i . $row['message']), 0, 10); ?>
            <div class="log-entry">
                <div class="log-entry-time"><?= htmlspecialchars($row['time']) ?></div>
                <div>
                    <div class="log-entry-message"><?= htmlspecialchars($row['message']) ?></div>
                    <div class="log-entry-meta">
                        <span class="log-entry-tag"><?= htmlspecialchars($row['channel']) ?></span>
                        <?php if ($row['origin'] !== ''): ?>
                            <span class="log-entry-tag"><?= htmlspecialchars($row['origin']) ?></span>
                        <?php endif; ?>
                        <?php if ($row['context'] !== ''): ?>
                            <button type="button" class="log-entry-toggle" data-context-toggle="<?= htmlspecialchars($contextId) ?>">Show context</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($row['context'] !== ''): ?>
                    <pre class="log-entry-context" id="<?= htmlspecialchars($contextId) ?>"><?= htmlspecialchars($row['context']) ?></pre>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
    document.querySelectorAll('[data-context-toggle]').forEach(function (btn) {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';

        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.dataset.contextToggle);
            if (!target) return;

            var isOpen = target.classList.toggle('is-open');
            btn.textContent = isOpen ? 'Hide context' : 'Show context';
        });
    });
</script>