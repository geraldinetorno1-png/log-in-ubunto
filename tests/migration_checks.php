<?php
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0) { exit("Run with sudo php on Ubuntu.\n"); }
$db = new PDO('mysql:unix_socket=/var/run/mysqld/mysqld.sock;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$name = 'auth_migration_' . bin2hex(random_bytes(5));
$db->exec("CREATE DATABASE `$name`");
try {
    $db->exec("USE `$name`");
    $db->exec('CREATE TABLE users (id INT PRIMARY KEY, username VARCHAR(64), password_hash VARCHAR(255))');
    $db->exec("INSERT INTO users VALUES (1, 'admin', 'unchanged-hash')");
    // Model an interruption after adding the role column, before backfilling.
    $db->exec("ALTER TABLE users ADD COLUMN role ENUM('super_admin','admin','user') NULL DEFAULT NULL");
    $file = __DIR__ . '/../src/role-migration.php';
    if (!is_file($file)) { throw new RuntimeException('FAIL: resumable role migration is missing'); }
    require $file;
    migrateRoles($db);
    $row = $db->query('SELECT * FROM users')->fetch(PDO::FETCH_ASSOC);
    if ($row['role'] !== 'admin' || $row['password_hash'] !== 'unchanged-hash') { throw new RuntimeException('FAIL: migration must recover and preserve password'); }
    $db->exec("UPDATE users SET role='user'");
    migrateRoles($db);
    if ($db->query('SELECT role FROM users')->fetchColumn() !== 'user') { throw new RuntimeException('FAIL: rerun overwrites intentional role'); }
    $db->exec("INSERT INTO users (id,username,password_hash) VALUES (2,'later-user','hash')");
    if ($db->query('SELECT role FROM users WHERE id=2')->fetchColumn() !== 'user') { throw new RuntimeException('FAIL: default must be user'); }
    echo "PASS: interrupted migration recovery, password preservation, idempotency and safe default\n";
} finally { $db->exec("DROP DATABASE `$name`"); }
