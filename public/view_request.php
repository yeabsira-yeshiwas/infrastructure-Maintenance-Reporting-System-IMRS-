<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$conn = getDBConnection();
$requestId = intval($_GET['id'] ?? 0);
$email = sanitizeInput($_GET['email'] ?? '');

if ($requestId === 0) {
    header('Location: ' . BASE_URL . '/public/track_request.php?error=invalid_request');
    exit();
}

// Get request details
$stmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, l.building
    FROM maintenance_requests mr
    JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
    JOIN locations l ON mr.location_id = l.location_id
    WHERE mr.request_id = ? AND (mr.user_id IS NULL AND mr.submitter_email = ?)");
$stmt->bind_param("is", $requestId, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . BASE_URL . '/public/track_request.php?error=access_denied');
    exit();
}

$request = $result->fetch_assoc();
$stmt->close();

// Get attachments
$stmt = $conn->prepare("SELECT * FROM request_attachments WHERE request_id = ?");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get status history
$stmt = $conn->prepare("SELECT su.*, u.full_name as updated_by_name
    FROM status_updates su
    LEFT JOIN users u ON su.updated_by = u.user_id
    WHERE su.request_id = ?
    ORDER BY su.updated_at DESC");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$statusHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Request - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-page">
    <div class="auth-container" style="max-width: 900px;">
        <div class="auth-card">
            <div class="page-header" style="margin-bottom: 1.5rem;">
                <h1><i class="fas fa-eye"></i> Request Details</h1>
                <a href="<?php echo BASE_URL; ?>/public/track_request.php?email=<?php echo urlencode($email); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            
            <div class="request-details">
                <div class="card">
                    <div class="card-header">
                        <h2>Request Information</h2>
                        <div class="request-meta">
                            <span class="request-number"><?php echo htmlspecialchars($request['request_number']); ?></span>
                            <?php echo getStatusBadge($request['status']); ?>
                            <?php echo getPriorityBadge($request['priority']); ?>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label><i class="fas fa-heading"></i> Title</label>
                                <p><?php echo htmlspecialchars($request['title']); ?></p>
                            </div>
                            
                            <div class="detail-item">
                                <label><i class="fas fa-building"></i> Infrastructure Type</label>
                                <p><?php echo htmlspecialchars($request['type_name']); ?></p>
                            </div>
                            
                            <div class="detail-item">
                                <label><i class="fas fa-map-marker-alt"></i> Location</label>
                                <p><?php echo htmlspecialchars(trim($request['location_name'] . ' - ' . $request['building'])); ?></p>
                            </div>
                            
                            <div class="detail-item">
                                <label><i class="fas fa-user"></i> Submitted By</label>
                                <p><?php echo htmlspecialchars($request['submitter_name']); ?></p>
                            </div>
                            
                            <div class="detail-item">
                                <label><i class="fas fa-calendar"></i> Submitted At</label>
                                <p><?php echo formatDate($request['submitted_at']); ?></p>
                            </div>
                            
                            <?php if ($request['completed_at']): ?>
                            <div class="detail-item">
                                <label><i class="fas fa-check-circle"></i> Completed At</label>
                                <p><?php echo formatDate($request['completed_at']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="detail-item full-width">
                                <label><i class="fas fa-align-left"></i> Description</label>
                                <p><?php echo nl2br(htmlspecialchars($request['description'])); ?></p>
                            </div>
                        </div>
                        
                        <?php if (!empty($attachments)): ?>
                        <div class="attachments-section">
                            <h3><i class="fas fa-paperclip"></i> Attachments</h3>
                            <div class="attachments-grid">
                                <?php foreach ($attachments as $attachment): ?>
                                <div class="attachment-item">
                                    <a href="<?php echo BASE_URL . '/' . $attachment['file_path']; ?>" target="_blank">
                                        <i class="fas fa-file"></i>
                                        <?php echo htmlspecialchars($attachment['file_name']); ?>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($statusHistory)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-history"></i> Status History</h2>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($statusHistory as $update): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker"></div>
                                <div class="timeline-content">
                                    <p><strong><?php echo htmlspecialchars($update['updated_by_name'] ?? 'System'); ?></strong> changed status from 
                                    <span class="badge"><?php echo htmlspecialchars($update['old_status'] ?? 'N/A'); ?></span> to 
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($update['new_status']); ?></span></p>
                                    <?php if ($update['update_message']): ?>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($update['update_message'])); ?></p>
                                    <?php endif; ?>
                                    <p class="text-muted"><small><?php echo formatDate($update['updated_at']); ?></small></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="auth-footer">
                <p><a href="<?php echo BASE_URL; ?>/public/track_request.php?email=<?php echo urlencode($email); ?>">View All Requests</a> | <a href="<?php echo BASE_URL; ?>/auth/register_general.php">Register for Full Access</a></p>
            </div>
        </div>
    </div>
</body>
</html>



