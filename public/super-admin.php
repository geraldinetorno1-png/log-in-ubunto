<?php
require __DIR__ . '/../src/bootstrap.php';
requireRole('super_admin');
$roleTitle = 'Super Admin';
$roleDescription = 'You are signed in to the Super Admin area.';
require __DIR__ . '/../src/dashboard-view.php';
