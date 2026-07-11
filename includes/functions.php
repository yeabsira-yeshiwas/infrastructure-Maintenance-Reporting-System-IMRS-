<?php
/**
 * General Utility Functions
 * Infrastructure Maintenance Reporting System (IMRS)
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Sanitize input to prevent XSS
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate unique request number
 */
function generateRequestNumber() {
    $prefix = 'REQ';
    $date = date('Ymd');
    $random = strtoupper(substr(uniqid(), -6));
    return $prefix . '-' . $date . '-' . $random;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

/**
 * Canonical student dorm/area names (must match proctor assignment checkboxes and create-request dropdown).
 */
function getCanonicalStudentLocations(): array {
    return [
        'Taytu',
        'Lucy',
        'Amsale Gualu',
        'Gafat',
        'Aklilu Lemma',
        'Guna',
        'James Watt',
        'Albert Einstein',
        'Thomas Edison',
        'Abdissa Aga',
    ];
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $badges = [
        'Pending' => '<span class="badge badge-warning">Pending</span>',
        'Approved' => '<span class="badge badge-success">Approved</span>',
        'Rejected' => '<span class="badge badge-danger">Rejected</span>',
        'Assigned' => '<span class="badge badge-info">Assigned</span>',
        'In_Progress' => '<span class="badge badge-primary">In Progress</span>',
        'Completed' => '<span class="badge badge-success">Completed</span>',
        'Closed' => '<span class="badge badge-secondary">Closed</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge">' . $status . '</span>';
}

/**
 * Get priority badge HTML
 */
function getPriorityBadge($priority) {
    $badges = [
        'Low' => '<span class="badge badge-secondary">Low</span>',
        'Medium' => '<span class="badge badge-info">Medium</span>',
        'High' => '<span class="badge badge-warning">High</span>',
        'Urgent' => '<span class="badge badge-danger">Urgent</span>'
    ];
    
    return $badges[$priority] ?? '<span class="badge">' . $priority . '</span>';
}

/**
 * Create notification.
 * Pass $conn when called inside an open transaction so FK checks reuse the same connection
 * (avoids lock wait timeouts from a second connection waiting on uncommitted rows).
 */
function createNotification($userId, $requestId, $type, $title, $message, $conn = null) {
    $ownConnection = $conn === null;
    if ($ownConnection) {
        $conn = getDBConnection();
    }

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, request_id, notification_type, title, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $userId, $requestId, $type, $title, $message);
    $stmt->execute();
    $stmt->close();

    if ($ownConnection) {
        closeDBConnection($conn);
    }
}

/**
 * Update request status and log the change
 */
function updateRequestStatus($requestId, $newStatus, $message = null, $conn = null) {
    $ownConnection = $conn === null;
    if ($ownConnection) {
        $conn = getDBConnection();
    }

    $userId = getCurrentUserId();
    $logMessage = $message ?? '';

    $stmt = $conn->prepare("SELECT status FROM maintenance_requests WHERE request_id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        if ($ownConnection) {
            closeDBConnection($conn);
        }
        return;
    }
    $oldStatus = $row['status'];

    $stmt = $conn->prepare("UPDATE maintenance_requests SET status = ? WHERE request_id = ?");
    $stmt->bind_param("si", $newStatus, $requestId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO status_updates (request_id, updated_by, old_status, new_status, update_message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $requestId, $userId, $oldStatus, $newStatus, $logMessage);
    $stmt->execute();
    $stmt->close();

    if ($ownConnection) {
        closeDBConnection($conn);
    }
}

function logRequestStatusUpdate($requestId, $updatedBy, $oldStatus, $newStatus, $message, $conn = null) {
    $ownConnection = $conn === null;
    if ($ownConnection) {
        $conn = getDBConnection();
    }

    $stmt = $conn->prepare("INSERT INTO status_updates (request_id, updated_by, old_status, new_status, update_message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $requestId, $updatedBy, $oldStatus, $newStatus, $message);
    $stmt->execute();
    $stmt->close();

    if ($ownConnection) {
        closeDBConnection($conn);
    }
}

function hasCompletionReviewApproval(int $requestId, $conn = null) {
    $ownConnection = $conn === null;
    if ($ownConnection) {
        $conn = getDBConnection();
    }

    $stmt = $conn->prepare("SELECT 1 FROM approvals WHERE request_id = ? AND decision = 'Approved' AND feedback LIKE 'COMPLETION_APPROVAL|%' LIMIT 1");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $approved = $result->num_rows > 0;
    $stmt->close();

    if ($ownConnection) {
        closeDBConnection($conn);
    }

    return $approved;
}

/**
 * Get user notifications count
 */
function getUnreadNotificationsCount($userId) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    closeDBConnection($conn);
    
    return $count;
}

/**
 * Validate file upload
 */
function validateFileUpload($file) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error occurred.";
        return $errors;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "File size exceeds maximum allowed size (5MB).";
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_FILE_TYPES)) {
        $errors[] = "File type not allowed. Only images and PDF files are permitted.";
    }
    
    return $errors;
}

/**
 * Check whether a given column exists in a table.
 */
function columnExists(string $table, string $column): bool {
    $conn = getDBConnection();
    $tableEsc = $conn->real_escape_string($table);
    $columnEsc = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$columnEsc}'";
    $result = $conn->query($sql);
    $exists = $result && $result->num_rows > 0;
    if ($result) {
        $result->free();
    }
    closeDBConnection($conn);
    return $exists;
}

/**
 * Upload file and return path
 */
function uploadFile($file, $requestId) {
    $errors = validateFileUpload($file);
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = UPLOAD_DIR . $requestId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Save to database
        $conn = getDBConnection();
        $relativePath = 'uploads/' . $requestId . '/' . $fileName;
        
        $stmt = $conn->prepare("INSERT INTO request_attachments (request_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $requestId, $file['name'], $relativePath, $file['type'], $file['size']);
        $stmt->execute();
        $attachmentId = $conn->insert_id;
        $stmt->close();
        closeDBConnection($conn);
        
        return ['success' => true, 'path' => $relativePath, 'attachment_id' => $attachmentId];
    } else {
        return ['success' => false, 'errors' => ['Failed to upload file.']];
    }
}
?>



