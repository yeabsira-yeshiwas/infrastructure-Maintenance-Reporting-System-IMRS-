<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    logSystemAction('User Logout', 'users', getCurrentUserId(), 'User logged out');
    logout();
}

header('Location: ' . BASE_URL . '/auth/login.php');
exit();
?>



