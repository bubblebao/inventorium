<?php
// Charset: data ใน backoffice เป็น TIS-620 ดิบ แต่ column = latin1
// ดังนั้นต้อง SET NAMES latin1 (กัน MySQL แปลง) แล้วให้ PHP iconv แปลงเอง
define('DB_CHARSET', 'latin1');

// i18n + Auth + Cache — โหลดทุก request
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cache.php';

// บังคับ login ทุกหน้า (ยกเว้น login.php, logout.php จะ require เอง)
require_login();

$conn = null;
$db_host = getenv('DB_HOST') ?: '192.168.1.12';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'Carmen';
$db_port = (int)(getenv('DB_PORT') ?: 3306);

// Retry up to 10x with 3s gap — allows db-bridge time to initialize
for ($i = 0; $i < 10; $i++) {
    $conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
    if ($conn) break;
    sleep(3);
}

if (!$conn) {
    http_response_code(500);
    die('<div style="font-family:sans-serif;padding:2rem;color:#dc2626">DB Connection Error: ' . htmlspecialchars(mysqli_connect_error()) . '</div>');
}

mysqli_query($conn, 'SET NAMES ' . DB_CHARSET);

// Convert TIS-620 bytes (stored as latin1) → UTF-8 สำหรับ browser
function db_str(?string $s): string
{
    if (!isset($s) || $s === '') return '';
    return iconv('TIS-620', 'UTF-8//IGNORE', $s) ?: $s;
}

// Escape all string user inputs — replaces prepared statements for MySQL 4.0
function db_escape(string $s): string
{
    global $conn;
    return mysqli_real_escape_string($conn, $s);
}

// UTF-8 (จาก browser) → TIS-620 bytes (เก็บใน DB) สำหรับใช้ใน LIKE/WHERE
// คืน string ที่ escape แล้ว พร้อมใส่ LIKE '%...%'
function db_search(string $s): string
{
    global $conn;
    if ($s === '') return '';
    // ถ้ามีตัวอักษรไทย → แปลง UTF-8 → TIS-620
    if (preg_match('/[\x{0E00}-\x{0E7F}]/u', $s)) {
        $converted = @iconv('UTF-8', 'TIS-620//IGNORE', $s);
        if ($converted !== false && $converted !== '') $s = $converted;
    }
    return mysqli_real_escape_string($conn, $s);
}

function fmt_number(?string $n): string
{
    return number_format((float)($n ?? 0), 2);
}

function fmt_date(?string $d): string
{
    if (!$d || $d === '0000-00-00') return '-';
    $parts = explode('-', $d);
    if (count($parts) !== 3) return htmlspecialchars($d); // malformed — return as-is safely
    [$y, $m, $day] = $parts;
    return sprintf('%02d/%02d/%04d', (int)$day, (int)$m, (int)$y);
}
