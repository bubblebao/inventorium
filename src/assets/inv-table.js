/* ═══════════════════════════════════════════════════════════════
   Inventorium Table Helper — Sortable headers + Inline filter
   ═══════════════════════════════════════════════════════════════
   Usage on any <table>:
     <table data-inv-table>
       <thead>
         <tr>
           <th data-sort="text">Code</th>
           <th data-sort="text">Name</th>
           <th data-sort="num" class="text-end">Total</th>
           <th data-sort="date">Date</th>
           <th data-nosort>Action</th>
         </tr>
       </thead>
       <tbody>...</tbody>
     </table>

   Filter inputs are auto-injected as 2nd <tr> in <thead>.
   Sort types: "text" | "num" | "date" (YYYY-MM-DD or DD/MM/YYYY)
   Skip filter on column: add data-nofilter to <th>
*/
(function () {
  'use strict';

  function parseNum(v) {
    if (v == null) return NaN;
    var s = String(v).replace(/[,\s฿]/g, '').replace(/[^\d.\-]/g, '');
    return s === '' ? NaN : parseFloat(s);
  }

  function parseDate(v) {
    if (!v) return 0;
    var s = String(v).trim();
    // DD/MM/YYYY
    var m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (m) return new Date(+m[3], +m[2] - 1, +m[1]).getTime();
    // YYYY-MM-DD
    m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
    if (m) return new Date(+m[1], +m[2] - 1, +m[3]).getTime();
    var t = Date.parse(s);
    return isNaN(t) ? 0 : t;
  }

  function cellText(tr, idx) {
    var td = tr.children[idx];
    return td ? (td.innerText || td.textContent || '').trim() : '';
  }

  function compareFn(idx, type, dir) {
    var sign = dir === 'desc' ? -1 : 1;
    return function (a, b) {
      var av = cellText(a, idx), bv = cellText(b, idx);
      var res;
      if (type === 'num') {
        var an = parseNum(av), bn = parseNum(bv);
        if (isNaN(an) && isNaN(bn)) res = 0;
        else if (isNaN(an)) res = 1;
        else if (isNaN(bn)) res = -1;
        else res = an - bn;
      } else if (type === 'date') {
        res = parseDate(av) - parseDate(bv);
      } else {
        res = av.localeCompare(bv, 'th', { numeric: true, sensitivity: 'base' });
      }
      return res * sign;
    };
  }

  function initTable(table) {
    if (table._invInit) return;
    table._invInit = true;

    var thead = table.tHead;
    if (!thead || !thead.rows.length) return;
    var headRow = thead.rows[0];
    var ths = Array.prototype.slice.call(headRow.cells);
    var tbody = table.tBodies[0];
    if (!tbody) return;

    // ── Sort: clickable headers ────────────────────────────────
    ths.forEach(function (th, idx) {
      if (th.hasAttribute('data-nosort') || !th.hasAttribute('data-sort')) return;
      th.style.cursor = 'pointer';
      th.style.userSelect = 'none';
      th.title = 'Click to sort';

      // Sort indicator
      var indicator = document.createElement('span');
      indicator.className = 'inv-sort-ind';
      indicator.style.cssText = 'display:inline-block;margin-left:4px;opacity:.35;font-size:10px;transition:opacity .15s';
      indicator.textContent = '↕';
      th.appendChild(document.createTextNode(' '));
      th.appendChild(indicator);

      th.addEventListener('click', function () {
        var type = th.getAttribute('data-sort') || 'text';
        var curDir = th.getAttribute('data-dir');
        var nextDir = curDir === 'asc' ? 'desc' : 'asc';

        // Reset other headers
        ths.forEach(function (other) {
          if (other === th) return;
          other.removeAttribute('data-dir');
          var ind = other.querySelector('.inv-sort-ind');
          if (ind) { ind.textContent = '↕'; ind.style.opacity = '.35'; }
        });

        th.setAttribute('data-dir', nextDir);
        indicator.textContent = nextDir === 'asc' ? '↑' : '↓';
        indicator.style.opacity = '1';

        var rows = Array.prototype.slice.call(tbody.rows);
        rows.sort(compareFn(idx, type, nextDir));
        var frag = document.createDocumentFragment();
        rows.forEach(function (r) { frag.appendChild(r); });
        tbody.appendChild(frag);
      });
    });

    // ── Filter row (hidden by default — toggle via button) ─────
    var filterRow = document.createElement('tr');
    filterRow.className = 'inv-filter-row';
    filterRow.style.display = 'none';
    var inputs = [];
    ths.forEach(function (th, idx) {
      var td = document.createElement('th');
      td.style.cssText = 'padding:3px 6px;background:rgba(0,0,0,.04);border-top:1px solid rgba(0,0,0,.06)';
      if (document.body.classList.contains('dark')) {
        td.style.background = 'rgba(255,255,255,.04)';
        td.style.borderTopColor = 'rgba(255,255,255,.08)';
      }
      if (th.hasAttribute('data-nofilter')) {
        filterRow.appendChild(td);
        inputs.push(null);
        return;
      }
      var inp = document.createElement('input');
      inp.type = 'search';
      inp.placeholder = '🔍';
      inp.setAttribute('aria-label', 'Filter ' + (th.innerText || '').trim());
      inp.style.cssText = 'width:100%;font-size:11px;padding:3px 6px;border:1px solid rgba(0,0,0,.15);border-radius:4px;background:#fff;color:#1f2937;outline:none;font-weight:normal';
      if (document.body.classList.contains('dark')) {
        inp.style.background = '#0f172a';
        inp.style.color = '#e2e8f0';
        inp.style.borderColor = 'rgba(255,255,255,.15)';
      }
      td.appendChild(inp);
      filterRow.appendChild(td);
      inputs.push(inp);
    });
    thead.appendChild(filterRow);

    function applyFilter() {
      var terms = inputs.map(function (i) {
        return i ? i.value.trim().toLowerCase() : '';
      });
      var anyActive = terms.some(function (t) { return t !== ''; });
      var visibleCount = 0;
      Array.prototype.forEach.call(tbody.rows, function (tr) {
        if (tr.classList.contains('inv-no-filter')) return;
        var show = true;
        if (anyActive) {
          for (var i = 0; i < terms.length; i++) {
            if (!terms[i]) continue;
            var cell = cellText(tr, i).toLowerCase();
            if (cell.indexOf(terms[i]) === -1) { show = false; break; }
          }
        }
        tr.style.display = show ? '' : 'none';
        if (show) visibleCount++;
      });

      // Update badge if exists
      var badge = table.closest('.card') && table.closest('.card').querySelector('[data-inv-count]');
      if (badge) {
        var total = badge.getAttribute('data-inv-count-total') || badge.textContent;
        if (!badge.hasAttribute('data-inv-count-total')) badge.setAttribute('data-inv-count-total', total);
        badge.textContent = anyActive ? (visibleCount + ' / ' + total) : total;
      }
    }

    var debounce;
    inputs.forEach(function (inp) {
      if (!inp) return;
      inp.addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(applyFilter, 80);
      });
      inp.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    // ── Inject toggle button into card header ─────────────────
    var card = table.closest('.card');
    var header = card && card.querySelector('.card-header-inv');
    if (header && !header.querySelector('.inv-filter-toggle')) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'inv-filter-toggle btn btn-sm';
      btn.title = 'Toggle column filters';
      btn.style.cssText = 'background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);padding:2px 8px;font-size:11.5px;border-radius:6px;flex-shrink:0';
      btn.innerHTML = '<i class="bi bi-funnel"></i>';
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var shown = filterRow.style.display !== 'none';
        filterRow.style.display = shown ? 'none' : '';
        btn.style.background = shown ? 'rgba(255,255,255,.15)' : 'rgba(255,255,255,.35)';
        if (!shown) {
          var firstInput = inputs.find(function (i) { return i; });
          if (firstInput) setTimeout(function () { firstInput.focus(); }, 50);
        } else {
          inputs.forEach(function (i) { if (i) i.value = ''; });
          applyFilter();
        }
      });

      // Insert inside existing right-side flex container (d-flex gap-1)
      // or wrap last child + button together — avoids 3-child justify-content-between
      var rightGroup = null;
      var children = Array.prototype.slice.call(header.children);
      // Look for an existing d-flex group (contains export/action buttons)
      children.forEach(function (c) {
        if (c.classList && (c.classList.contains('d-flex') || c.classList.contains('gap-1'))) {
          rightGroup = c;
        }
      });

      if (rightGroup) {
        // Already has a flex group — just append into it
        rightGroup.appendChild(btn);
      } else {
        // No flex group — wrap last child (export link) + funnel into one
        var lastChild = header.lastElementChild;
        if (lastChild && lastChild !== header.firstElementChild) {
          var wrapper = document.createElement('div');
          wrapper.style.cssText = 'display:flex;align-items:center;gap:4px';
          header.insertBefore(wrapper, lastChild);
          wrapper.appendChild(lastChild);
          wrapper.appendChild(btn);
        } else {
          header.appendChild(btn);
        }
      }
    }
  }

  function initAll() {
    document.querySelectorAll('table[data-inv-table]').forEach(initTable);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  // Re-init on dark mode toggle (refresh filter input colors)
  var darkBtn = document.getElementById('themeToggle');
  if (darkBtn) {
    darkBtn.addEventListener('click', function () {
      setTimeout(function () {
        document.querySelectorAll('.inv-filter-row input').forEach(function (inp) {
          var dark = document.body.classList.contains('dark');
          inp.style.background = dark ? '#0f172a' : '#fff';
          inp.style.color = dark ? '#e2e8f0' : '#1f2937';
          inp.style.borderColor = dark ? 'rgba(255,255,255,.15)' : 'rgba(0,0,0,.15)';
        });
        document.querySelectorAll('.inv-filter-row > th').forEach(function (th) {
          var dark = document.body.classList.contains('dark');
          th.style.background = dark ? 'rgba(255,255,255,.04)' : 'rgba(0,0,0,.04)';
          th.style.borderTopColor = dark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';
        });
      }, 0);
    });
  }
})();
