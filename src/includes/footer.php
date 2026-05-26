</main><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /app-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/inv-table.js?v=1"></script>
<script>
/* ═══════════════════════════════════════════════════════════════
   Inventorium UX Module — Toast / Shortcuts / Recently Viewed
   ═══════════════════════════════════════════════════════════════ */
(function(){
  var Inv = window.Inv = window.Inv || {};

  // ── Toast notifications ──────────────────────────────────────
  Inv.toast = function(msg, type){
    type = type || 'info';
    var c = document.getElementById('inv-toast-container');
    if (!c){
      c = document.createElement('div');
      c.id = 'inv-toast-container';
      c.style.cssText = 'position:fixed;top:70px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:90vw';
      document.body.appendChild(c);
    }
    var colors = {info:'#0ea5e9',success:'#10b981',warning:'#f59e0b',error:'#ef4444'};
    var icons  = {info:'info-circle-fill',success:'check-circle-fill',warning:'exclamation-triangle-fill',error:'x-circle-fill'};
    var t = document.createElement('div');
    t.style.cssText = 'background:#fff;border-left:4px solid '+colors[type]+';padding:11px 14px;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.15);font-size:13px;min-width:260px;max-width:380px;display:flex;align-items:center;gap:10px;color:#1f2937;animation:invToastIn .25s ease-out';
    if (document.body.classList.contains('dark')) {
      t.style.background = '#1e293b';
      t.style.color = '#e2e8f0';
    }
    t.innerHTML = '<i class="bi bi-'+icons[type]+'" style="color:'+colors[type]+';font-size:16px"></i>'+
                  '<span style="flex:1">'+msg+'</span>'+
                  '<i class="bi bi-x" style="cursor:pointer;opacity:.5;font-size:16px"></i>';
    t.lastChild.addEventListener('click', function(){ t.remove(); });
    c.appendChild(t);
    setTimeout(function(){
      t.style.transition = 'opacity .3s, transform .3s';
      t.style.opacity = '0';
      t.style.transform = 'translateX(20px)';
      setTimeout(function(){ t.remove(); }, 300);
    }, 4000);
  };

  // ── Keyboard shortcuts ───────────────────────────────────────
  var navMap = {d:'/index.php', p:'/po_list.php', i:'/item_history.php', v:'/vendor_list.php', r:'/dept_report.php'};
  var waitingG = false, gTimer;

  document.addEventListener('keydown', function(e){
    var typing = e.target.matches('input,textarea,select,[contenteditable]');
    if (typing){
      if (e.key === 'Escape') e.target.blur();
      return;
    }
    // Focus search
    if (e.key === '/' || (e.ctrlKey && e.key === 'k')){
      e.preventDefault();
      var s = document.getElementById('globalSearch') || document.querySelector('input[name="search"]');
      if (s){ s.focus(); s.select && s.select(); }
      return;
    }
    // Help
    if (e.key === '?' && e.shiftKey){
      e.preventDefault();
      Inv.showHelp();
      return;
    }
    // Esc — close dropdowns
    if (e.key === 'Escape'){
      document.querySelectorAll('.user-menu.open, .sidebar.show').forEach(function(el){ el.classList.remove('open','show'); });
      var bd = document.getElementById('sidebarBackdrop');
      if (bd) bd.classList.remove('show');
      var mod = document.getElementById('inv-shortcut-modal');
      if (mod) mod.remove();
      return;
    }
    // g + key navigation
    if (e.key === 'g'){
      waitingG = true;
      clearTimeout(gTimer);
      gTimer = setTimeout(function(){ waitingG = false; }, 1200);
      return;
    }
    if (waitingG && navMap[e.key]){
      e.preventDefault();
      waitingG = false;
      location.href = navMap[e.key];
    }
  });

  Inv.showHelp = function(){
    if (document.getElementById('inv-shortcut-modal')) return;
    var dark = document.body.classList.contains('dark');
    var bg = dark ? '#1e293b' : '#fff';
    var fg = dark ? '#e2e8f0' : '#1f2937';
    var mu = dark ? '#94a3b8' : '#6b7280';
    var m = document.createElement('div');
    m.id = 'inv-shortcut-modal';
    m.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998;display:flex;align-items:center;justify-content:center;animation:invToastIn .2s';
    m.innerHTML =
      '<div style="background:'+bg+';color:'+fg+';border-radius:12px;padding:24px;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.4)">'+
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">'+
          '<h5 style="margin:0;color:#0ea5e9">⌨️ Keyboard Shortcuts</h5>'+
          '<button onclick="document.getElementById(\'inv-shortcut-modal\').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;color:'+mu+'">×</button>'+
        '</div>'+
        '<table style="width:100%;font-size:13px"><tbody>'+
          row('Focus search', '/', mu) +
          row('Focus search (Ctrl)', 'Ctrl+K', mu) +
          row('Go to Dashboard', 'g d', mu) +
          row('Go to PO List', 'g p', mu) +
          row('Go to Items', 'g i', mu) +
          row('Go to Vendor', 'g v', mu) +
          row('Go to By Location', 'g r', mu) +
          row('Close / Blur', 'Esc', mu) +
          row('Show this help', 'Shift + ?', mu) +
        '</tbody></table>'+
      '</div>';
    function row(label, key, muted){
      return '<tr><td style="padding:7px 0;color:'+muted+'">'+label+'</td>'+
             '<td style="text-align:right"><kbd style="background:'+(dark?'#334155':'#f3f4f6')+';padding:2px 8px;border-radius:4px;font-size:11px;font-family:monospace">'+key+'</kbd></td></tr>';
    }
    m.addEventListener('click', function(e){ if (e.target === m) m.remove(); });
    document.body.appendChild(m);
  };

  // ── Recently Viewed (localStorage, 10 items) ──────────────────
  Inv.trackView = function(type, id, label){
    if (!id || !label) return;
    var list = [];
    try { list = JSON.parse(localStorage.getItem('inv_recent') || '[]'); } catch(e){}
    list = list.filter(function(x){ return !(x.type === type && String(x.id) === String(id)); });
    list.unshift({type:type, id:String(id), label:String(label).slice(0, 60), ts:Date.now()});
    list = list.slice(0, 10);
    localStorage.setItem('inv_recent', JSON.stringify(list));
    Inv.renderRecent();
  };
  Inv.getRecent = function(){
    try { return JSON.parse(localStorage.getItem('inv_recent') || '[]'); } catch(e){ return []; }
  };
  Inv.renderRecent = function(){
    var c = document.getElementById('recentViewed');
    if (!c) return;
    var list = Inv.getRecent();
    if (!list.length){ c.innerHTML = ''; return; }
    var iconMap = {po:'file-text', vendor:'building', item:'box-seam'};
    var urlMap  = {po:'/po_detail.php?seq=', vendor:'/vendor_detail.php?vn_code=', item:'/item_history.php?prd_id='};
    var html = '<div class="sidebar-divider" style="margin-top:14px">RECENT</div>';
    list.slice(0, 5).forEach(function(x){
      var ic = iconMap[x.type] || 'circle';
      var url = (urlMap[x.type] || '#') + encodeURIComponent(x.id);
      var lbl = x.label.length > 18 ? x.label.slice(0,18) + '…' : x.label;
      html += '<a class="nav-item" href="'+url+'" data-loading title="'+x.label.replace(/"/g,'&quot;')+'">'+
              '<i class="bi bi-'+ic+'"></i>'+
              '<span class="nav-label" style="font-size:11.5px;overflow:hidden;text-overflow:ellipsis">'+lbl+'</span></a>';
    });
    c.innerHTML = html;
  };
  Inv.renderRecent();
})();
</script>

<style>
@keyframes invToastIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}
kbd{font-family:ui-monospace,monospace}
</style>

