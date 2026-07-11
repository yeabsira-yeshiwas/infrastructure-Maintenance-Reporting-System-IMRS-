<?php
require_once __DIR__ . '/../includes/auth.php';

requireAnyRole(['Maintenance_Manager', 'Admin']);

header('Location: ' . BASE_URL . '/management/requests.php');
exit();
