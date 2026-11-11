<?php
// Upgrade an existing database without changing any existing passwords.
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0) { exit("Run with sudo php.\n"); }
$db = new PDO('mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=ubuntu_auth;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
require __DIR__ . '/../src/role-migration.php';
migrateRoles($db);
echo "Role migration complete; existing passwords preserved.\n";
