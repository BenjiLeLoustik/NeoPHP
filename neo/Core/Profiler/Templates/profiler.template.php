<?php
/**
 * @var string $token
 * @var string $method
 * @var string $path
 * @var string $ip
 * @var string $timestamp
 * @var int $statusCode
 * @var string $statusLabel
 * @var string $statusSolid
 * @var string $statusGradient
 * @var float $duration
 * @var float $memory
 * @var string $navHtml
 * @var string $sectionsHtml
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profiler — <?= htmlspecialchars($path) ?></title>
    <style>
        :root {
            --bg: #ffffff;
            --bg-subtle: #f8fafc;
            --bg-muted: #f1f5f9;
            --border: #e2e8f0;
            --text: #0f172a;
            --text-muted: #64748b;
            --text-faint: #94a3b8;
            --accent: <?= $statusSolid ?>;
            --shadow-sm: 0 1px 2px rgba(15, 23, 42, 0.04);
            --radius: 10px;
            --radius-sm: 6px;
            --font: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --mono: "SF Mono", "Cascadia Code", Consolas, monospace;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            font-family: var(--font);
            font-size: 14px;
            line-height: 1.6;
            color: var(--text);
            background: var(--bg);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .header {
            flex: 0 0 auto;
            background: <?= $statusGradient ?>;
            padding: 1.65rem 2.25rem;
        }

        .header-top {
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .method-badge {
            font-family: var(--mono);
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            padding: 0.22rem 0.6rem;
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }

        .path {
            font-family: var(--mono);
            font-size: 1.05rem;
            font-weight: 500;
            color: #ffffff;
            word-break: break-all;
        }

        .header-meta {
            margin-top: 0.95rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.3rem 1.85rem;
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .header-meta strong {
            color: #ffffff;
            font-weight: 600;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.65rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.74rem;
            color: <?= $statusSolid ?>;
            background: #ffffff;
        }

        .body {
            flex: 1 1 auto;
            display: flex;
            min-height: 0;
        }

        .sidebar {
            flex: 0 0 216px;
            background: var(--bg-subtle);
            border-right: 1px solid var(--border);
            overflow-y: auto;
            padding: 1.1rem 0.75rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0.5rem 0.85rem;
            margin-bottom: 0.1rem;
            background: none;
            border: none;
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            font-family: inherit;
            font-size: 0.85rem;
            text-align: left;
            cursor: pointer;
            transition: background 0.12s, color 0.12s;
        }

        .nav-item:hover {
            background: var(--bg-muted);
            color: var(--text);
        }

        .nav-item.is-active {
            background: #ffffff;
            color: var(--text);
            font-weight: 600;
            box-shadow: var(--shadow-sm), inset 0 0 0 1px var(--border);
        }

        .nav-badge {
            background: #fee2e2;
            color: #991b1b;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.05rem 0.4rem;
            border-radius: 999px;
        }

        .panel-badge {
            display: inline-block;
            background: #fee2e2;
            color: #991b1b;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            vertical-align: middle;
            margin-left: 0.5rem;
        }

        .main {
            flex: 1 1 auto;
            min-width: 0;
            overflow-y: auto;
            padding: 2.5rem 3.25rem 5rem;
        }

        .main-inner {
            width: 100%;
        }

        .metrics {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2.75rem;
        }

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

        .panel {
            display: none;
        }

        .panel.is-active {
            display: block;
        }

        .panel-heading {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0 0 1.6rem;
            padding-bottom: 0.9rem;
            border-bottom: 1px solid var(--border);
            letter-spacing: -0.01em;
        }

        .panel-body .group-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--text-faint);
            margin: 1.85rem 0 0.9rem;
        }

        .panel-body .group-label:first-child {
            margin-top: 0;
        }

        .panel-body dl {
            display: grid;
            grid-template-columns: 148px minmax(0, 1fr);
            row-gap: 0.65rem;
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

        .panel-body table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.83rem;
            margin-bottom: 1.5rem;
        }

        .panel-body th {
            text-align: left;
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
        }

        .panel-body td {
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid var(--bg-muted);
            overflow-wrap: anywhere;
            vertical-align: top;
        }

        .panel-body tbody tr:hover td {
            background: var(--bg-subtle);
        }

        .empty-state {
            color: var(--text-faint);
            font-size: 0.85rem;
            padding: 1.5rem;
            text-align: center;
            border: 1px dashed var(--border);
            border-radius: var(--radius);
            margin: 0;
        }
    </style>
</head>
<body>
<header class="header">
    <div class="header-top">
        <span class="method-badge"><?= htmlspecialchars($method) ?></span>
        <span class="path"><?= htmlspecialchars($path) ?></span>
    </div>
    <div class="header-meta">
        <span class="status-pill"><?= $statusCode ?> <?= htmlspecialchars($statusLabel) ?></span>
        <span>IP <strong><?= htmlspecialchars($ip) ?></strong></span>
        <span>Profiled <strong><?= htmlspecialchars($timestamp) ?></strong></span>
        <span>Token <strong><?= htmlspecialchars($token) ?></strong></span>
    </div>
</header>

<div class="body">
    <aside class="sidebar">
        <nav><?= $navHtml ?></nav>
    </aside>

    <main class="main">
        <div class="main-inner">
            <div class="metrics">
                <div class="metric">
                    <div class="metric-label">Total time</div>
                    <div class="metric-value"><?= $duration ?><small>ms</small></div>
                </div>
                <div class="metric">
                    <div class="metric-label">Peak memory</div>
                    <div class="metric-value"><?= $memory ?><small>MB</small></div>
                </div>
            </div>

            <?= $sectionsHtml ?>
        </div>
    </main>
</div>

<script>
    document.querySelectorAll('.nav-item').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.dataset.target;

            document.querySelectorAll('.nav-item').forEach(function (b) {
                b.classList.remove('is-active');
            });
            document.querySelectorAll('.panel').forEach(function (s) {
                s.classList.remove('is-active');
            });

            btn.classList.add('is-active');
            document.querySelector('.panel[data-section="' + target + '"]').classList.add('is-active');
        });
    });

    var hash = window.location.hash.replace('#', '');
    if (hash) {
        var target = document.querySelector('.nav-item[data-target="' + hash + '"]');
        if (target) target.click();
    }
</script>
</body>
</html>