<script>
// Sidebar toggle — 3 tiers:
//   Mobile (≤768): show = slide-in + backdrop
//   Tablet (769-992): show = expand 64→220px
//   Desktop (>992): collapsed = shrink 220→64px
// Dark mode toggle
(function(){
  var btn = document.getElementById('themeToggle');
  if (!btn) return;
  btn.addEventListener('click', function(){
    var isDark = document.body.classList.toggle('dark');
    var theme  = isDark ? 'dark' : 'light';
    document.cookie = 'inv_theme=' + theme + ';path=/;max-age=' + (365*24*3600) + ';SameSite=Lax';
    var icon = btn.querySelector('i');
    if (icon) icon.className = 'bi bi-' + (isDark ? 'sun' : 'moon') + '-fill';
  });
})();

// Sidebar toggle — 3 tiers:
//   Mobile (≤768): show = slide-in + backdrop
//   Tablet (769-992): show = expand 64→220px
//   Desktop (>992): collapsed = shrink 220→64px
(function() {
    var sidebar  = document.getElementById('sidebar');
    var toggle   = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar || !toggle) return;

    function isMobile() { return window.innerWidth <= 768; }
    function isTablet() { return window.innerWidth > 768 && window.innerWidth <= 992; }

    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        if (isMobile()) {
            sidebar.classList.toggle('show');
            if (backdrop) backdrop.classList.toggle('show');
        } else if (isTablet()) {
            sidebar.classList.toggle('show');  // expand width
        } else {
            sidebar.classList.toggle('collapsed');  // desktop: collapse
        }
    });

    // Backdrop click → close (mobile)
    if (backdrop) {
        backdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        });
    }

    // Nav click on mobile/tablet → close sidebar after navigation
    sidebar.querySelectorAll('.nav-item').forEach(function(item) {
        item.addEventListener('click', function() {
            if (isMobile() || isTablet()) {
                sidebar.classList.remove('show');
                if (backdrop) backdrop.classList.remove('show');
            }
        });
    });

    // Resize: cleanup state across breakpoints
    window.addEventListener('resize', function() {
        // Clean show on desktop, clean collapsed on mobile/tablet
        if (window.innerWidth > 992) {
            sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
        } else {
            sidebar.classList.remove('collapsed');
        }
    });
})();

