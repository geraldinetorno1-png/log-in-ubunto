<?php
require __DIR__ . '/../src/bootstrap.php';
redirect(sessionExpired(time()) ? '/login.php' : '/dashboard.php');
