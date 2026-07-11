<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$conn = getDBConnection();
$requestId = intval($_GET['id'] ?? 0);

if ($requestId === 0) {
    header('Location: ' . BASE_URL . '/index.php?error=invalid_request');
    exit();
}

// Get request details
$stmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, l.building, u.full_name, u.email, u.role as user_role
    FROM maintenance_requests mr
    JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
    JOIN locations l ON mr.location_id = l.location_id
    LEFT JOIN users u ON mr.user_id = u.user_id
    WHERE mr.request_id = ?");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . BASE_URL . '/index.php?error=invalid_request');
    exit();
}

$request = $result->fetch_assoc();
$stmt->close();

$pageTitle = 'View Request';
require_once __DIR__ . '/../includes/header.php';

// Check access permission
$currentUserId = getCurrentUserId();
$currentUserRole = getCurrentUserRole();

$hasAccess = hasRole('Admin') || hasRole('Maintenance_Manager') || hasAnyRole(['Proctor', 'Office_Head']) || $request['user_id'] == $currentUserId;

if (!$hasAccess && $currentUserRole === 'Maintenance_Team') {
    $sql = "SELECT 1 FROM task_assignments WHERE request_id = ? AND assigned_to = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $requestId, $currentUserId);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $hasAccess = $assignment !== null;
}

if (!$hasAccess) {
    header('Location: ' . BASE_URL . '/index.php?error=access_denied');
    exit();
}

// Get attachments
$stmt = $conn->prepare("SELECT * FROM request_attachments WHERE request_id = ?");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get approval records
$stmt = $conn->prepare("SELECT a.*, u.full_name as approver_name
    FROM approvals a
    JOIN users u ON a.approver_id = u.user_id
    WHERE a.request_id = ?");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$approvals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get task assignment
$sql = "SELECT ta.*, u1.full_name as assigned_by_name, u2.full_name as assigned_to_name
    FROM task_assignments ta
    JOIN users u1 ON ta.assigned_by = u1.user_id
    JOIN users u2 ON ta.assigned_to = u2.user_id
    WHERE ta.request_id = ?";
$hasIsActive = columnExists('task_assignments', 'is_active');
if ($hasIsActive) {
    $sql .= " AND ta.is_active = 1";
}
$sql .= " ORDER BY ta.assignment_id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $requestId);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get status history
$stmt = $conn->prepare("SELECT su.*, u.full_name as updated_by_name
    FROM status_updates su
    JOIN users u ON su.updated_by = u.user_id
    WHERE su.request_id = ?
    ORDER BY su.updated_at DESC");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$statusHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-eye"></i> Request Details</h1>
        <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-secondary">
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
                        <p>
                            <?php 
                            if ($request['user_id']) {
                                echo htmlspecialchars($request['full_name'] . ' (' . $request['user_role'] . ')');
                            } else {
                                echo htmlspecialchars($request['submitter_name'] . ' (General User/Guest)');
                            }
                            ?>
                        </p>
                    </div>
                    
                    <div class="detail-item">
                        <label><i class="fas fa-calendar"></i> Submitted At</label>
                        <p><?php echo formatDate($request['submitted_at']); ?></p>
                    </div>
                    
                    <?php if ($request['approved_at']): ?>
                    <div class="detail-item">
                        <label><i class="fas fa-check"></i> Approved At</label>
                        <p><?php echo formatDate($request['approved_at']); ?></p>
                    </div>
                    <?php endif; ?>
                    
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
        
        <?php if (!empty($approvals)): ?>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-check-circle"></i> Approval History</h2>
            </div>
            <div class="card-body">
                <?php foreach ($approvals as $approval): ?>
                <div class="approval-item">
                    <p><strong><?php echo htmlspecialchars($approval['approver_name']); ?></strong> 
                    (<?php echo htmlspecialchars($approval['approver_role']); ?>) - 
                    <span class="badge <?php echo $approval['decision'] === 'Approved' ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo htmlspecialchars($approval['decision']); ?>
                    </span></p>
                    <p class="text-muted"><?php echo formatDate($approval['approved_at']); ?></p>
                    <?php if ($approval['feedback']): ?>
                    <p><?php echo nl2br(htmlspecialchars($approval['feedback'])); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($assignment): ?>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-user-check"></i> Task Assignment</h2>
            </div>
            <div class="card-body">
                <p><strong>Assigned To:</strong> <?php echo htmlspecialchars($assignment['assigned_to_name']); ?></p>
                <p><strong>Assigned By:</strong> <?php echo htmlspecialchars($assignment['assigned_by_name']); ?></p>
                <p><strong>Assigned At:</strong> <?php echo formatDate($assignment['assigned_at']); ?></p>
                <?php if ($assignment['scheduled_date']): ?>
                <p><strong>Scheduled Date:</strong> <?php echo formatDate($assignment['scheduled_date'], 'Y-m-d'); ?></p>
                <?php endif; ?>
                <?php if ($assignment['notes']): ?>
                <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($assignment['notes'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
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
                            <p><strong><?php echo htmlspecialchars($update['updated_by_name']); ?></strong> changed status from 
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



