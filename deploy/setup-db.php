<?php
// Run once as root on Ubuntu. Generated service credentials never leave this server.
if (PHP_SAPI !== 'cli' || posix_geteuid() !== 0) { exit("Run with sudo php.\n"); }
$directory = '/etc/auth-demo';
if (is_file($directory . '/config.php')) { exit("Database already configured; keeping existing credentials.\n"); }
$root = new PDO('mysql:unix_socket=/var/run/mysqld/mysqld.sock;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$root->exec('CREATE DATABASE IF NOT EXISTS ubuntu_auth CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
$root->exec('USE ubuntu_auth');
$root->exec(file_get_contents(__DIR__ . '/../database/schema.sql'));
$password = bin2hex(random_bytes(32));
$root->exec("CREATE USER 'auth_demo'@'localhost' IDENTIFIED BY " . $root->quote($password));
$root->exec("GRANT SELECT ON ubuntu_auth.users TO 'auth_demo'@'localhost'");
$root->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON ubuntu_auth.login_attempts TO 'auth_demo'@'localhost'");
if (!is_dir($directory)) { mkdir($directory, 0750, true); }
chgrp($directory, 'www-data');
$config = ['dsn' => 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=ubuntu_auth;charset=utf8mb4', 'username' => 'auth_demo', 'password' => $password];
file_put_contents($directory . '/config.php', "<?php\nreturn " . var_export($config, true) . ";\n");
chmod($directory . '/config.php', 0640);
chgrp($directory . '/config.php', 'www-data');
echo "Database and restricted service account ready.\n";
