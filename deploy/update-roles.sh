#!/bin/sh
set -eu
test "$(id -u)" = 0 || { echo 'Run with sudo.'; exit 1; }
cd "$(dirname "$0")/.."
test -d /var/www/auth-demo/public
backup="/var/backups/auth-demo-before-roles-$(date +%Y%m%d%H%M%S).tar.gz"
tar -czf "$backup" -C /var/www auth-demo
chmod 600 "$backup"
php database/migrate-roles.php
cp -R public src database bin deploy tests /var/www/auth-demo/
cp README.md /var/www/auth-demo/
chown -R root:root /var/www/auth-demo
find /var/www/auth-demo -type d -exec chmod 755 {} \;
find /var/www/auth-demo -type f -exec chmod 644 {} \;
nginx -t
echo "Role dashboards installed. Original files backed up to $backup"
echo 'Existing admin account preserved. Create superadmin and user with bin/create-role-account.php.'
