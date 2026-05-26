<?php
$page_title   = $page_title   ?? 'Dashboard';
$current_page = basename($_SERVER['PHP_SELF']);

$nav_items = [
    ['file' => 'index.php',        'icon' => 'bi-speedometer2',  'label' => t('dashboard')],
    ['file' => 'po_list.php',      'icon' => 'bi-file-text',     'label' => t('po_list')],
    ['file' => 'item_history.php', 'icon' => 'bi-box-seam',      'label' => t('item_search')],
    ['file' => 'vendor_list.php',  'icon' => 'bi-building',      'label' => t('vendor')],
    ['file' => 'dept_report.php',  'icon' => 'bi-bar-chart-line','label' => t('dept_report')],
];

$_user = current_user();
$_is_admin = ($_user['role'] ?? '') === 'admin';

// Build current URL for lang switcher (preserve query params)
$current_qs = $_SERVER['QUERY_STRING'] ?? '';
parse_str($current_qs, $qs_arr);
$qs_arr['lang'] = $GLOBALS['LANG'] === 'th' ? 'en' : 'th';
$lang_switch_url = '?' . http_build_query($qs_arr);
$next_lang_label = $GLOBALS['LANG'] === 'th' ? 'EN' : 'TH';
$next_lang_flag  = $GLOBALS['LANG'] === 'th' ? '🇺🇸' : '🇹🇭';

