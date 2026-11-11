<?php
require __DIR__ . '/../src/bootstrap.php';
requireRole('admin');
$roleTitle = 'Admin';
$roleDescription = 'You are signed in to the Admin area.';
require __DIR__ . '/../src/dashboard-view.php';
