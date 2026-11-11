<?php
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0) { exit("Run with sudo php.\n"); }
$db = new PDO('mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=ubuntu_auth;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) { exit("An account already exists; no changes made.\n"); }
echo "Create the website account (username: admin).\nUse at least 12 characters. This is separate from your Ubuntu password.\n";
function hiddenPassword(string $prompt): string {
    echo $prompt;
    $state = trim(shell_exec('stty -g') ?? '');
    if ($state === '') { throw new RuntimeException('Use an interactive terminal.'); }
    system('stty -echo');
    try { return rtrim(fgets(STDIN) ?: '', "\r\n"); }
    finally { system('stty ' . escapeshellarg($state)); echo "\n"; }
}
$password = hiddenPassword('New website password: ');
$confirmation = hiddenPassword('Repeat website password: ');
if (strlen($password) < 12 || strlen($password) > 72 || !hash_equals($password, $confirmation)) { exit("Passwords must match and contain 12–72 characters. Run this command again.\n"); }
$db->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)')->execute(['admin', password_hash($password, PASSWORD_DEFAULT), 'admin']);
echo "Website account created. Username: admin\n";
