<?php

declare(strict_types=1);

/** @var array{label: string, value: string, unit?: string} $metric */
?>
<style>
    .metric {
        flex: 0 0 auto;
        min-width: 128px;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 0.95rem 1.3rem;
        box-shadow: var(--shadow-sm);
    }

    .metric-label {
        font-size: 0.66rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--accent);
        margin-bottom: 0.35rem;
    }

    .metric-value {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.01em;
    }

    .metric-value small {
        font-size: 0.78rem;
        color: var(--text-muted);
        font-weight: 500;
        margin-left: 0.2rem;
    }
</style>

<div class="metric">
    <div class="metric-label"><?= htmlspecialchars($metric['label']) ?></div>
    <div class="metric-value">
        <?= htmlspecialchars($metric['value']) ?>
        <?php if (!empty($metric['unit'])): ?>
            <small><?= htmlspecialchars($metric['unit']) ?></small>
        <?php endif; ?>
    </div>
</div>