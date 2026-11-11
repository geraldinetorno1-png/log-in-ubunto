<?php
// Ubuntu-only integration suite. Uses an isolated database, account and session directory.
// Run: sudo php tests/mysql_checks.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0) { exit("Run with sudo php on Ubuntu.\n"); }
$rootDir = dirname(__DIR__);
$name = 'auth_test_' . bin2hex(random_bytes(5));
$temp = sys_get_temp_dir() . '/' . $name;
mkdir($temp, 0700);
$database = new PDO('mysql:unix_socket=/var/run/mysqld/mysqld.sock;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$process = null;
$checks = 0;
function ensure(bool $condition, string $label): void {
    global $checks;
    if (!$condition) { throw new RuntimeException('FAIL: ' . $label); }
    $checks++;
}
try {
    $database->exec("CREATE DATABASE `$name`");
    $database->exec("USE `$name`");
    $database->exec(file_get_contents($rootDir . '/database/schema.sql'));
    $password = bin2hex(random_bytes(20));
    $database->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)')->execute(['testadmin', password_hash($password, PASSWORD_DEFAULT), 'admin']);
    $config = ['dsn' => "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=$name;charset=utf8mb4", 'username' => 'root', 'password' => ''];
    file_put_contents($temp . '/config.php', '<?php return ' . var_export($config, true) . ';');
    $process = proc_open([PHP_BINARY, '-d', 'session.save_path=' . $temp, '-S', '127.0.0.1:18766', '-t', $rootDir . '/public'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $temp . '/server.log', 'a'], 2 => ['file', $temp . '/server.log', 'a']], $pipes, null, ['AUTH_CONFIG' => $temp . '/config.php']);
    usleep(500000);
    $cookie = '';
    $request = function (string $path, ?array $post = null) use (&$cookie): array {
        $headers = "Cookie: $cookie\r\n";
        if ($post !== null) { $headers .= "Content-Type: application/x-www-form-urlencoded\r\n"; }
        $context = stream_context_create(['http' => ['method' => $post === null ? 'GET' : 'POST', 'header' => $headers, 'content' => $post === null ? '' : http_build_query($post), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
        $body = file_get_contents('http://127.0.0.1:18766' . $path, false, $context);
        foreach ($http_response_header as $header) {
            if (preg_match('/^Set-Cookie: (auth_session=[^;]*)/i', $header, $m)) { $cookie = $m[1]; }
        }
        preg_match('/\s(\d{3})\s/', $http_response_header[0], $m);
        return [(int)$m[1], $body, $http_response_header];
    };
    $fixture = function () use ($temp, &$cookie): array {
        // Inspect only this test's private session files, never deployed user sessions.
        $sid = substr($cookie, strlen('auth_session='));
        session_save_path($temp);
        session_name('auth_session');
        session_id($sid);
        session_start();
        $result = $_SESSION;
        session_write_close();
        return $result;
    };
    $request('/login.php');
    $request('/captcha.php');
    $state = $fixture();
    $form = ['username' => 'testadmin', 'password' => $password, 'csrf' => $state['csrf'], 'captcha' => 'wrong'];
    ensure($request('/login.php', $form)[0] === 200, 'wrong CAPTCHA fails');
    ensure($request('/dashboard.php')[0] === 303, 'wrong CAPTCHA cannot authenticate');
    $request('/login.php'); $request('/captcha.php'); $state = $fixture();
    $form['csrf'] = $state['csrf']; $form['captcha'] = $state['captcha']['answer']; $form['password'] = 'wrong';
    ensure(str_contains($request('/login.php', $form)[1], 'Incorrect username'), 'correct CAPTCHA with wrong password fails');
    $request('/captcha.php'); $state = $fixture();
    $form['captcha'] = $state['captcha']['answer']; $form['password'] = $password;
    $before = $cookie;
    ensure($request('/login.php', $form)[0] === 303, 'valid login redirects');
    ensure($cookie !== $before, 'login rotates session ID');
    ensure(str_contains($request('/admin.php')[1], 'Welcome, testadmin!'), 'dashboard displays authenticated user');
    $saved = $fixture()['last_activity'];
    $request('/session.php');
    ensure($fixture()['last_activity'] === $saved, 'polling does not extend session');
    $state = $fixture();
    ensure($request('/logout.php', ['csrf' => 'bad'])[0] === 403, 'invalid logout blocked');
    ensure($request('/logout.php', ['csrf' => $state['csrf']])[0] === 303, 'valid logout redirects');
    ensure($request('/dashboard.php')[0] === 303, 'logout prevents dashboard access');
    // Each login attempt counts, including invalid CAPTCHA; ten allowed per IP/window.
    $database->exec('DELETE FROM login_attempts');
    $request('/login.php'); $state = $fixture();
    for ($i = 0; $i < 10; $i++) { ensure($request('/login.php', ['csrf' => $state['csrf'], 'captcha' => 'bad'])[0] === 200, 'attempt before rate limit'); }
    ensure($request('/login.php', ['csrf' => $state['csrf'], 'captcha' => 'bad'])[0] === 429, 'eleventh attempt throttled');
    echo "PASS: $checks MySQL and HTTP integration checks\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    $database->exec("DROP DATABASE IF EXISTS `$name`");
    foreach (glob($temp . '/*') as $file) { unlink($file); }
    rmdir($temp);
}
