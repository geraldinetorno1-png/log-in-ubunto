<?php
declare(strict_types=1);
function formString(array $input, string $key, int $max = 256): string {
    $value = $input[$key] ?? '';
    return is_string($value) && strlen($value) <= $max ? $value : '';
}
function csrfToken(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function validCsrf(string $token): bool {
    return $token !== '' && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
function issueCaptcha(int $now): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $text = '';
    for ($i = 0; $i < 6; $i++) { $text .= $alphabet[random_int(0, strlen($alphabet) - 1)]; }
    $_SESSION['captcha'] = ['answer' => $text, 'issued' => $now];
    return $text;
}
function consumeCaptcha(string $answer, int $now): bool {
    $challenge = $_SESSION['captcha'] ?? null;
    unset($_SESSION['captcha']);
    return $challenge !== null && $now >= $challenge['issued'] && $now - $challenge['issued'] < 300
        && hash_equals($challenge['answer'], strtoupper(trim($answer)));
}
function sessionExpired(int $now): bool {
    return !isset($_SESSION['user'], $_SESSION['last_activity']) || $now - $_SESSION['last_activity'] >= 300;
}
function recordActivity(int $now): bool {
    if (sessionExpired($now)) { return false; }
    $_SESSION['last_activity'] = $now;
    return true;
}
function verifyCredentials(PDO $db, string $username, string $password): ?array {
    $query = $db->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
    $query->execute([$username]);
    $user = $query->fetch(PDO::FETCH_ASSOC);
    // Fixed dummy hash prevents skipping the expensive password check for unknown users.
    $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if (!password_verify($password, $hash) || !$user) { return null; }
    if (!isset(rolePages()[$user['role']])) { return null; }
    return ['id' => (int)$user['id'], 'username' => $user['username'], 'role' => $user['role']];
}
function rolePages(): array { return ['super_admin' => '/super-admin.php', 'admin' => '/admin.php', 'user' => '/user.php']; }
function dashboardPath(): string { return rolePages()[$_SESSION['user']['role'] ?? ''] ?? '/login.php'; }
function destroyLoginSession(): void {
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $cookie['path'],
        'secure' => $cookie['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}
function redirect(string $path): never { header('Location: ' . $path, true, 303); exit; }
function requireLogin(): void {
    if (sessionExpired(time())) {
        $expired = isset($_SESSION['user']);
        destroyLoginSession();
        redirect('/login.php' . ($expired ? '?expired=1' : ''));
    }
    if (!isset(rolePages()[$_SESSION['user']['role'] ?? ''])) {
        destroyLoginSession();
        redirect('/login.php');
    }
}
function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        exit('Access denied. This dashboard belongs to a different role.');
    }
}
function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
