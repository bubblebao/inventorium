</main><!-- /page-content -->
</div><!-- /main-area -->
</div><!-- /app-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

// Sidebar toggle — desktop: collapse, mobile: show/hide with backdrop
(function() {
    var sidebar  = document.getElementById('sidebar');
    var toggle   = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar || !toggle) return;

    function isMobile() { return window.innerWidth <= 768; }

    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        if (isMobile()) {
            sidebar.classList.toggle('show');
            if (backdrop) backdrop.classList.toggle('show');
        } else {
            sidebar.classList.toggle('collapsed');
        }
    });

    // Click backdrop → close mobile sidebar
    if (backdrop) {
        backdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        });
    }

    // Click nav-item on mobile → close sidebar after navigation
    sidebar.querySelectorAll('.nav-item').forEach(function(item) {
        item.addEventListener('click', function() {
            if (isMobile()) {
                sidebar.classList.remove('show');
                if (backdrop) backdrop.classList.remove('show');
            }
        });
    });

    // Resize: clean state when crossing breakpoint
    window.addEventListener('resize', function() {
        if (!isMobile()) {
            sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
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
