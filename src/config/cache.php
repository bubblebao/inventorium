<?php
// ─── Simple File Cache ────────────────────────────────────────────────────
// Usage:
//   $data = cache_remember('key', 3600, function() use ($conn) {
//       return mysqli_fetch_all(mysqli_query($conn, "SELECT ..."), MYSQLI_ASSOC);
//   });

$CACHE_DIR = __DIR__ . '/../logs/cache';
if (!is_dir($CACHE_DIR)) @mkdir($CACHE_DIR, 0775, true);

function cache_path(string $key): string {
    global $CACHE_DIR;
    return $CACHE_DIR . '/' . preg_replace('/[^a-zA-Z0-9_]/', '_', $key) . '.cache';
}

function cache_get(string $key, int $ttl_seconds) {
    $f = cache_path($key);
    if (!is_file($f)) return null;
    if ((time() - filemtime($f)) > $ttl_seconds) return null;
    $raw = @file_get_contents($f);
    if ($raw === false) return null;
    $data = @unserialize($raw);
    return $data === false ? null : $data;
}

function cache_set(string $key, $value): bool {
    $f = cache_path($key);
    return @file_put_contents($f, serialize($value), LOCK_EX) !== false;
}

function cache_remember(string $key, int $ttl_seconds, callable $loader) {
    $cached = cache_get($key, $ttl_seconds);
    if ($cached !== null) return $cached;
    $fresh = $loader();
    cache_set($key, $fresh);
    return $fresh;
}

function cache_forget(string $key): void {
    $f = cache_path($key);
    if (is_file($f)) @unlink($f);
}
