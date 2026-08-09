<?php
/**
 * @var string $chipsHtml
 * @var float $duration
 * @var string $memory
 * @var string $token
 */
?>
<style>
    #neo-bar-wrapper * {
        box-sizing: border-box;
    }

    #neo-bar-wrapper {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 999999;
        font-family: -apple-system, "Segoe UI", Roboto, sans-serif;
        font-size: 12.5px;
    }

    #neo-bar-collapsed {
        display: none;
        position: fixed;
        bottom: 12px;
        left: 12px;
        background: #ffffff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
        padding: 0.5rem 0.9rem;
        font-weight: 700;
        color: #111827;
        cursor: pointer;
        z-index: 999999;
    }

    #neo-bar {
        background: #ffffff;
        border-top: 1px solid #e5e7eb;
        display: flex;
        align-items: stretch;
        height: 40px;
    }

    #neo-bar button, #neo-bar a {
        font-family: inherit;
        font-size: inherit;
    }

    .n-collapse-btn {
        display: flex;
        align-items: center;
        padding: 0 1rem;
        color: #111827;
        font-weight: 700;
        border-right: 1px solid #e5e7eb;
        flex-shrink: 0;
        background: none;
        border-top: none;
        border-bottom: none;
        border-left: none;
        cursor: pointer;
    }

    #neo-bar .n-chips {
        display: flex;
        align-items: stretch;
        overflow-x: auto;
        flex: 1;
    }

    #neo-bar .n-metrics {
        display: flex;
        align-items: center;
        padding: 0 1rem;
        gap: 1rem;
        border-left: 1px solid #e5e7eb;
        flex-shrink: 0;
    }

    .n-metric {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .n-metric-value {
        color: #111827;
        font-weight: 700;
        font-size: 0.85rem;
    }

    .n-metric-label {
        font-size: 0.62rem;
        color: #9ca3af;
        text-transform: uppercase;
    }

    .n-profiler-btn {
        display: flex;
        align-items: center;
        padding: 0 1.2rem;
        background: #111827;
        color: #ffffff;
        font-weight: 600;
        text-decoration: none;
        flex-shrink: 0;
    }

    .n-profiler-btn:hover {
        background: #1f2937;
    }
</style>

<div id="neo-bar-wrapper">
    <button type="button" id="neo-bar-collapsed">NeoPHP</button>

    <div id="neo-bar">
        <button type="button" class="n-collapse-btn" id="neo-collapse-btn">NeoPHP</button>

        <div class="n-chips">
            <?= $chipsHtml ?>
        </div>

        <div class="n-metrics">
            <div class="n-metric">
                <span class="n-metric-value"><?= htmlspecialchars((string)$duration) ?> ms</span>
                <span class="n-metric-label">Duration</span>
            </div>
            <div class="n-metric">
                <span class="n-metric-value"><?= htmlspecialchars($memory) ?></span>
                <span class="n-metric-label">Memory</span>
            </div>
        </div>

        <a href="/_profiler/<?= htmlspecialchars($token) ?>" target="_blank" class="n-profiler-btn">Profiler</a>
    </div>
</div>

<script>
    (function () {
        var STORAGE_KEY = 'neo-profiler-bar-collapsed';
        var bar = document.getElementById('neo-bar');
        var collapsed = document.getElementById('neo-bar-collapsed');
        var collapseBtn = document.getElementById('neo-collapse-btn');

        function setCollapsed(isCollapsed) {
            bar.style.display = isCollapsed ? 'none' : 'flex';
            collapsed.style.display = isCollapsed ? 'block' : 'none';
            localStorage.setItem(STORAGE_KEY, isCollapsed ? '1' : '0');
        }

        collapseBtn.addEventListener('click', function () {
            setCollapsed(true);
        });
        collapsed.addEventListener('click', function () {
            setCollapsed(false);
        });
        setCollapsed(localStorage.getItem(STORAGE_KEY) === '1');
    })();
</script>