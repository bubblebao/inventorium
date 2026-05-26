<?php
// ─── Authentication & Audit ───────────────────────────────────────────────
require_once __DIR__ . '/users.php';

// Log directory (declare ก่อน session เพราะ timeout handler ใช้)
$LOG_DIR = __DIR__ . '/../logs';
if (!is_dir($LOG_DIR)) @mkdir($LOG_DIR, 0775, true);
$LOG_FILE = $LOG_DIR . '/audit.log';

// Session config — 8 ชั่วโมง idle timeout
define('SESSION_TIMEOUT', 8 * 3600);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string)SESSION_TIMEOUT);
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Idle timeout — kick out ถ้าไม่มี activity > 8h
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        if (isset($_SESSION['user'])) {
            $u = $_SESSION['user']['username'] ?? '-';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
            @file_put_contents($LOG_FILE,
                sprintf("%s | %-12s | %-15s | %-10s | %s\n",
                    date('Y-m-d H:i:s'), $u, $ip, 'TIMEOUT', 'idle 8h'),
                FILE_APPEND | LOCK_EX);
        }
        $_SESSION = [];
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        header('Location: /login.php?reason=timeout');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// ─── Verify login ─────────────────────────────────────────────────────────
function verify_login(string $username, string $password): ?array {
    global $USERS;
    $username = strtolower(trim($username));
    if (!isset($USERS[$username])) return null;
    $stored = $USERS[$username]['pass'];

    $ok = false;
    $prefix2 = substr($stored, 0, 4);
    if ($prefix2 === '$2y$' || $prefix2 === '$2a$' || substr($stored, 0, 7) === '$argon2') {
        $ok = password_verify($password, $stored);
    } else {
        $ok = hash_equals($stored, $password);
    }
    return $ok ? array_merge($USERS[$username], ['username' => $username]) : null;
}

// ─── Audit log writer ─────────────────────────────────────────────────────
function audit_log(string $action, string $details = ''): void {
    global $LOG_FILE;
    $user = $_SESSION['user']['username'] ?? '-';
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '-';
    $line = sprintf("%s | %-12s | %-15s | %-10s | %s\n",
        date('Y-m-d H:i:s'), $user, $ip, $action, $details);
    @file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// ─── Session helpers ──────────────────────────────────────────────────────
function is_logged_in(): bool {
    return isset($_SESSION['user']['username']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (is_logged_in()) return;
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: /login.php');
    exit;
}

function require_role(string $role): void {
    require_login();
    if (($_SESSION['user']['role'] ?? '') !== $role) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:3rem;text-align:center">
            <h2>🚫 ไม่มีสิทธิ์เข้าถึง</h2>
            <p>หน้านี้สำหรับ ' . htmlspecialchars($role) . ' เท่านั้น</p>
            <a href="/index.php">← กลับหน้าหลัก</a></div>');
    }
}
