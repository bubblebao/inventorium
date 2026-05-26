# 📊 Inventorium — Carmen PO Reporting

Web-based reporting system for **Carmen ERP** Purchase Orders, designed for Pavilions Hotels accounting and procurement teams.

> **The Inventor's Hub** — ดูประวัติ PO, สินค้า, vendor แบบ read-only พร้อม audit trail

![PHP 7.4](https://img.shields.io/badge/PHP-7.4-blue) ![MySQL 5.5](https://img.shields.io/badge/MySQL-5.5_bridge-orange) ![Docker](https://img.shields.io/badge/Docker-Compose-2496ED) ![License](https://img.shields.io/badge/License-Internal-red)

---

## ✨ Features

- 🔐 **Authentication** — Multi-user login + role-based (admin/accounting/purchase/viewer)
- 📊 **Dashboard** — Monthly chart, due-soon alerts, top vendors
- 📋 **PO List** — Filter by date/vendor/PO#/location, sortable, paginated
- 📦 **Item Search** — Smart search across 21,000+ items, purchase history, price trends
- 🏢 **Vendor Management** — 1,797 vendors, PO history per vendor
- 🌍 **By Location Report** — Aggregated spending per branch/outlet
- 📜 **Audit Log** — Track every login + IP (admin only)
- 🌐 **i18n** — Thai / English switcher (cookie-based)
- 📥 **Export** — Excel + PDF for PO list and details

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  Browser (Chrome/Edge/Firefox)                              │
│  ↓ HTTPS (production) / HTTP (LAN)                          │
├─────────────────────────────────────────────────────────────┤
│  Synology NAS (192.168.1.200)                               │
│  ├── Web Station → reverse proxy port 6020 → :8080          │
│  └── Container Manager (Docker Compose)                     │
│      ├── inventorium-web-1     (PHP 7.4 + Apache)           │
│      └── inventorium-db-bridge-1 (MySQL 5.5)                │
│           ↑ sync nightly via init script                    │
├─────────────────────────────────────────────────────────────┤
│  Carmen ERP Server (192.168.1.12)                           │
│  └── MySQL 4.0.26 → backoffice database (READ-ONLY)         │
└─────────────────────────────────────────────────────────────┘
```

**Why MySQL 5.5 bridge?** PHP 7.4's `mysqlnd` refuses MySQL 4.0 connections (deprecated protocol). The bridge syncs Carmen → modern MySQL where PHP can read.

---

## 📁 Project Structure

```
carmen-report/
├── docker-compose.yml          # Production deployment
├── docker-compose.dev.yml      # Local development (bind-mount)
├── Dockerfile                  # PHP 7.4 + Apache + mysqli/gd/zip
├── composer.json               # phpspreadsheet, mpdf
├── db-init/
│   └── 01-sync.sh              # Carmen → bridge sync + indexes
└── src/
    ├── assets/
    │   └── inventorium-logo.png
    ├── config/
    │   ├── db.php              # DB connect + auth hook
    │   ├── auth.php            # Session + login + audit
    │   ├── users.php           # ⚠️ EDIT TO ADD/CHANGE USERS
    │   └── lang.php            # i18n TH/EN dictionary (~150 keys)
    ├── includes/
    │   ├── header.php          # Sidebar nav + top header + lang switcher
    │   └── footer.php          # Common JS (Chart.js, sidebar toggle)
    ├── logs/
    │   └── audit.log           # Login activity (auto-rotated)
    ├── index.php               # Dashboard
    ├── po_list.php             # PO list + filter
    ├── po_detail.php           # PO detail + items
    ├── item_history.php        # Item search + purchase history
    ├── vendor_list.php         # Vendor directory
    ├── vendor_detail.php       # Vendor info + PO history
    ├── dept_report.php         # Report by location
    ├── chart-data.php          # JSON API for charts
    ├── export.php              # Excel/PDF export
    ├── admin_log.php           # Audit log viewer (admin)
    ├── login.php
    ├── logout.php
    └── status.php              # Health check
```

---

## 🚀 Local Development

### Prerequisites
- **Docker Desktop** (Windows/Mac)
- Network access to Carmen ERP server (`192.168.1.12`)
- ~2 GB free disk

### Quick Start

```powershell
cd D:\Claude\code\inventorium\carmen-report
docker compose -f docker-compose.dev.yml up -d --build
```

⏰ Wait ~5–10 minutes for first sync (~820k rows + 3 indexes)

Watch progress:
```powershell
docker compose -f docker-compose.dev.yml logs -f db-bridge
```

When you see `[SYNC] เสร็จสมบูรณ์ ✓` → open:
- **App:** http://localhost:8080
- **Status:** http://localhost:8080/status.php
- **HeidiSQL:** `localhost:3306`, user=`root`, pass=`inv123`, db=`backoffice`

### Live Edit Workflow
1. Edit any file in `src/*.php` (VS Code)
2. Save
3. Refresh browser → changes appear immediately (bind mount, no rebuild)

### Stop
```powershell
docker compose -f docker-compose.dev.yml stop                # keep data
docker compose -f docker-compose.dev.yml down -v             # delete data
```

---

## 🚢 Production Deployment (Synology NAS)

### 1. Pre-deploy checklist (on local)

```
[ ] Hash all passwords in src/config/users.php (see below)
[ ] Verify logo at src/assets/inventorium-logo.png
[ ] Change MYSQL_ROOT_PASSWORD in docker-compose.yml (current: inv123)
[ ] Test full flow in dev (login → all pages → logout)
[ ] Commit + push to Git
```

### Generate bcrypt password hash
```powershell
docker compose -f docker-compose.dev.yml exec web php -r "echo password_hash('your_password', PASSWORD_DEFAULT), PHP_EOL;"
```
Copy the `$2y$...` output → replace `pass` value in `users.php`.

### 2. Copy to NAS

Upload entire `carmen-report/` folder to NAS path:
```
/volume1/docker/inventorium/
```

(Use File Station, rsync, or SMB share)

### 3. Container Manager

1. **Project** → **Create**
2. Project name: `inventorium`
3. Path: `/volume1/docker/inventorium`
4. Source: **Use existing docker-compose.yml**
5. Click **Next → Build → Done**

⏰ Wait ~10 minutes for sync to complete

### 4. Web Station Portal (optional, recommended)

For pretty URL `http://nas-ip:6020/`:
- **Web Station → Web Service Portal → Create**
- Type: **Container**
- Container: `inventorium-web-1`
- Port: `6020` (or any)

### 5. Verify

Open: `http://192.168.1.200:6020/status.php`

Expected:
```
=== Inventorium Status ===
Table invpo0  : 203,177 rows ✓
Table invpo1  : 618,264 rows ✓
Table gblvend : 1,797   rows ✓
Table gblprod : 21,065  rows ✓
Status: ✅ READY
```

### 6. Test Login

Default URL: `http://192.168.1.200:6020/`

Use credentials from `users.php`.

---

## 👥 User Management

### Add / Modify Users

Edit `src/config/users.php`:

```php
$USERS = [
    'username' => [
        'pass' => '$2y$10$...',     // bcrypt hash (production)
        'name' => 'Display Name',
        'role' => 'admin',          // admin | accounting | purchase | viewer
    ],
    // add more users...
];
```

**No restart needed** — changes take effect on next page load.

### Role Capabilities

| Role | Dashboard | PO/Vendor/Item | Audit Log |
|---|---|---|---|
| `admin` | ✅ | ✅ | ✅ |
| `accounting` | ✅ | ✅ | ❌ |
| `purchase` | ✅ | ✅ | ❌ |
| `viewer` | ✅ | ✅ | ❌ |

---

## 🔄 Data Sync

Sync runs **once** on first MySQL initialization (volume creation).

### Force re-sync
```bash
# Container Manager → Stop project
ssh admin@192.168.1.200
sudo docker volume rm inventorium_carmen_cache
# Container Manager → Build → Start
```

⚠️ Re-sync downloads ~830k rows from Carmen — takes 10–15 minutes.

### Auto re-sync (future v1.1)
Currently manual. Roadmap includes cron-based incremental sync.

---

## 🛡️ Security

### Implemented
- ✅ Session-based auth (HTTPOnly + SameSite cookies)
- ✅ `session_regenerate_id()` after login
- ✅ SQL injection prevention (`db_escape` + `(int)` casts)
- ✅ XSS prevention (`htmlspecialchars` everywhere)
- ✅ Role-based access control
- ✅ Audit logging (LOGIN/LOGIN_FAIL/LOGOUT + IP)
- ✅ Read-only on Carmen (no write operations)
- ✅ Password verification supports plain + bcrypt hybrid

### Recommended (Production)
- 🔒 **HTTPS** via Synology reverse proxy + Let's Encrypt
- 🔒 **bcrypt** passwords (not plain text in `users.php`)
- 🔒 **Firewall** restrict to office VLAN
- 🔒 **Backup** `/volume1/@docker/volumes/inventorium_*` weekly

---

## 🌐 i18n (TH / EN)

Switch language: click pill button top-right → cookie persists 1 year.

Add new translations: edit `src/config/lang.php`:
```php
'my_key' => ['th' => 'ไทย', 'en' => 'English'],
```

Use in PHP: `<?= t('my_key') ?>`

---

## 🐛 Troubleshooting

### "Connection refused" on login
→ db-bridge container not ready. Wait or check `docker logs inventorium-db-bridge-1`

### Charts not loading
→ Hard refresh browser (Ctrl+F5). Chart.js may be cached.

### Thai characters show as "???"
→ Verify `SET NAMES latin1` in `config/db.php` (NOT `tis620` for backoffice DB)

### "Table doesn't exist"
→ Sync didn't run. Stop project → remove volume → rebuild (see "Force re-sync").

### Login failed with correct password
→ Check `src/logs/audit.log` for `LOGIN_FAIL` entries. Verify `users.php` not corrupted.

---

## 📊 Tech Stack

| Layer | Choice | Reason |
|---|---|---|
| Language | PHP 7.4 | Carmen team familiarity, mature |
| Web Server | Apache 2.4 | Bundled with PHP image |
| Database | MySQL 5.5 (bridge) | Compatible with MySQL 4.0 source |
| Frontend | Bootstrap 5.3 + Chart.js 4.4 | No build step, CDN-loaded |
| Containerization | Docker Compose | Synology-friendly |
| Fonts | Inter (Google Fonts) | Modern, readable for numeric data |
| Icons | Bootstrap Icons 1.11 | Consistent with Bootstrap |
| Excel Export | phpoffice/phpspreadsheet | Battle-tested |
| PDF Export | mpdf/mpdf | Unicode + Thai font support |

---

## 🛣️ Roadmap (v1.1+)

- [ ] Cron-based incremental sync (nightly)
- [ ] Rate limiting on login (anti-brute-force)
- [ ] Email alert on suspicious activity
- [ ] Auto-rotate `audit.log` (90 days)
- [ ] Item history Excel export
- [ ] Vendor scorecard (on-time delivery, price stability)
- [ ] Mobile-responsive polish
- [ ] CSRF tokens on POST forms

---

## 📝 License

Internal use only — Pavilions Hotels. Not for distribution.

---

## 🙋 Support

- Code questions: contact IT team
- Carmen ERP data: contact Carmen administrator
- Add new user: edit `src/config/users.php` then commit

---

*Built with ❤️ by the AI-Dev Dream Team* 💎
