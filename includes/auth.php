<?php
/**
 * Authentication and Authorization Functions
 * Infrastructure Maintenance Reporting System (IMRS)
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Check if user has specific role
 */
function hasRole($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Admin has access to everything
    if ($_SESSION['role'] === 'Admin') {
        return true;
    }
    // Normalize role strings (allow 'Maintenance Team' or 'Maintenance_Team')
    $current = str_replace(' ', '_', trim($_SESSION['role']));
    $required = str_replace(' ', '_', trim($requiredRole));
    return strcasecmp($current, $required) === 0;
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if ($_SESSION['role'] === 'Admin') {
        return true;
    }
    $current = str_replace(' ', '_', trim($_SESSION['role']));
    foreach ($roles as $r) {
        if (strcasecmp($current, str_replace(' ', '_', trim($r))) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit();
    }
}

/**
 * Require specific role - redirect if user doesn't have role
 */
function requireRole($requiredRole) {
    requireLogin();
    
    if (!hasRole($requiredRole)) {
        header('Location: ' . BASE_URL . '/index.php?error=access_denied');
        exit();
    }
}

/**
 * Require any of the specified roles
 */
function requireAnyRole($roles) {
    requireLogin();
    
    if (!hasAnyRole($roles)) {
        header('Location: ' . BASE_URL . '/index.php?error=access_denied');
        exit();
    }
}

/**
 * Get current user ID (always int when set, so strict comparisons with DB ids work reliably).
 */
function getCurrentUserId(): ?int {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return (int) $_SESSION['user_id'];
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Logout user
 */
function logout() {
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Hash password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Log system action.
 * Pass $conn when inside an active transaction (same reason as createNotification).
 */
function logSystemAction($action, $tableName = null, $recordId = null, $details = null, $conn = null) {
    $ownConnection = $conn === null;
    if ($ownConnection) {
        $conn = getDBConnection();
    }
    $userId = getCurrentUserId();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $userId, $action, $tableName, $recordId, $details, $ipAddress);
    $stmt->execute();
    $stmt->close();

    if ($ownConnection) {
        closeDBConnection($conn);
    }
}
?>



