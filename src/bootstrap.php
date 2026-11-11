<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', '1800');
session_name('auth_session');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
set_exception_handler(function (Throwable $error): void {
    error_log((string)$error);
    http_response_code(503);
    echo 'The service is temporarily unavailable. Please try again shortly.';
});
function db(): PDO {
    static $db;
    if (!$db) {
        $config = require (getenv('AUTH_CONFIG') ?: '/etc/auth-demo/config.php');
        $db = new PDO($config['dsn'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    return $db;
}
function allowAttempt(PDO $db, string $ip): bool {
    $key = hash('sha256', $ip);
    $db->beginTransaction();
    try {
        $db->prepare('INSERT IGNORE INTO login_attempts (address_hash, attempts, window_start) VALUES (?, 0, UNIX_TIMESTAMP())')->execute([$key]);
        $q = $db->prepare('SELECT attempts, window_start FROM login_attempts WHERE address_hash = ? FOR UPDATE');
        $q->execute([$key]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        $now = time();
        $attempts = $now - (int)$row['window_start'] >= 900 ? 0 : (int)$row['attempts'];
        $start = $attempts === 0 ? $now : (int)$row['window_start'];
        if ($attempts >= 10) { $db->commit(); return false; }
        $db->prepare('UPDATE login_attempts SET attempts = ?, window_start = ? WHERE address_hash = ?')->execute([$attempts + 1, $start, $key]);
        $db->commit();
        if (random_int(1, 100) === 1) { $db->exec('DELETE FROM login_attempts WHERE window_start < UNIX_TIMESTAMP() - 86400'); }
        return true;
    } catch (Throwable $e) { if ($db->inTransaction()) { $db->rollBack(); } throw $e; }
}
