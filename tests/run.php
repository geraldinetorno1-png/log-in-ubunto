<?php
declare(strict_types=1);
$file = __DIR__ . '/../src/auth.php';
if (!is_file($file)) { fwrite(STDERR, "FAIL: authentication rules are not implemented\n"); exit(1); }
require $file;
$count = 0;
function check(bool $ok, string $label): void {
    global $count;
    if (!$ok) { throw new RuntimeException('FAIL: ' . $label); }
    $count++;
}
$_SESSION = [];
check(!consumeCaptcha('ABC123', 100), 'missing CAPTCHA rejected');
$answer = issueCaptcha(100);
check(!consumeCaptcha('wrong', 101), 'wrong CAPTCHA rejected');
check(!consumeCaptcha($answer, 102), 'failed CAPTCHA consumed');
$answer = issueCaptcha(100);
check(consumeCaptcha(strtolower($answer), 399), 'correct CAPTCHA accepted before deadline');
check(!consumeCaptcha($answer, 399), 'replayed CAPTCHA rejected');
$answer = issueCaptcha(100);
check(!consumeCaptcha($answer, 400), 'CAPTCHA expires at deadline');
$old = issueCaptcha(100);
do { $new = issueCaptcha(101); } while ($old === $new);
check(!consumeCaptcha($old, 102), 'refresh invalidates old challenge');
check(!validCsrf(''), 'missing CSRF rejected');
$token = csrfToken();
check(validCsrf($token), 'valid CSRF accepted');
check(!validCsrf(str_repeat('x', 64)), 'wrong CSRF rejected');
check(formString(['field' => ['x']], 'field') === '', 'array form input rejected');
check(formString(['field' => str_repeat('a', 300)], 'field', 100) === '', 'oversized form input rejected');
$_SESSION = [];
check(sessionExpired(100), 'anonymous session is unauthorized');
$_SESSION = ['user' => ['id' => 1, 'username' => 'admin'], 'last_activity' => 100];
check(!sessionExpired(399), 'active at 299 seconds');
check(sessionExpired(400), 'expired at 300 seconds');
check($_SESSION['last_activity'] === 100, 'status checks do not renew session');
check(!recordActivity(400), 'expired session cannot be revived');
$_SESSION['last_activity'] = 100;
check(recordActivity(200), 'real activity renews live session');
check($_SESSION['last_activity'] === 200, 'activity timestamp updated');
// Use a real isolated SQLite database locally; production uses MySQL.
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT UNIQUE, password_hash TEXT, role TEXT)');
$pdo->prepare('INSERT INTO users VALUES (1, ?, ?, ?)')->execute(['admin', password_hash('Test-only-password-47', PASSWORD_DEFAULT), 'admin']);
check(verifyCredentials($pdo, 'admin', 'Test-only-password-47')['username'] === 'admin', 'correct credentials');
check(verifyCredentials($pdo, 'admin', 'wrong') === null, 'incorrect password');
check(verifyCredentials($pdo, 'missing', 'wrong') === null, 'unknown user');
check(verifyCredentials($pdo, "admin' OR 1=1 --", 'wrong') === null, 'SQL injection rejected');
check((verifyCredentials($pdo, 'admin', 'Test-only-password-47')['role'] ?? null) === 'admin', 'role comes from stored account');
foreach (['super_admin', 'user'] as $role) {
    $pdo->prepare('UPDATE users SET role = ? WHERE id = 1')->execute([$role]);
    check(verifyCredentials($pdo, 'admin', 'Test-only-password-47')['role'] === $role, 'preserves ' . $role . ' role');
}
$pdo->exec("UPDATE users SET role = 'unexpected'");
check(verifyCredentials($pdo, 'admin', 'Test-only-password-47') === null, 'unknown role fails closed');
echo "PASS: $count authentication checks\n";
