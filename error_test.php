<?php
// Error Test File - Remove after testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Test database connection
require_once __DIR__ . '/config/database.php';

try {
    $conn = getDBConnection();
    echo "Database connection: SUCCESS<br>";
    closeDBConnection($conn);
} catch (Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "<br>";
}

// Test includes
echo "Testing includes...<br>";
if (file_exists(__DIR__ . '/includes/auth.php')) {
    echo "auth.php: EXISTS<br>";
} else {
    echo "auth.php: NOT FOUND<br>";
}

if (file_exists(__DIR__ . '/includes/functions.php')) {
    echo "functions.php: EXISTS<br>";
} else {
    echo "functions.php: NOT FOUND<br>";
}

echo "<br>All tests complete!";
?>