// Theme (light/dark) — cookie based
$current_theme = $_COOKIE['inv_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> — Inventorium</title>
<link rel="icon" type="image/png" href="/assets/inventorium-logo.png">
<link rel="apple-touch-icon" href="/assets/inventorium-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ── Variables (Light theme — default) ── */
:root{
  --primary:#1e3a5f; --primary-lt:#2d5a8e;
  --accent:#0ea5e9;  --gold:#f59e0b;
  --bg:#f0f4f8; --surface:#fff; --surface-2:#f8fafc;
  --border:#e2e8f0;
  --success:#10b981; --warning:#f59e0b; --danger:#ef4444; --info:#0ea5e9;
  --text:#1e293b; --muted:#64748b;
  --sidebar-w:220px;
}

/* ── Dark theme overrides ── */
body.dark{
  --bg:#0f172a; --surface:#1e293b; --surface-2:#0f172a;
  --border:#334155;
  --text:#e2e8f0; --muted:#94a3b8;
  --primary:#1e3a5f; --primary-lt:#2d5a8e;
}
body.dark{color:var(--text)}

/* ── Cards / Layout ── */
body.dark .card{box-shadow:0 1px 3px rgba(0,0,0,.4),0 1px 2px rgba(0,0,0,.3);background:var(--surface);color:var(--text)}
body.dark .card-body{color:var(--text)}
body.dark .top-header{background:var(--surface);border-bottom-color:var(--border)}
body.dark .card-footer{background:var(--surface-2)!important;border-top-color:var(--border)!important;color:var(--text)}

/* ── Form inputs ── */
body.dark .form-control,body.dark .form-select,body.dark .header-search input{
  background:var(--surface-2)!important;color:var(--text)!important;border-color:var(--border)!important}
body.dark .form-control:focus,body.dark .form-select:focus{background:var(--surface)!important}
body.dark .form-control::placeholder,body.dark .header-search input::placeholder{color:var(--muted)}
body.dark .form-label{color:var(--text)!important}
body.dark input[type="date"]{color-scheme:dark}

/* ── Tables (forced override Bootstrap defaults) ── */
body.dark .table,body.dark .table-inv{
  --bs-table-color:var(--text);--bs-table-bg:transparent;
  --bs-table-striped-color:var(--text);--bs-table-hover-color:var(--text);
  --bs-table-border-color:var(--border);color:var(--text)}
body.dark .table-inv tbody tr{border-bottom-color:var(--border)}
body.dark .table-inv tbody tr:nth-child(even){background:rgba(255,255,255,.03)}
body.dark .table-inv tbody tr:hover,
body.dark .table-inv tbody tr:nth-child(even):hover{background:rgba(14,165,233,.15)!important}
body.dark .table tbody td,
body.dark .table-inv tbody td,
body.dark .table tbody td *,
body.dark .table-inv tbody td *{color:var(--text)!important}
body.dark .table tbody td .text-muted,
body.dark .table-inv tbody td .text-muted,
body.dark .table tbody td small,
body.dark .table-inv tbody td small,
body.dark .table tbody td [style*="--muted"],
body.dark .table-inv tbody td [style*="--muted"]{color:var(--muted)!important}
body.dark .table tbody td code,
body.dark .table-inv tbody td code{color:#7dd3fc!important;background:rgba(125,211,252,.1)}
body.dark .table tbody tr{border-color:var(--border)}
body.dark .table-striped tbody tr:nth-of-type(odd){background:rgba(255,255,255,.03);color:var(--text)}
body.dark .table-hover tbody tr:hover{background:rgba(14,165,233,.15);color:var(--text)}
body.dark thead th{color:#cbd5e1!important}

/* Bootstrap table-danger/warning/success rows in dark mode */
body.dark tr.table-danger,
body.dark tr.table-danger td,
body.dark tr.table-danger td *{
  background-color:rgba(239,68,68,.12)!important;color:#fecaca!important}
body.dark tr.table-danger:hover,
body.dark tr.table-danger:hover td{background-color:rgba(239,68,68,.2)!important}
body.dark tr.table-warning,
body.dark tr.table-warning td,
body.dark tr.table-warning td *{
  background-color:rgba(245,158,11,.12)!important;color:#fde68a!important}
body.dark tr.table-success,
body.dark tr.table-success td,
body.dark tr.table-success td *{
  background-color:rgba(16,185,129,.12)!important;color:#a7f3d0!important}
body.dark tr.table-danger code,
body.dark tr.table-warning code,
body.dark tr.table-success code{background:rgba(255,255,255,.1)!important}

/* ── Bootstrap utility overrides ── */
body.dark .text-muted{color:var(--muted)!important}
body.dark .text-secondary{color:var(--muted)!important}
body.dark .text-dark{color:var(--text)!important}
body.dark .fw-bold,body.dark .fw-semibold{color:inherit}
body.dark small{color:var(--muted)}

/* ── Code blocks ── */
body.dark code{color:#7dd3fc;background:rgba(125,211,252,.1);padding:1px 6px;border-radius:3px}

/* ── Inline style fixes (var(--muted) text) ── */
body.dark [style*="color:var(--muted)"],
body.dark [style*="color: var(--muted)"]{color:var(--muted)!important}
body.dark [style*="color:var(--text)"]{color:var(--text)!important}
body.dark [style*="color:var(--primary)"]:not(.card-header-inv){color:#7dd3fc!important}
body.dark [style*="background:var(--surface-2)"]{background:var(--surface-2)!important}

/* ── Stat cards ── */
body.dark .stat-card .stat-val{color:var(--text)}
body.dark .stat-card .stat-label{color:var(--muted)}

/* ── Buttons ── */
body.dark .btn-inv-outline{background:var(--surface);color:var(--text);border-color:var(--border)}
body.dark .btn-inv-outline:hover{background:var(--surface-2)}
body.dark .lang-switch,body.dark .user-btn,body.dark .theme-toggle{background:var(--surface-2);color:var(--text);border-color:var(--border)}
body.dark .user-dropdown{background:var(--surface);border-color:var(--border);box-shadow:0 8px 24px rgba(0,0,0,.5)}
body.dark .dropdown-item{color:var(--text)}
body.dark .dropdown-item:hover{background:var(--surface-2)}

/* ── Pagination ── */
body.dark .page-link{background:var(--surface);color:var(--text);border-color:var(--border)}
body.dark .page-item.active .page-link{background:var(--accent);border-color:var(--accent);color:#fff}
body.dark .page-item.disabled .page-link{background:var(--surface-2);color:var(--muted);border-color:var(--border)}

/* ── Loading overlay ── */
body.dark .loading-overlay{background:rgba(15,23,42,.85)}

/* ── Badges (เพิ่มสีให้ contrast ดีใน dark) ── */
body.dark .badge-normal  {background:#1e3a5f;color:#bae6fd;border-color:#1e40af}
body.dark .badge-src     {background:#334155;color:#e2e8f0;border-color:#475569}
body.dark .badge-overdue {background:rgba(239,68,68,.18);color:#fca5a5;border-color:#7f1d1d}
body.dark .badge-due-soon{background:rgba(245,158,11,.2);color:#fcd34d;border-color:#78350f}
body.dark .badge-paid    {background:rgba(16,185,129,.18);color:#86efac;border-color:#14532d}
body.dark .badge.bg-secondary{background:#334155!important;color:#cbd5e1!important}

/* ── Bootstrap alert ── */
body.dark .alert-info{background:#0c4a6e!important;color:#7dd3fc!important;border-color:#0369a1!important}
body.dark .alert-warning{background:#451a03!important;color:#fcd34d!important;border-color:#92400e!important}

/* ── Links ── */
body.dark a:not(.btn):not(.dropdown-item):not(.nav-item):not(.card-header-inv a){color:#7dd3fc}
body.dark a:not(.btn):not(.dropdown-item):hover{color:#38bdf8}

/* ── Chart canvas — ปรับให้สีตัดกับพื้นมืด ── */
body.dark canvas{filter:brightness(1.05)}

/* ── Item history & detail (specific) ── */
body.dark .text-success{color:#86efac!important}
body.dark .text-danger{color:#fca5a5!important}
body.dark .text-warning{color:#fcd34d!important}

/* ── Date shortcut bar ── */
body.dark .date-shortcuts .btn-inv-outline{background:var(--surface);color:var(--text)}

/* ── Tfoot ── */
body.dark tfoot{background:var(--surface-2)!important;color:var(--text)}
body.dark tfoot td{color:var(--text)!important}

/* ── Reset ── */
*{box-sizing:border-box}
body{font-family:'Inter',system-ui,sans-serif;font-size:14px;color:var(--text);background:var(--bg);margin:0}

/* ── Layout ── */
.app-layout{display:flex;min-height:100vh}
.sidebar{width:var(--sidebar-w);min-height:100vh;position:fixed;top:0;left:0;z-index:200;
  background:linear-gradient(180deg,#1e3a5f 0%,#162d4a 100%);overflow-y:auto;transition:width .25s}
.sidebar.collapsed{width:64px}
.main-area{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;transition:margin-left .25s}
.sidebar.collapsed ~ .main-area{margin-left:64px}

/* ── Sidebar ── */
.sidebar-brand{padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.1);white-space:nowrap;overflow:hidden;display:flex;align-items:center;gap:10px;justify-content:center}
.brand-logo{width:36px;height:36px;border-radius:8px;flex-shrink:0;object-fit:cover}
.brand-text{font-size:18px;font-weight:800;letter-spacing:-.5px}
.brand-text .inv{color:#fff}.brand-text .orium{color:var(--accent)}
.sidebar.collapsed .sidebar-brand{padding:14px 8px}
.sidebar.collapsed .brand-logo{width:40px;height:40px}
.sidebar-divider{font-size:10px;font-weight:600;letter-spacing:1px;color:rgba(255,255,255,.35);
  padding:16px 20px 6px;text-transform:uppercase;white-space:nowrap;overflow:hidden}
.sidebar.collapsed .sidebar-divider,.sidebar.collapsed .nav-label,.sidebar.collapsed .brand-text{display:none}
.nav-item{display:flex;align-items:center;gap:12px;padding:10px 20px;color:rgba(255,255,255,.65);
  text-decoration:none;font-size:13.5px;font-weight:500;transition:all .18s;
  border-left:3px solid transparent;white-space:nowrap;overflow:hidden}
.nav-item:hover{background:rgba(255,255,255,.1);color:#fff;border-left-color:rgba(255,255,255,.3)}
.nav-item.active{background:rgba(255,255,255,.15);color:#fff;border-left-color:var(--accent)}
.nav-item i{font-size:18px;width:20px;text-align:center;flex-shrink:0}
.sidebar.collapsed .nav-item{padding:12px;justify-content:center;border-left:none;border-radius:0}
.sidebar.collapsed .nav-item.active{border-left:none;border-right:3px solid var(--accent)}

/* ── Top Header ── */
.top-header{position:sticky;top:0;z-index:100;height:60px;background:#fff;
  border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 24px;gap:16px}
.sidebar-toggle{background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;
  padding:6px;border-radius:8px;transition:background .15s;flex-shrink:0}
.sidebar-toggle:hover{background:var(--surface-2);color:var(--text)}
.page-breadcrumb{font-size:15px;font-weight:600;color:var(--text);white-space:nowrap}
.page-breadcrumb .bc-parent{color:var(--muted);font-weight:400}
.header-search{position:relative;flex:1;max-width:380px}
.header-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:15px}
.header-search input{width:100%;border:1.5px solid var(--border);border-radius:24px;
  padding:8px 16px 8px 36px;font-size:13.5px;background:var(--surface-2);transition:all .2s;font-family:inherit}
.header-search input:focus{outline:none;border-color:var(--accent);background:#fff;box-shadow:0 0 0 3px rgba(14,165,233,.1)}
.header-right{margin-left:auto;display:flex;align-items:center;gap:12px}
.header-date{font-size:12px;color:var(--muted);white-space:nowrap}
.db-status{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--success)}
.db-status i{font-size:8px}
.lang-switch{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;
  border-radius:20px;background:var(--surface-2);color:var(--text);text-decoration:none;
  font-size:12px;font-weight:600;border:1px solid var(--border);transition:all .15s}
.lang-switch:hover{background:var(--accent);color:#fff;border-color:var(--accent);transform:translateY(-1px);
  box-shadow:0 2px 6px rgba(14,165,233,.25)}
.lang-flag{font-size:14px;line-height:1}
.lang-label{letter-spacing:.3px}
.theme-toggle{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;
  border-radius:50%;background:var(--surface-2);color:var(--text);border:1px solid var(--border);
  cursor:pointer;transition:all .2s;font-size:14px}
.theme-toggle:hover{background:var(--accent);color:#fff;border-color:var(--accent);transform:rotate(15deg)}
.user-menu{position:relative}
.user-btn{display:inline-flex;align-items:center;gap:8px;padding:5px 12px 5px 6px;
  border-radius:20px;background:var(--surface-2);color:var(--text);text-decoration:none;
  font-size:12.5px;font-weight:600;border:1px solid var(--border);cursor:pointer;transition:all .15s}
.user-btn:hover{background:var(--primary);color:#fff;border-color:var(--primary)}
.user-avatar{width:24px;height:24px;border-radius:50%;background:var(--accent);color:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700}
.user-dropdown{position:absolute;right:0;top:calc(100% + 6px);min-width:180px;
  background:#fff;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);
  border:1px solid var(--border);padding:6px;z-index:300;display:none}
.user-menu.open .user-dropdown{display:block}
.user-info{padding:10px 12px;border-bottom:1px solid var(--border);margin-bottom:4px}
.user-info-name{font-size:13px;font-weight:600;color:var(--text)}
.user-info-role{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-top:2px}
.dropdown-item{display:flex;align-items:center;gap:10px;padding:8px 12px;
  border-radius:6px;color:var(--text);text-decoration:none;font-size:13px;transition:background .12s}
.dropdown-item:hover{background:var(--surface-2);color:var(--accent)}
.dropdown-item.logout{color:var(--danger)}
.dropdown-item.logout:hover{background:#fef2f2}

/* ── Page Content ── */
.page-content{flex:1;padding:24px}

/* ── Cards ── */
.card{border:none;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.05)}
.card-header-inv{background:var(--primary);color:#fff;border-radius:12px 12px 0 0!important;padding:12px 20px;font-weight:600}

/* ── Stat Cards ── */
.stat-card{border-left:4px solid var(--card-color,var(--accent))!important}
.stat-card .stat-val{font-size:28px;font-weight:700;color:var(--primary);line-height:1.1}
.stat-card .stat-label{font-size:12px;color:var(--muted);font-weight:500}
.stat-card .stat-icon{font-size:28px;opacity:.15;color:var(--card-color,var(--accent))}

/* ── Tables ── */
.table-inv{border-radius:0 0 12px 12px;overflow:hidden}
.table-inv thead th{background:var(--primary);color:#fff;font-size:11.5px;font-weight:600;
  text-transform:uppercase;letter-spacing:.5px;padding:11px 16px;border:none;white-space:nowrap}
.table-inv thead th.sortable{cursor:pointer;user-select:none}
.table-inv thead th.sortable:hover{background:var(--primary-lt)}
.table-inv thead th .sort-icon{margin-left:4px;opacity:.5;font-size:10px}
.table-inv thead th.sort-active .sort-icon{opacity:1;color:var(--accent)}
.table-inv tbody tr{border-bottom:1px solid var(--border);transition:background .12s}
.table-inv tbody tr:hover{background:#e0f2fe;cursor:pointer}
.table-inv tbody tr:nth-child(even){background:var(--surface-2)}
.table-inv tbody tr:nth-child(even):hover{background:#e0f2fe}
.table-inv tbody td{padding:11px 16px;vertical-align:middle}

/* ── Badges ── */
.badge-inv{border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600;border:1px solid transparent}
.badge-overdue {background:#fef2f2;color:#dc2626;border-color:#fca5a5}
.badge-due-soon{background:#fffbeb;color:#d97706;border-color:#fcd34d}
.badge-paid    {background:#f0fdf4;color:#16a34a;border-color:#86efac}
.badge-normal  {background:#f0f9ff;color:#0369a1;border-color:#7dd3fc}
.badge-src     {background:#f1f5f9;color:#475569;border-color:#cbd5e1}

/* ── Date Shortcut Bar ── */
.date-shortcuts{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}
.date-shortcuts .btn{border-radius:20px;font-size:12px;padding:3px 12px;font-weight:500}

/* ── Buttons ── */
.btn-inv-primary{background:var(--primary);color:#fff;border:none;border-radius:8px;font-weight:500}
.btn-inv-primary:hover{background:var(--primary-lt);color:#fff}
.btn-inv-outline{border:1.5px solid var(--border);background:#fff;color:var(--text);border-radius:8px;font-weight:500}
.btn-inv-outline:hover{background:var(--surface-2)}

/* ── Loading overlay ── */
.loading-overlay{display:none;position:fixed;inset:0;background:rgba(255,255,255,.75);
  z-index:9999;align-items:center;justify-content:center}
.loading-overlay .spinner-border{width:3rem;height:3rem}

/* ── Print ── */
@media print{
  .sidebar,.top-header,.no-print{display:none!important}
  .main-area{margin-left:0!important}
  .page-content{padding:0!important}
  .card{box-shadow:none!important;border:1px solid #ddd!important}
  .table-inv tbody tr:hover{background:transparent!important}
  .btn{display:none!important}
}

/* ════════════════════════════════════════════════════════════════════════
   RESPONSIVE — 4 breakpoints
   📱 Mobile  ≤768   → sidebar slide-in + 1-col cards
   📱 Tablet  769-992 → sidebar collapsed (icon) + 2-col cards
   💻 Laptop  993-1279 → sidebar full + 2-4 col cards
   🖥️ Desktop ≥1280  → all full + max-width container
   ════════════════════════════════════════════════════════════════════════ */

/* ── Desktop ultra-wide: prevent stretched look ── */
@media (min-width: 1600px){
  .page-content{max-width:1600px;margin:0 auto;width:100%}
}

/* ── Laptop: cards always 4 cols ── */
@media (min-width: 992px){
  .row.g-3 > .col-sm-6.col-xl-3{flex:0 0 25%;max-width:25%}
}

/* ── Tablet (≤992px): sidebar collapsed by default + compact ── */
@media (max-width: 992px){
  .sidebar{width:64px}
  .sidebar .sidebar-divider,.sidebar .nav-label,.sidebar .brand-text{display:none}
  .main-area{margin-left:64px}
  .stat-card .stat-val{font-size:22px}
  .top-header{padding:0 12px;gap:8px}
  .page-content{padding:16px}
  .page-breadcrumb{font-size:13px}
  .header-search{max-width:none}
  .header-date{display:none}
  .nav-item{padding:12px;justify-content:center;border-left:none}
  .nav-item.active{border-left:none;border-right:3px solid var(--accent)}
  /* Compact tables for tablet */
  .table-inv thead th{padding:8px 10px;font-size:10.5px}
  .table-inv tbody td{padding:8px 10px;font-size:12.5px}
}

/* ── Mobile (≤768px): sidebar HIDDEN by default, slide-in via toggle ── */
@media (max-width: 768px){
  .sidebar{
    width:240px;transform:translateX(-100%);
    transition:transform .25s ease-out;
    box-shadow:none;
  }
  .sidebar .sidebar-divider,.sidebar .nav-label,.sidebar .brand-text{display:inline-block}
  .sidebar .nav-item{padding:12px 20px;justify-content:flex-start;border-left:3px solid transparent}
  .sidebar .nav-item.active{border-left-color:var(--accent);border-right:none}
  .sidebar.show{transform:translateX(0);box-shadow:4px 0 24px rgba(0,0,0,.3)}
  .main-area{margin-left:0!important}
  .sidebar-backdrop{
    display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);
    z-index:199;opacity:0;transition:opacity .25s
  }
  .sidebar-backdrop.show{display:block;opacity:1}

  /* Top header: more compact */
  .top-header{height:54px;padding:0 10px}
  .header-search{display:none}  /* hide search bar on mobile */
  .lang-switch .lang-label{display:none}  /* flag only */
  .user-btn span:not(.user-avatar){display:none}  /* avatar only */
  .db-status{display:none}

  /* Cards: 1 column */
  .row.g-3 > .col-sm-6,
  .row.g-3 > .col-md-6,
  .row.g-3 > .col-md-4{flex:0 0 100%;max-width:100%}

  /* Stat cards smaller */
  .stat-card .stat-val{font-size:24px}
  .stat-card .stat-icon{font-size:24px}

  /* Page content less padding */
  .page-content{padding:12px}

  /* Tables: allow horizontal scroll */
  .table-responsive{overflow-x:auto;-webkit-overflow-scrolling:touch}
  .card-header-inv{padding:10px 14px;font-size:13px}

  /* Date shortcuts: smaller buttons */
  .date-shortcuts .btn{padding:2px 8px;font-size:11px}

  /* Filter form: full width fields */
  .card-body .row.g-2 > [class*="col-"]{flex:0 0 100%;max-width:100%}

  /* Item detail tables stack */
  .table-inv thead th{padding:8px 10px;font-size:10.5px}
  .table-inv tbody td{padding:8px 10px;font-size:12px}
}

/* ── Very small mobile (≤480px) ── */
@media (max-width: 480px){
  .top-header{padding:0 8px}
  .page-content{padding:8px}
  .stat-card .stat-val{font-size:20px}
  .lang-switch{padding:4px 8px}
}

/* ── Sidebar backdrop (only visible on mobile when sidebar shown) ── */
.sidebar-backdrop{display:none}
</style>
<script>
// Apply theme BEFORE body renders → ไม่มี flash of light
(function(){
  var theme = document.cookie.match(/inv_theme=(dark|light)/);
  if (theme && theme[1] === 'dark') document.documentElement.setAttribute('data-pre-dark','1');
})();
</script>
</head>
<body class="<?= $current_theme === 'dark' ? 'dark' : '' ?>">

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="loading-overlay" id="loadingOverlay">
  <div class="text-center">
    <div class="spinner-border text-primary"></div>
    <div class="mt-2 fw-semibold" style="color:var(--primary)"><?= t('loading') ?></div>
  </div>
</div>

<div class="app-layout">

<!-- ── Sidebar ── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img src="/assets/inventorium-logo.png" alt="Inventorium" class="brand-logo">
    <span class="brand-text"><span class="inv">Invent</span><span class="orium">orium</span></span>
  </div>
  <div class="sidebar-divider"><?= t('menu_main') ?></div>
  <nav>
    <?php foreach ($nav_items as $item): ?>
    <a class="nav-item <?= $current_page === $item['file'] ? 'active' : '' ?>"
       href="/<?= $item['file'] ?>" data-loading
       title="<?= htmlspecialchars($item['label']) ?>">
      <i class="bi <?= $item['icon'] ?>"></i>
      <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
</aside>

<!-- ── Main Area ── -->
<div class="main-area">

<!-- Top Header -->
<header class="top-header">
  <button class="sidebar-toggle" id="sidebarToggle" title="ซ่อน/แสดง sidebar">
    <i class="bi bi-list"></i>
  </button>
  <div class="page-breadcrumb">
    <?php if ($current_page !== 'index.php'): ?>
      <span class="bc-parent"><a href="/index.php" style="color:var(--muted);text-decoration:none"><?= t('dashboard') ?></a> /</span>
    <?php endif; ?>
    <?= htmlspecialchars($page_title) ?>
  </div>

  <form class="header-search" action="/po_list.php" method="GET" id="globalSearchForm">
    <i class="bi bi-search"></i>
    <input type="text" name="inv_no" id="globalSearch"
           placeholder="ค้นหา PO... (หรือ V: ชื่อ vendor)"
           autocomplete="off">
  </form>

  <div class="header-right">
    <button type="button" class="theme-toggle" id="themeToggle" title="Toggle dark/light mode">
      <i class="bi bi-<?= $current_theme === 'dark' ? 'sun' : 'moon' ?>-fill"></i>
    </button>
    <a href="<?= htmlspecialchars($lang_switch_url) ?>" class="lang-switch" title="Switch language">
      <span class="lang-flag"><?= $next_lang_flag ?></span>
      <span class="lang-label"><?= $next_lang_label ?></span>
    </a>

    <div class="user-menu" id="userMenu">
      <button class="user-btn" type="button" onclick="document.getElementById('userMenu').classList.toggle('open')">
        <span class="user-avatar"><?= strtoupper(substr($_user['username'] ?? '?', 0, 1)) ?></span>
        <span><?= htmlspecialchars($_user['name'] ?? 'User') ?></span>
        <i class="bi bi-chevron-down" style="font-size:10px"></i>
      </button>
      <div class="user-dropdown">
        <div class="user-info">
          <div class="user-info-name"><?= htmlspecialchars($_user['name'] ?? 'User') ?></div>
          <div class="user-info-role"><?= htmlspecialchars($_user['role'] ?? '') ?> · @<?= htmlspecialchars($_user['username'] ?? '') ?></div>
        </div>
        <?php if ($_is_admin): ?>
        <a href="/admin_log.php" class="dropdown-item">
          <i class="bi bi-shield-check"></i> Audit Log
        </a>
        <?php endif; ?>
        <a href="/logout.php" class="dropdown-item logout">
          <i class="bi bi-box-arrow-right"></i> <?= $GLOBALS['LANG']==='th'?'ออกจากระบบ':'Sign Out' ?>
        </a>
      </div>
    </div>

    <span class="db-status"><i class="bi bi-circle-fill"></i> Backoffice</span>
    <span class="header-date"><?= date('d/m/Y') ?></span>
  </div>

  <script>
    // Close user dropdown on outside click
    document.addEventListener('click', function(e) {
      const menu = document.getElementById('userMenu');
      if (menu && !menu.contains(e.target)) menu.classList.remove('open');
    });
  </script>
</header>

<!-- Page Content -->
<main class="page-content">