// Loading overlay on form submit or [data-loading] click
document.querySelectorAll('form').forEach(function(f) {
    f.addEventListener('submit', function() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    });
});
document.querySelectorAll('[data-loading]').forEach(function(el) {
    el.addEventListener('click', function() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    });
});

// Global search: prefix "V:" redirects to vendor_list
var globalSearch = document.getElementById('globalSearch');
var globalSearchForm = document.getElementById('globalSearchForm');
if (globalSearch && globalSearchForm) {
    globalSearchForm.addEventListener('submit', function(e) {
        var val = globalSearch.value.trim();
        if (val.toLowerCase().indexOf('v:') === 0) {
            e.preventDefault();
            location.href = '/vendor_list.php?search=' + encodeURIComponent(val.slice(2).trim());
        }
    });
}

// Date preset helper (used by po_list, dept_report date shortcut bars)
function getDatePreset(preset) {
    var now = new Date();
    var pad = function(n) { return String(n).padStart(2, '0'); };
    var fmt = function(d) { return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()); };
    switch (preset) {
        case 'today':
            return [fmt(now), fmt(now)];
        case 'week': {
            var day = now.getDay() || 7;
            var mon = new Date(now); mon.setDate(now.getDate() - day + 1);
            return [fmt(mon), fmt(now)];
        }
        case 'month': {
            var fm = new Date(now.getFullYear(), now.getMonth(), 1);
            return [fmt(fm), fmt(now)];
        }
        case 'last_month': {
            var fm2 = new Date(now.getFullYear(), now.getMonth()-1, 1);
            var lm  = new Date(now.getFullYear(), now.getMonth(), 0);
            return [fmt(fm2), fmt(lm)];
        }
        case '3months': {
            var fm3 = new Date(now.getFullYear(), now.getMonth()-3, 1);
            return [fmt(fm3), fmt(now)];
        }
        case 'year':
            return [now.getFullYear() + '-01-01', fmt(now)];
    }
    return [null, null];
}
function applyDatePreset(preset) {
    var pair = getDatePreset(preset);
    var fromEl = document.getElementById('date_from') || document.querySelector('[name="date_from"]');
    var toEl   = document.getElementById('date_to')   || document.querySelector('[name="date_to"]');
    if (fromEl && toEl && pair[0]) {
        fromEl.value = pair[0];
        toEl.value   = pair[1];
        var form = fromEl.closest('form');
        if (form) form.submit();
    }
}
</script>
</body>
</html>
