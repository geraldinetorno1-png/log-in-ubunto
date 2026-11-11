<?php
require __DIR__ . '/../src/bootstrap.php';
requireRole('user');
$roleTitle = 'User';
$roleDescription = 'You are signed in to your personal User area.';
require __DIR__ . '/../src/dashboard-view.php';
