# Ubuntu Access — PHP + MySQL authentication

A small login system with an image CAPTCHA, a protected welcome dashboard, logout, and a five-minute inactivity timeout. Includes separate Super Admin, Admin and User dashboards; no registration or CRUD.

## Your installed application

- On this Mac: **https://127.0.0.1:8443/login.php**
- Inside Ubuntu: **https://10.0.2.15/login.php**
- HTTP on the Mac, http://127.0.0.1:8080, redirects to HTTPS.
- Ubuntu application: `/var/www/auth-demo`
- Nginx public document root: `/var/www/auth-demo/public`
- Database: `ubuntu_auth`; service credentials: `/etc/auth-demo/config.php` (root/www-data only).
- Website username: `admin`, after you complete account creation.

The VM must be running. VirtualBox NAT forwards Mac loopback port 8443 to Ubuntu port 443. Other computers cannot connect through this loopback-only forwarding rule. The guest IP is on VirtualBox's private network.

The local demo uses a self-signed certificate valid for localhost, 127.0.0.1 and 10.0.2.15. Browsers will show a certificate warning until you explicitly trust the certificate. Browser trust has **not** been changed automatically. For an internet deployment, replace it with a certificate from a trusted authority and configure the actual server name/IP. Never reuse this demo configuration as an unattended public deployment.

## Create your website account

In Ubuntu:

```sh
sudo php /var/www/auth-demo/bin/create-admin.php
```

Type a new password containing 12–72 characters, press Enter, then type it again and press Enter. Nothing appears while typing. This is separate from the Ubuntu login password. The script stores only a password hash and refuses to overwrite an existing account.

## What is implemented

- PDO prepared statements and PHP `password_hash` / `password_verify`.
- Random six-character GD image CAPTCHA, case-insensitive, single-use, expiring after five minutes. Refresh invalidates the previous challenge. Its answer is never put in the form or JavaScript.
- CSRF protection for login, logout and activity requests.
- Per-IP login throttling: ten attempts per fifteen-minute window, shared across browser sessions and including incorrect CAPTCHA submissions.
- Session ID rotation on successful login; secure, HttpOnly, SameSite=Lax cookies under HTTPS.
- Server authorization and 300-second inactivity checks on every protected endpoint.
- Browser activity reporting and idle redirect; status polling does not extend the session.
- POST logout destroys session storage and expires the cookie.
- No-store headers and browser-history revalidation to protect the dashboard after logout.
- A database service account that can read users but cannot create or change them.

The custom image CAPTCHA is suitable for this classroom demonstration; it is not a substitute for a maintained anti-abuse service on a public high-risk site. It has no audio alternative.

## Verification recorded on 10 November 2026

- 23 authentication rule checks passed on PHP 8.2 on this Mac.
- 16 isolated HTTP checks passed: unauthorized access, malformed forms, escaped username, logout CSRF, session invalidation, expiry and cache headers.
- 21 isolated MySQL/HTTP integration checks passed on Ubuntu PHP 8.5: correct/incorrect login, CAPTCHA rejection, session rotation, dashboard, logout, status polling and throttling. Test database and generated test credentials were removed afterwards.
- All 12 PHP files passed syntax checks.
- Nginx configuration validated and reloaded successfully.
- Deployed HTTPS responses verified from the Mac: login 200; unauthorized dashboard 303; Secure/HttpOnly/SameSite cookie; no-store headers.
- Independent review found no critical or important security defects.

The original admin account was created successfully. The user reported successful login, incorrect-password rejection, CAPTCHA rejection and the five-minute timeout. Logout/Back was exercised by the user but its outcome was not explicitly confirmed. Role dashboard screenshots and new role-account setup are pending.

Minor review notes: activity reporting can lag physical interaction by up to ten seconds. A request crossing the expiry second between two server clock checks can briefly return active with zero seconds remaining; it does not revive the expired session.

## Run tests

Local PHP with PDO SQLite and Python 3:

```sh
php tests/run.php
python3 tests/web_checks.py
```

Set `PHP_BIN` for the Python test when PHP is not on PATH. The local test uses an in-memory SQLite table; production uses MySQL. On Ubuntu, run the separate MySQL integration suite:

```sh
sudo php tests/mysql_checks.php
```

It creates a randomly named temporary database, starts a local PHP test server on port 18766, and removes the test resources in a finally block. It never reads production sessions or uses the real admin password. The HTTP suite uses port 18765.

## Install on another Ubuntu server

Install Nginx, MySQL, PHP-FPM, PHP-MySQL, PHP-GD, and fonts-dejavu-core using Ubuntu packages. Verify `/run/php/php-fpm.sock`. Review the IP addresses in `deploy/install.sh`, and adjust `deploy/nginx.conf` to the server's addresses. The included loopback redirect mapping assumes VirtualBox forwarding on port 8443.

Run `sudo sh deploy/install.sh` from an extracted project, then run the account-creation command above. The installer backs up the existing default Nginx site to `/etc/nginx/sites-available/default.before-auth-app`, and refuses to overwrite an existing `/var/www/auth-demo` installation. It assumes MySQL root socket authentication and no existing `auth_demo` database account.

For maintenance, back up the database and `/etc/auth-demo`, keep Ubuntu/PHP/MySQL patched, and review server logs without exposing them on the web. The private TLS key and database password must stay outside the public directory.

References: [PHP session cookie options](https://www.php.net/manual/en/function.session-set-cookie-params.php), [Nginx HTTPS configuration](https://nginx.org/en/docs/http/configuring_https_servers.html).

## November 11 submission update

Group: Trisha Marie Estrecho, Precious Marquez, Geraldine Agang.

The teacher’s newer submission instructions require screenshots of Login, Super Admin, Admin and User dashboards. This supersedes the original single-dashboard scope. Accounts obtain their role from MySQL, and server-side guards reject cross-role access. Each role has a simple dashboard; no management features or analytics are implied.

After upgrading an existing installation with `sudo sh deploy/update-roles.sh`, create the two additional accounts privately:

```sh
sudo php /var/www/auth-demo/bin/create-role-account.php superadmin
sudo php /var/www/auth-demo/bin/create-role-account.php user
```

Set a different 12–72 character password for each. Existing `admin` keeps its password. Create `admin` first on a fresh installation. Never include passwords, TLS private keys, session files, or `/etc/auth-demo/config.php` in GitHub or email.

Dashboard paths: `/super-admin.php`, `/admin.php`, `/user.php`. `/dashboard.php` routes to the signed-in account’s page. Existing sessions without a role must sign in again.

Additional validation: 27 local authentication checks and the full three-role HTTP authorization matrix pass. Run `sudo php tests/migration_checks.php` on Ubuntu to check resumable migration and password preservation.
