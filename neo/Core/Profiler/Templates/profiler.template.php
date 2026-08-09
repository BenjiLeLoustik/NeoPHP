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

        .brand-bar {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            background: #0f172a;
            color: #ffffff;
            padding: 0.7rem 2.25rem;
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .brand-bar .brand-accent {
            color: var(--accent);
        }

        .header {
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
            flex: 0 0 300px;
            background: var(--bg-subtle);
            border-right: 1px solid var(--border);
            overflow-y: auto;
            padding: 1.25rem 1rem;
        }

        .main {
            flex: 1 1 auto;
            min-width: 0;
            overflow-y: auto;
        }

        .main-inner {
            width: 100%;
            padding: 2.5rem 3.25rem 5rem;
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
<div class="brand-bar">
    <span>Neo<span class="brand-accent">Profiler</span></span>
</div>

<div class="body">
    <aside class="sidebar">
        <nav><?= $navHtml ?></nav>
    </aside>

    <main class="main">
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

        <div class="main-inner">
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
            var panel = document.querySelector('.panel[data-section="' + target + '"]');
            if (panel) panel.classList.add('is-active');
        });
    });

    document.querySelectorAll('.nav-group-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var children = toggle.closest('.nav-group').querySelector('.nav-group-children');
            children.classList.toggle('is-open');
            toggle.classList.toggle('is-open');
        });
    });

    document.querySelectorAll('.tab-item').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var group = tab.dataset.tabgroup;
            var target = tab.dataset.tabTarget;

            document.querySelectorAll('.tab-item[data-tabgroup="' + group + '"]').forEach(function (t) {
                t.classList.remove('is-active');
            });
            document.querySelectorAll('.tab-panel').forEach(function (p) {
                if (p.dataset.tabPanel && p.dataset.tabPanel.startsWith(group + '-')) {
                    p.classList.remove('is-active');
                }
            });

            tab.classList.add('is-active');
            var panel = document.querySelector('.tab-panel[data-tab-panel="' + target + '"]');
            if (panel) panel.classList.add('is-active');
        });
    });

    var hash = window.location.hash.replace('#', '');
    if (hash) {
        var target = document.querySelector('.nav-item[data-target="' + hash + '"]');
        if (target) {
            var group = target.closest('.nav-group-children');
            if (group) {
                group.classList.add('is-open');
                var toggle = group.closest('.nav-group').querySelector('.nav-group-toggle');
                if (toggle) toggle.classList.add('is-open');
            }
            target.click();
        }
    }
</script>
</body>
</html>