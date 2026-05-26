<?php
require_once __DIR__ . '/config/auth.php';

$error = '';
$info  = '';

if (($_GET['reason'] ?? '') === 'timeout') {
    $info = 'Session หมดอายุ (ไม่มี activity 8 ชั่วโมง) — กรุณา login ใหม่';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    $user = verify_login($u, $p);

    if ($user) {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        audit_log('LOGIN', "as {$user['role']}");

        $redirect = $_SESSION['redirect_to'] ?? '/index.php';
        unset($_SESSION['redirect_to']);
        header('Location: ' . $redirect);
        exit;
    } else {
        audit_log('LOGIN_FAIL', "user=$u");
        $error = 'Username หรือ Password ไม่ถูกต้อง';
    }
}

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Login — Inventorium</title>
<link rel="icon" type="image/png" href="/assets/inventorium-logo.png">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{--primary:#1e3a5f;--accent:#0ea5e9;--danger:#ef4444;--surface:#fff;--text:#1e293b;--muted:#64748b;--border:#e2e8f0}
*{box-sizing:border-box}
body{margin:0;font-family:'Inter',system-ui,sans-serif;background:linear-gradient(135deg,#1e3a5f 0%,#0ea5e9 100%);
  min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login-card{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.25);
  padding:40px 36px;width:100%;max-width:380px;animation:slide-up .4s ease-out}
@keyframes slide-up{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.logo-wrap{text-align:center;margin-bottom:24px}
.logo-wrap img{width:80px;height:80px;border-radius:18px;box-shadow:0 4px 16px rgba(14,165,233,.3)}
.brand{font-size:24px;font-weight:800;color:var(--primary);margin-top:14px;letter-spacing:-.5px}
.brand .accent{color:var(--accent)}
.tagline{font-size:12px;color:var(--muted);margin-top:4px;letter-spacing:.5px;text-transform:uppercase}
.form-group{margin-bottom:16px}
.form-label{font-size:12px;font-weight:600;color:var(--text);margin-bottom:6px;display:block}
.input-wrap{position:relative}
.input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:16px}
.form-control{width:100%;padding:11px 14px 11px 42px;border:1.5px solid var(--border);
  border-radius:10px;font-size:14px;background:#f8fafc;transition:all .2s;font-family:inherit}
.form-control:focus{outline:none;border-color:var(--accent);background:#fff;box-shadow:0 0 0 3px rgba(14,165,233,.12)}
.btn-login{width:100%;padding:12px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--primary) 0%,var(--accent) 100%);
  color:#fff;font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;margin-top:8px;letter-spacing:.3px}
.btn-login:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(14,165,233,.4)}
.btn-login:active{transform:translateY(0)}
.error-box{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;
  padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.footer-note{text-align:center;font-size:11px;color:var(--muted);margin-top:20px;line-height:1.5}
</style>
</head>
<body>
<div class="login-card">
  <div class="logo-wrap">
    <img src="/assets/inventorium-logo.png" alt="Inventorium" onerror="this.style.display='none'">
    <div class="brand"><span>Invent</span><span class="accent">orium</span></div>
    <div class="tagline">The Inventor's Hub</div>
  </div>

  <?php if ($error): ?>
    <div class="error-box">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($info): ?>
    <div class="error-box" style="background:#fffbeb;border-color:#fcd34d;color:#b45309">
      <i class="bi bi-clock-history"></i>
      <span><?= htmlspecialchars($info) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="form-group">
      <label class="form-label">Username</label>
      <div class="input-wrap">
        <i class="bi bi-person"></i>
        <input type="text" name="username" class="form-control" required autofocus
               placeholder="ชื่อผู้ใช้" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <div class="input-wrap">
        <i class="bi bi-lock"></i>
        <input type="password" name="password" class="form-control" required placeholder="••••••••">
      </div>
    </div>
    <button type="submit" class="btn-login">
      <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
    </button>
  </form>

  <div class="footer-note">
    ระบบสำหรับ Pavilions Hotels เท่านั้น<br>
    ต้องการสิทธิ์เพิ่ม ติดต่อ IT
  </div>
</div>
</body>
</html>
