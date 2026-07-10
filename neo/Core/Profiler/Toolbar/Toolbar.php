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
        $collectors = $this->profiler->getCollectors();

        $duration = $data['duration'];
        $memory = $this->formatBytes($data['memory']);

        $tabsHtml = '';
        $ptabsHtml = '';
        $panesHtml = '';

        foreach ($collectors as $name => $collector) {
            $collectorData = $data[$name] ?? [];

            $tabsHtml  .= $collector->renderTab($collectorData);
            $ptabsHtml .= sprintf(
                '<div class="n-ptab" id="npt-%s" onclick="neoPanel(\'%s\')">%s</div>',
                htmlspecialchars($name),
                htmlspecialchars($name),
                htmlspecialchars(ucfirst($name)),
            );
            $panesHtml .= sprintf(
                '<div id="npane-%s" style="display:none">%s</div>',
                htmlspecialchars($name),
                $collector->renderPanel($collectorData),
            );
        }

        $paneNames = json_encode(array_keys($collectors));

        return <<<HTML
<style>
  #neo-bar *{box-sizing:border-box;font-family:'JetBrains Mono',monospace,sans-serif}

  #neo-bar{
    position:fixed;bottom:0;left:0;right:0;z-index:99999;
    background:transparent;color:#a1a1aa;
    display:flex;align-items:stretch;justify-content:flex-end;height:34px;
    border-top:none;font-size:11px;
  }

  #neo-bar.expanded{
    background:#18181b;
    border-top:1px solid #27272a;
  }

  #neo-bar .n-tabs-wrapper{
    display:none;align-items:stretch;flex:1;
  }

  #neo-bar .n-tabs-wrapper.visible{
    display:flex;
  }

  #neo-bar .n-brand{
    display:flex;align-items:center;gap:8px;padding:0 14px;
    background:#7c3aed;color:#fff;font-weight:600;font-size:11px;letter-spacing:.3px;
    border-right:1px solid #5b21b6;flex-shrink:0;cursor:pointer;user-select:none;
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
  #neo-bar .n-badge{background:#27272a;border-radius:3px;padding:1px 6px;font-size:10px;color:#71717a}
  #neo-bar .n-spacer{flex:1}

  #neo-bar .n-status-chip{gap:8px;padding:0 14px;border-right:2px solid #3f3f46}
  #neo-bar .n-method{font-size:10px;font-weight:700;color:#a78bfa;letter-spacing:.5px}
  #neo-bar .n-status{font-size:12px;font-weight:700}
  #neo-bar .n-path{font-size:11px;color:#71717a;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

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
    color:#52525b;cursor:pointer;border:none;background:none;transition:color .12s;
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
    padding:3px 10px;border-radius:3px;font-size:11px;font-weight:500;margin-bottom:12px;
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
</style>

<div id="neo-bar">
  <div class="n-brand" onclick="neoToggleBar()">
    <div class="n-brand-dot"></div>
    Neo
  </div>

  <div class="n-tabs-wrapper" id="neo-tabs-wrapper">
    <div class="n-tab n-status-chip" style="cursor:default">
      <span class="n-label">Response</span>
      <span class="n-value" style="color:{$this->durationColor($duration)}">{$duration} ms</span>
    </div>
    <div class="n-tab" style="cursor:default">
      <span class="n-label">Memory</span>
      <span class="n-value">{$memory}</span>
    </div>

    {$tabsHtml}

    <div class="n-spacer"></div>
  </div>
</div>

<div id="neo-panel">
  <div class="n-ptabs">
    {$ptabsHtml}
    <button class="n-close" onclick="neoClose()">&#x2715;</button>
  </div>
  <div class="n-body">
    {$panesHtml}
  </div>
</div>

<script>
(function(){
  var current = null;
  var panes = {$paneNames};
  var STORAGE_KEY = 'neo_bar_visible';

  function applyBarState(visible) {
    var bar     = document.getElementById('neo-bar');
    var wrapper = document.getElementById('neo-tabs-wrapper');
    var panel   = document.getElementById('neo-panel');
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
    var next = !wrapper.classList.contains('visible');
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
      var pt = document.getElementById('npt-' + p);
      if (pt) pt.classList.remove('active');
    });
    document.getElementById('npane-' + name).style.display = '';
    var active = document.getElementById('npt-' + name);
    if (active) active.classList.add('active');
    panel.classList.add('open');
    current = name;
  };

  window.neoClose = function() {
    document.getElementById('neo-panel').classList.remove('open');
    document.querySelectorAll('#neo-bar .n-tab').forEach(function(t){ t.classList.remove('active'); });
    current = null;
  };

  var saved = localStorage.getItem(STORAGE_KEY);
  applyBarState(saved === '1');
})();
</script>
HTML;
    }

    private function durationColor(float $duration): string
    {
        return $duration < 200 ? '#4ade80' : ($duration < 500 ? '#fb923c' : '#f87171');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1) . ' MB';
        }
        return round($bytes / 1024, 1) . ' KB';
    }
}