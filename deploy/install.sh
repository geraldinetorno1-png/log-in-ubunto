#!/bin/sh
set -eu
test "$(id -u)" = 0 || { echo 'Run with sudo.'; exit 1; }
cd "$(dirname "$0")/.."
test -S /run/php/php-fpm.sock
test ! -e /var/www/auth-demo || { echo 'Installation already exists. Review before updating.'; exit 1; }
install -d -m 755 /var/www/auth-demo
cp -R public src database bin deploy tests /var/www/auth-demo/
chown -R root:root /var/www/auth-demo
find /var/www/auth-demo -type d -exec chmod 755 {} \;
find /var/www/auth-demo -type f -exec chmod 644 {} \;
php /var/www/auth-demo/deploy/setup-db.php
umask 077
openssl req -x509 -newkey rsa:3072 -sha256 -nodes -days 365 -keyout /etc/auth-demo/server.key -out /etc/auth-demo/server.crt -subj '/CN=Ubuntu Access Demo' -addext 'subjectAltName=IP:10.0.2.15,IP:127.0.0.1,DNS:localhost'
chmod 644 /etc/auth-demo/server.crt
cp -p /etc/nginx/sites-available/default /etc/nginx/sites-available/default.before-auth-app
cp deploy/nginx.conf /etc/nginx/sites-available/default
if nginx -t; then systemctl reload nginx; else cp /etc/nginx/sites-available/default.before-auth-app /etc/nginx/sites-available/default; exit 1; fi
echo 'Application installed. Create your account with:'
echo 'sudo php /var/www/auth-demo/bin/create-admin.php'
