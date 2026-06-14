<?php
declare(strict_types=1);

namespace Neo\Core\Profiler\Toolbar;

use Neo\Core\Profiler\Profiler;

readonly class Toolbar
{
    public function __construct(
        private Profiler $profiler
    ) {}

    public function render(): string
    {
        $data = $this->profiler->collect();

        $duration = $data['duration'];
        $memory = $this->formatBytes($data['memory']);

        $db = $data['database'] ?? ['count' => 0, 'total_ms' => 0, 'queries' => []];
        $events = $data['events'] ?? ['count' => 0, 'dispatched' => []];
        $logs = $data['logs'] ?? ['count' => 0, 'entries' => []];
        $request = $data['request'] ?? [];
        $router = $data['router'] ?? [];
        $auth = $data['auth'] ?? ['authenticated' => false, 'user' => null];
        $translation = $data['translation'] ?? [
            'enabled' => false, 'locale' => null, 'locales' => [],
            'hits_count' => 0, 'misses_count' => 0, 'hits' => [], 'misses' => [],
        ];

        $statusCode = http_response_code() ?: 200;
        $method = htmlspecialchars($request['method'] ?? 'GET');
        $path = htmlspecialchars($request['path'] ?? '/');

        $statusColor = match(true) {
            $statusCode >= 500 => '#f87171',
            $statusCode >= 400 => '#fb923c',
            $statusCode >= 300 => '#60a5fa',
            default => '#4ade80',
        };

        $durationColor = $duration < 200 ? '#4ade80' : ($duration < 500 ? '#fb923c' : '#f87171');
        $dbColor = $db['count'] > 20 ? '#f87171' : ($db['count'] > 10 ? '#fb923c' : '#a78bfa');
        $logColor = $this->logBadgeColor($logs['entries'] ?? []);
        $authColor = $auth['authenticated'] ? '#4ade80' : '#71717a';
        $authLabel = $auth['authenticated']
            ? htmlspecialchars((string) ($auth['user']['attributes']['email']
                ?? $auth['user']['attributes']['name']
                ?? 'Connecté'))
            : 'Anonyme';

        $transColor = !$translation['enabled'] ? '#52525b'
            : ($translation['misses_count'] > 0 ? '#fbbf24' : '#4ade80');
        $transLabel = !$translation['enabled'] ? 'Disabled'
            : htmlspecialchars(strtoupper($translation['locale'] ?? '—'));

        $queriesHtml = $this->renderQueries($db['queries'] ?? []);
        $eventsHtml = $this->renderEvents($events['dispatched'] ?? []);
        $logsHtml = $this->renderLogs($logs['entries'] ?? []);
        $requestHtml = $this->renderRequest($request, $router);
        $authHtml = $this->renderAuth($auth);
        $translationHtml = $this->renderTranslation($translation);

        $dbCount = $db['count'];
        $dbMs = $db['total_ms'];
        $eventCount = $events['count'];
        $logCount = $logs['count'];

        $mail = $data['mail'] ?? ['count' => 0, 'sent' => 0, 'failed' => 0, 'total_ms' => 0, 'mails' => []];

        $mailColor = $mail['failed'] > 0 ? '#f87171' : ($mail['count'] > 0 ? '#4ade80' : '#71717a');
        $mailCount = $mail['count'];
        $mailHtml = $this->renderMail($mail);

        return <<<HTML
<style>
  #neo-bar *{box-sizing:border-box;font-family:'JetBrains Mono',monospace,sans-serif}

  #neo-bar{
    position:fixed;bottom:0;left:0;right:0;z-index:99999;
    background:transparent;color:#a1a1aa;
    display:flex;align-items:stretch;height:34px;
    border-top:none;font-size:11px;
  }
  
  #neo-bar.expanded{
    background:#18181b;
    border-top:1px solid #27272a;
  }
  
  #neo-bar .n-tabs-wrapper {
    display:none;align-items:stretch;flex:1;
  }
  
  #neo-bar .n-tabs-wrapper.visible {
    display:flex;
  }

  #neo-bar .n-brand{
    display:flex;align-items:center;gap:8px;padding:0 14px;
    background:#7c3aed;color:#fff;font-weight:600;font-size:11px;letter-spacing:.3px;
    border-right:1px solid #5b21b6;flex-shrink:0;cursor:default;user-select:none;
    cursor:pointer;
  }
  #neo-bar .n-brand-dot{width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,.4)}

  #neo-bar .n-tab{
    display:flex;align-items:center;gap:7px;padding:0 13px;
    border-right:1px solid #27272a;cursor:pointer;white-space:nowrap;
    transition:background .12s;
  }
  #neo-bar .n-tab:hover{background:#27272a}
  #neo-bar .n-tab.active{background:#27272a;box-shadow:inset 0 -2px 0 #7c3aed}

  #neo-bar .n-label{font-size:10px;color:#52525b;text-transform:uppercase;letter-spacing:.6px}
  #neo-bar .n-value{font-size:11px;font-weight:500;color:#e4e4e7}
  #neo-bar .n-badge{
    background:#27272a;border-radius:3px;padding:1px 6px;
    font-size:10px;color:#71717a;
  }
  #neo-bar .n-spacer{flex:1}

  #neo-panel{
    display:none;position:fixed;bottom:34px;left:0;right:0;z-index:99998;
    background:#18181b;border-top:1px solid #27272a;
    max-height:380px;
  }
  #neo-panel.open{display:flex;flex-direction:column}

  #neo-panel .n-ptabs{
    display:flex;border-bottom:1px solid #27272a;flex-shrink:0;
  }
  #neo-panel .n-ptab{
    padding:7px 14px;font-size:10px;text-transform:uppercase;letter-spacing:.6px;
    color:#52525b;cursor:pointer;border-bottom:2px solid transparent;transition:all .12s;
    white-space:nowrap;
  }
  #neo-panel .n-ptab:hover{color:#a1a1aa}
  #neo-panel .n-ptab.active{color:#a78bfa;border-bottom-color:#7c3aed}

  #neo-panel .n-close{
    margin-left:auto;padding:7px 14px;font-size:10px;
    color:#52525b;cursor:pointer;border:none;background:none;
    transition:color .12s;
  }
  #neo-panel .n-close:hover{color:#e4e4e7}

  #neo-panel .n-body{overflow-y:auto;padding:14px 16px;flex:1}

  #neo-panel table{width:100%;border-collapse:collapse;font-size:11px}
  #neo-panel th{
    color:#52525b;font-weight:500;text-align:left;
    padding:5px 8px;border-bottom:1px solid #27272a;
    font-size:10px;text-transform:uppercase;letter-spacing:.5px;
  }
  #neo-panel td{
    padding:5px 8px;border-bottom:1px solid #1f1f1f;
    color:#a1a1aa;vertical-align:top;
  }
  #neo-panel tr:last-child td{border-bottom:none}
  #neo-panel tr:hover td{background:#1f1f1f}

  .n-sql{color:#93c5fd;word-break:break-all;max-width:560px}
  .n-ms{color:#6ee7b7;text-align:right;white-space:nowrap}
  .n-params{color:#71717a;font-size:10px}
  .n-event{color:#c4b5fd}
  .n-origin{color:#52525b}

  .n-kv{display:grid;grid-template-columns:170px 1fr;gap:3px 12px;font-size:11px}
  .n-kv dt{color:#52525b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .n-kv dd{color:#d4d4d8;margin:0;word-break:break-all}

  .n-section-title{
    color:#52525b;font-size:10px;text-transform:uppercase;letter-spacing:.6px;
    margin:14px 0 8px;
  }
  .n-section-title:first-child{margin-top:0}

  .n-auth-chip{
    display:inline-flex;align-items:center;gap:6px;
    padding:3px 10px;border-radius:3px;font-size:11px;font-weight:500;
    margin-bottom:12px;
  }
  .n-auth-chip.on{background:#14532d;color:#4ade80}
  .n-auth-chip.off{background:#27272a;color:#71717a}
  .n-auth-chip-dot{width:5px;height:5px;border-radius:50%;background:currentColor}

  .n-log-debug td:first-child{color:#52525b}
  .n-log-info td:first-child{color:#60a5fa}
  .n-log-notice td:first-child{color:#34d399}
  .n-log-warning td:first-child{color:#fbbf24}
  .n-log-error td:first-child,
  .n-log-critical td:first-child,
  .n-log-alert td:first-child,
  .n-log-emergency td:first-child{color:#f87171}

  .n-empty{color:#52525b;font-size:11px;padding:4px 0}

  #neo-bar .n-status-chip{gap:8px;padding:0 14px;border-right:2px solid #3f3f46}
  #neo-bar .n-method{font-size:10px;font-weight:700;color:#a78bfa;letter-spacing:.5px}
  #neo-bar .n-status{font-size:12px;font-weight:700}
  #neo-bar .n-path{font-size:11px;color:#71717a;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
</style>

<div id="neo-bar">
    <div class="n-brand" onclick="neoToggleBar()">
        <div class="n-brand-dot"></div>
        Neo
    </div>

    <div class="n-tabs-wrapper" id="neo-tabs-wrapper">
        <div class="n-tab n-status-chip" onclick="neoSwitch('request')" title="Statut HTTP">
            <span class="n-method">{$method}</span>
            <span class="n-status" style="color:{$statusColor}">{$statusCode}</span>
            <span class="n-path">{$path}</span>
        </div>
    
        <div class="n-tab" onclick="neoSwitch('request')" title="Requête HTTP">
            <span class="n-label">Response</span>
            <span class="n-value" style="color:{$durationColor}">{$duration} ms</span>
        </div>
    
        <div class="n-tab" onclick="neoSwitch('request')" title="Mémoire">
            <span class="n-label">Memory</span>
            <span class="n-value">{$memory}</span>
        </div>
    
        <div class="n-tab" onclick="neoSwitch('database')" title="Requêtes SQL">
            <span class="n-label">SQL</span>
            <span class="n-value" style="color:{$dbColor}">{$dbCount} req</span>
            <span class="n-badge">{$dbMs} ms</span>
        </div>
    
        <div class="n-tab" onclick="neoSwitch('events')" title="Événements">
            <span class="n-label">Events</span>
            <span class="n-value">{$eventCount}</span>
        </div>
    
        <div class="n-tab" onclick="neoSwitch('logs')" title="Logs">
            <span class="n-label">Logs</span>
            <span class="n-value" style="color:{$logColor}">{$logCount}</span>
        </div>
    
        <div class="n-tab" onclick="neoSwitch('auth')" title="Authentification">
            <span class="n-label">User</span>
            <span class="n-value" style="color:{$authColor}">{$authLabel}</span>
        </div>
    
        <div class="n-tab" onclick="neoSwitch('translation')" title="Traductions">
            <span class="n-label">i18n</span>
            <span class="n-value" style="color:{$transColor}">{$transLabel}</span>
        </div>
        
        <div class="n-tab" onclick="neoSwitch('mail')" title="Mails">
            <span class="n-label">Mail</span>
            <span class="n-value" style="color:{$mailColor}">{$mailCount}</span>
        </div>
    
        <div class="n-spacer"></div>
    </div>
</div>

<div id="neo-panel">
    <div class="n-ptabs">
        <div class="n-ptab" id="npt-request" onclick="neoPanel('request')">Request</div>
        <div class="n-ptab" id="npt-database" onclick="neoPanel('database')">Database</div>
        <div class="n-ptab" id="npt-events" onclick="neoPanel('events')">Events</div>
        <div class="n-ptab" id="npt-logs" onclick="neoPanel('logs')">Logs</div>
        <div class="n-ptab" id="npt-auth" onclick="neoPanel('auth')">Auth</div>
        <div class="n-ptab" id="npt-translation" onclick="neoPanel('translation')">i18n</div>
        <div class="n-ptab" id="npt-mail" onclick="neoPanel('mail')">Mail</div>
        <button class="n-close" onclick="neoClose()">&#x2715; Fermer</button>
    </div>

    <div class="n-body">
        <div id="npane-request" style="display:none">{$requestHtml}</div>
        <div id="npane-database" style="display:none">{$queriesHtml}</div>
        <div id="npane-events" style="display:none">{$eventsHtml}</div>
        <div id="npane-logs" style="display:none">{$logsHtml}</div>
        <div id="npane-auth" style="display:none">{$authHtml}</div>
        <div id="npane-translation" style="display:none">{$translationHtml}</div>
        <div id="npane-mail" style="display:none">{$mailHtml}</div>
    </div>
</div>

<script>
(function(){
  var current = null;
  var panes = ['request','database','events','logs','auth','translation','mail'];
  var STORAGE_KEY = 'neo_bar_visible';

  function applyBarState(visible) {
    var bar = document.getElementById('neo-bar');
    var wrapper = document.getElementById('neo-tabs-wrapper');
    var panel = document.getElementById('neo-panel');
    if (visible) {
      wrapper.classList.add('visible');
      bar.classList.add('expanded');
    } else {
      wrapper.classList.remove('visible');
      bar.classList.remove('expanded');
      panel.classList.remove('open');
      current = null;
    }
  }

  window.neoToggleBar = function() {
    var wrapper = document.getElementById('neo-tabs-wrapper');
    var isVisible = wrapper.classList.contains('visible');
    var next = !isVisible;
    localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
    applyBarState(next);
  };

  window.neoSwitch = function(name) {
    var panel = document.getElementById('neo-panel');
    if (current === name && panel.classList.contains('open')) {
      neoClose(); return;
    }
    neoPanel(name);
    panel.classList.add('open');
    document.querySelectorAll('#neo-bar .n-tab').forEach(function(t){ t.classList.remove('active'); });
  };

  window.neoPanel = function(name) {
    var panel = document.getElementById('neo-panel');
    panes.forEach(function(p){
      document.getElementById('npane-' + p).style.display = 'none';
      document.getElementById('npt-' + p).classList.remove('active');
    });
    document.getElementById('npane-' + name).style.display = '';
    document.getElementById('npt-' + name).classList.add('active');
    panel.classList.add('open');
    current = name;
  };

  window.neoClose = function() {
    document.getElementById('neo-panel').classList.remove('open');
    document.querySelectorAll('#neo-bar .n-tab').forEach(function(t){ t.classList.remove('active'); });
    current = null;
  };

  // Init : relit l'état sauvegardé
  var saved = localStorage.getItem(STORAGE_KEY);
  applyBarState(saved === '1');
})();
</script>
HTML;
    }

    /**
     * @param array<string, mixed> $t
     */
    private function renderTranslation(array $t): string
    {
        if (!$t['enabled']) {
            return '<p class="n-empty">Le système de traduction est désactivé.</p>';
        }

        $locale = htmlspecialchars(strtoupper($t['locale'] ?? '—'));
        $hits = $t['hits_count'];
        $misses = $t['misses_count'];
        $locales = $t['locales'] ?? [];

        $localeRows = '';
        foreach ($locales as $code => $label) {
            $c = htmlspecialchars((string) $code);
            $l = htmlspecialchars((string) $label);
            $active = strtolower((string) $code) === strtolower($t['locale'] ?? '')
                ? 'style="color:#4ade80;font-weight:600"'
                : '';
            $localeRows .= "<tr><td {$active}>{$c}</td><td class=\"n-origin\" {$active}>{$l}</td></tr>";
        }

        $localesTable = $localeRows ? <<<HTML
<table>
  <thead><tr><th>Code</th><th>Label</th></tr></thead>
  <tbody>{$localeRows}</tbody>
</table>
HTML : '<p class="n-empty">Aucune locale configurée.</p>';

        $missRows = '';
        foreach (($t['misses'] ?? []) as $m) {
            $k = htmlspecialchars($m['key']);
            $v = htmlspecialchars($m['result']);
            $missRows .= "<tr><td style=\"color:#fbbf24\">{$k}</td><td class=\"n-origin\">{$v}</td></tr>";
        }

        $missesTable = $missRows ? <<<HTML
<table>
  <thead><tr><th>Clé manquante</th><th>Fallback</th></tr></thead>
  <tbody>{$missRows}</tbody>
</table>
HTML : '<p class="n-empty">Aucune clé manquante.</p>';

        $hitRows = '';
        foreach (($t['hits'] ?? []) as $h) {
            $k = htmlspecialchars($h['key']);
            $v = htmlspecialchars($h['result']);
            $hitRows .= "<tr><td class=\"n-event\">{$k}</td><td class=\"n-origin\">{$v}</td></tr>";
        }

        $hitsTable = $hitRows ? <<<HTML
<table>
  <thead><tr><th>Clé</th><th>Traduction</th></tr></thead>
  <tbody>{$hitRows}</tbody>
</table>
HTML : '<p class="n-empty">Aucune traduction résolue.</p>';

        return <<<HTML
<dl class="n-kv" style="margin-bottom:12px">
  <dt>Locale active</dt><dd style="color:#4ade80;font-weight:600">{$locale}</dd>
  <dt>Clés résolues</dt><dd style="color:#4ade80">{$hits}</dd>
  <dt>Clés manquantes</dt><dd style="color:{$this->transWarningColor($misses)}">{$misses}</dd>
</dl>

<p class="n-section-title">Locales disponibles</p>
{$localesTable}

<p class="n-section-title">Clés manquantes ({$misses})</p>
{$missesTable}

<p class="n-section-title">Clés résolues ({$hits})</p>
{$hitsTable}
HTML;
    }

    private function transWarningColor(int $misses): string
    {
        return $misses > 0 ? '#fbbf24' : '#4ade80';
    }

    /**
     * @param array<string, mixed> $auth
     */
    private function renderAuth(array $auth): string
    {
        if (!$auth['authenticated']) {
            return <<<HTML
<div class="n-auth-chip off">
  <span class="n-auth-chip-dot"></span>
  Aucun utilisateur connecté
</div>
HTML;
        }

        $user = $auth['user'];
        $id = htmlspecialchars((string) ($user['id'] ?? '—'));
        $attributes = $user['attributes'] ?? [];

        $rows = '';
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $k = htmlspecialchars($key);
            $v = htmlspecialchars(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value);
            $rows .= "<dt>{$k}</dt><dd>{$v}</dd>";
        }

        return <<<HTML
<div class="n-auth-chip on">
  <span class="n-auth-chip-dot"></span>
  Connecté &mdash; ID {$id}
</div>
<dl class="n-kv">{$rows}</dl>
HTML;
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $router
     */
    private function renderRequest(array $request, array $router): string
    {
        $route = htmlspecialchars($router['route'] ?? '—');
        $controller = htmlspecialchars($router['controller'] ?? '—');
        $action = htmlspecialchars($router['action'] ?? '—');
        $method = htmlspecialchars($request['method'] ?? '—');
        $path = htmlspecialchars($request['path'] ?? '—');
        $ip = htmlspecialchars($request['ip'] ?? '—');
        $ua = htmlspecialchars(substr($request['user_agent'] ?? '—', 0, 120));

        $paramsHtml = '';
        foreach (($router['params'] ?? []) as $k => $v) {
            $paramsHtml .= '<dt>' . htmlspecialchars($k) . '</dt>'
                . '<dd>' . htmlspecialchars((string) $v) . '</dd>';
        }

        $headersHtml = '';
        foreach (($request['headers'] ?? []) as $k => $v) {
            $headersHtml .= '<dt>' . htmlspecialchars($k) . '</dt>'
                . '<dd>' . htmlspecialchars((string) $v) . '</dd>';
        }

        $routeBlock = $paramsHtml
            ? "<p class=\"n-section-title\">Route params</p><dl class=\"n-kv\">{$paramsHtml}</dl>"
            : '';

        $headersBlock = $headersHtml
            ? "<p class=\"n-section-title\">Headers</p><dl class=\"n-kv\">{$headersHtml}</dl>"
            : '';

        return <<<HTML
<dl class="n-kv">
  <dt>Method</dt><dd>{$method}</dd>
  <dt>Path</dt><dd>{$path}</dd>
  <dt>Route</dt><dd>{$route}</dd>
  <dt>Controller</dt><dd>{$controller}</dd>
  <dt>Action</dt><dd>{$action}</dd>
  <dt>IP</dt><dd>{$ip}</dd>
  <dt>User-Agent</dt><dd>{$ua}</dd>
</dl>
{$routeBlock}
{$headersBlock}
HTML;
    }

    /**
     * @param array<int, array<string, mixed>> $queries
     */
    private function renderQueries(array $queries): string
    {
        if (empty($queries)) {
            return '<p class="n-empty">Aucune requête SQL.</p>';
        }

        $rows = '';
        foreach ($queries as $i => $q) {
            $n = $i + 1;
            $sql = htmlspecialchars($q['sql']);
            $ms = htmlspecialchars((string) $q['duration']);
            $params = htmlspecialchars(json_encode($q['params'], JSON_UNESCAPED_UNICODE));
            $rows .= <<<HTML
<tr>
  <td style="color:#52525b;width:28px">{$n}</td>
  <td class="n-sql">{$sql}</td>
  <td class="n-params">{$params}</td>
  <td class="n-ms">{$ms} ms</td>
</tr>
HTML;
        }

        return <<<HTML
<table>
  <thead>
    <tr><th>#</th><th>SQL</th><th>Params</th><th style="text-align:right">Time</th></tr>
  </thead>
  <tbody>{$rows}</tbody>
</table>
HTML;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function renderEvents(array $events): string
    {
        if (empty($events)) {
            return '<p class="n-empty">Aucun événement dispatché.</p>';
        }

        $rows = '';
        foreach ($events as $e) {
            $event = htmlspecialchars($e['event']);
            $listeners = htmlspecialchars(implode(', ', $e['listeners']));
            $ms = htmlspecialchars((string) $e['duration']);
            $rows .= <<<HTML
<tr>
  <td class="n-event">{$event}</td>
  <td class="n-origin">{$listeners}</td>
  <td class="n-ms">{$ms} ms</td>
</tr>
HTML;
        }

        return <<<HTML
<table>
  <thead>
    <tr><th>Event</th><th>Listeners</th><th style="text-align:right">Time</th></tr>
  </thead>
  <tbody>{$rows}</tbody>
</table>
HTML;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function renderLogs(array $entries): string
    {
        if (empty($entries)) {
            return '<p class="n-empty">Aucun log émis.</p>';
        }

        $rows = '';
        foreach ($entries as $e) {
            $cls = 'n-log-' . strtolower($e['level']);
            $level = htmlspecialchars($e['level']);
            $msg = htmlspecialchars($e['message']);
            $origin = htmlspecialchars($e['origin']);
            $ctx = $e['context']
                ? htmlspecialchars(json_encode($e['context'], JSON_UNESCAPED_UNICODE))
                : '';
            $rows  .= <<<HTML
<tr class="{$cls}">
  <td style="width:80px;font-weight:600">{$level}</td>
  <td>{$msg}</td>
  <td class="n-origin">{$origin}</td>
  <td style="color:#3f3f46;font-size:10px">{$ctx}</td>
</tr>
HTML;
        }

        return <<<HTML
<table>
  <thead>
    <tr><th>Level</th><th>Message</th><th>Origin</th><th>Context</th></tr>
  </thead>
  <tbody>{$rows}</tbody>
</table>
HTML;
    }

    /**
     * @param array<string, mixed> $mail
     */
    private function renderMail(array $mail): string
    {
        if ($mail['count'] === 0) {
            return '<p class="n-empty">Aucun mail envoyé.</p>';
        }

        $sent = $mail['sent'];
        $failed = $mail['failed'];
        $totalMs = $mail['total_ms'];

        $rows = '';
        foreach ($mail['mails'] as $i => $m) {
            $n = $i + 1;
            $to = htmlspecialchars($m['to']);
            $subject = htmlspecialchars($m['subject']);
            $status = htmlspecialchars($m['status']);
            $ms = htmlspecialchars((string) $m['duration_ms']);
            $error = isset($m['error']) ? htmlspecialchars($m['error']) : '—';
            $statusColor = $m['status'] === 'sent' ? '#4ade80' : '#f87171';

            $rows .= <<<HTML
<tr>
  <td style="color:#52525b;width:28px">{$n}</td>
  <td class="n-event">{$to}</td>
  <td>{$subject}</td>
  <td style="color:{$statusColor};font-weight:600">{$status}</td>
  <td class="n-origin">{$error}</td>
  <td class="n-ms">{$ms} ms</td>
</tr>
HTML;
        }

        return <<<HTML
<dl class="n-kv" style="margin-bottom:12px">
  <dt>Total</dt><dd>{$mail['count']}</dd>
  <dt>Envoyés</dt><dd style="color:#4ade80">{$sent}</dd>
  <dt>Échoués</dt><dd style="color:{$this->mailFailColor($failed)}">{$failed}</dd>
  <dt>Durée totale</dt><dd>{$totalMs} ms</dd>
</dl>

<table>
  <thead>
    <tr><th>#</th><th>To</th><th>Subject</th><th>Status</th><th>Error</th><th style="text-align:right">Time</th></tr>
  </thead>
  <tbody>{$rows}</tbody>
</table>
HTML;
    }

    private function mailFailColor(int $failed): string
    {
        return $failed > 0 ? '#f87171' : '#4ade80';
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function logBadgeColor(array $entries): string
    {
        $worst = 0;
        $map = [
            'debug' => 0, 'info' => 1, 'notice' => 1,
            'warning' => 2, 'error' => 3, 'critical' => 3,
            'alert' => 3, 'emergency' => 3,
        ];
        foreach ($entries as $e) {
            $worst = max($worst, $map[strtolower($e['level'])] ?? 0);
        }
        return match ($worst) {
            3 => '#f87171',
            2 => '#fbbf24',
            default => '#a1a1aa',
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1) . ' MB';
        }
        return round($bytes / 1024, 1) . ' KB';
    }
}