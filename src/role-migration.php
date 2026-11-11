<?php
declare(strict_types=1);
function migrateRoles(PDO $db): void {
    $exists = $db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role'")->fetchColumn();
    if (!$exists) {
        // NULL marks unfinished rows, allowing recovery after any statement.
        $db->exec("ALTER TABLE users ADD COLUMN role ENUM('super_admin', 'admin', 'user') NULL DEFAULT NULL");
    }
    $db->exec("UPDATE users SET role = CASE WHEN username = 'admin' THEN 'admin' ELSE 'user' END WHERE role IS NULL");
    $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'user') NOT NULL DEFAULT 'user'");
}
