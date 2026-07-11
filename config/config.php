<?php
/**
 * Application Configuration
 * Infrastructure Maintenance Reporting System (IMRS)
 */

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application settings
define('APP_NAME', 'IMRS - Infrastructure Maintenance Reporting System');
define('APP_VERSION', '1.0.0');
// Encode spaces so form actions and redirects resolve correctly (e.g. /final%20project/)
define('BASE_URL', 'http://localhost/final%20project');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'application/pdf']);

// Pagination
define('RECORDS_PER_PAGE', 10);

// Timezone
date_default_timezone_set('Africa/Addis_Ababa');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once __DIR__ . '/database.php';
?>



