<?php
require_once __DIR__ . '/../includes/auth.php';

requireAnyRole(['Maintenance_Manager', 'Admin']);

$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';

header('Location: ' . BASE_URL . '/management/assign.php' . $query);
exit();

