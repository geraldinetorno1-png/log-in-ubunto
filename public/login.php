<?php
require __DIR__ . '/../src/bootstrap.php';
if (!sessionExpired(time())) { redirect('/dashboard.php'); }
if (isset($_SESSION['user'])) { $_SESSION = []; session_regenerate_id(true); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf(formString($_POST, 'csrf'))) {
        unset($_SESSION['captcha']);
        $error = 'Your form has expired. Please try again.';
    } elseif (!allowAttempt(db(), $_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
        unset($_SESSION['captcha']);
        $error = 'Too many attempts. Please wait 15 minutes before trying again.';
        http_response_code(429);
    } elseif (!consumeCaptcha(formString($_POST, 'captcha', 20), time())) {
        $error = 'The verification code is incorrect or expired. Please try the new code.';
    } else {
        $user = verifyCredentials(db(), trim(formString($_POST, 'username', 64)), formString($_POST, 'password', 1024));
        if ($user) {
            session_regenerate_id(true);
            $_SESSION = ['user' => $user, 'last_activity' => time()];
            redirect('/dashboard.php');
        }
        $error = 'Incorrect username or password.';
    }
}
$csrf = csrfToken();
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in · Ubuntu Access</title><link rel="stylesheet" href="/assets/style.css"><script src="/assets/login.js" defer></script></head>
<body><main class="card"><div class="mark" aria-hidden="true">U</div><p class="eyebrow">UBUNTU ACCESS</p><h1>Welcome back.</h1><p class="intro">Sign in to your secure dashboard.</p>
<?php if (isset($_GET['expired'])): ?><p class="notice" role="status">Your session expired after five minutes of inactivity. Please sign in again.</p><?php endif ?>
<?php if (isset($_GET['logout'])): ?><p class="notice" role="status">You have been logged out.</p><?php endif ?>
<?php if ($error): ?><p class="error" role="alert"><?= escape($error) ?></p><?php endif ?>
<form method="post" action="/login.php">
<input type="hidden" name="csrf" value="<?= escape($csrf) ?>">
<label for="username">Username</label><input id="username" name="username" autocomplete="username" maxlength="64" required value="<?= escape(trim(formString($_POST, 'username', 64))) ?>">
<label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" maxlength="1024" required>
<label for="captcha">Verification code</label><div class="challenge"><img id="captcha-image" src="/captcha.php" width="240" height="80" alt="Six-character verification code"><button class="secondary" type="button" id="refresh">New code</button></div>
<input id="captcha" name="captcha" autocomplete="off" spellcheck="false" maxlength="6" aria-describedby="captcha-help" required><small id="captcha-help">Enter the six characters shown above. Uppercase or lowercase is fine.</small>
<button class="primary" type="submit">Sign in <span aria-hidden="true">→</span></button></form><p class="footnote">Your session locks after 5 minutes of inactivity.</p></main></body></html>
