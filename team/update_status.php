<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('Maintenance_Team');

$conn = getDBConnection();
$requestId = intval($_GET['id'] ?? 0);
$error = '';

if ($requestId === 0) {
    header('Location: ' . BASE_URL . '/team/my_tasks.php?error=invalid_request');
    exit();
}

// Verify task is assigned to current user
$userId = getCurrentUserId();

// First, get the maintenance request
$stmt = $conn->prepare("SELECT mr.*, u.role AS requester_role FROM maintenance_requests mr
    JOIN users u ON mr.user_id = u.user_id
    WHERE mr.request_id = ?");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    header('Location: ' . BASE_URL . '/team/my_tasks.php?error=invalid_request');
    exit();
}

// Then, verify user is assigned to this task (check all assignments)
$assignSql = "SELECT 1 FROM task_assignments WHERE request_id = ? AND assigned_to = ? LIMIT 1";
$stmt = $conn->prepare($assignSql);
$stmt->bind_param("ii", $requestId, $userId);
$stmt->execute();
$assignment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assignment) {
    header('Location: ' . BASE_URL . '/team/my_tasks.php?error=access_denied');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = sanitizeInput($_POST['status'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    
    if (empty($newStatus)) {
        $error = 'Please select a status.';
    } elseif (!in_array($newStatus, ['In_Progress', 'Completed'])) {
        $error = 'Invalid status selected.';
    } else {
        // Update status
        updateRequestStatus($requestId, $newStatus, $message);
        
        if ($newStatus === 'Completed') {
            $stmt = $conn->prepare("UPDATE maintenance_requests SET completed_at = NOW() WHERE request_id = ?");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $stmt->close();
            
            // Notify requester
            createNotification($request['user_id'], $requestId, 'task_completed', 'Task Completed', "Your maintenance request has been completed.");

            // For student and staff requests, notify the approver to review completion before manager closure.
            if (in_array($request['requester_role'], ['Student', 'Staff'], true)) {
                $approverIds = [];

                if ($request['requester_role'] === 'Staff' && !empty($request['assigned_office_head_id'])) {
                    $approverIds[] = (int) $request['assigned_office_head_id'];
                }

                if ($request['requester_role'] === 'Student' && !empty($request['location_id'])) {
                    $locationStmt = $conn->prepare("SELECT DISTINCT pl.proctor_user_id FROM proctor_locations pl JOIN locations l ON TRIM(pl.location_name) = TRIM(l.location_name) WHERE l.location_id = ?");
                    $locationStmt->bind_param("i", $request['location_id']);
                    $locationStmt->execute();
                    $proctorResult = $locationStmt->get_result();
                    while ($row = $proctorResult->fetch_assoc()) {
                        $approverIds[] = (int) $row['proctor_user_id'];
                    }
                    $locationStmt->close();
                }

                foreach (array_unique($approverIds) as $approverId) {
                    if ($approverId > 0) {
                        createNotification($approverId, $requestId, 'completion_review', 'Completion Review Needed', 'A completed maintenance task needs your review and approval before it goes to the manager.');
                    }
                }
            }
        }
        
        logSystemAction('Update Task Status', 'maintenance_requests', $requestId, "Status updated to: $newStatus");
        
        header('Location: ' . BASE_URL . '/team/my_tasks.php?success=status_updated');
        exit();
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Status - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>
    
    <div class="page-container">
        <div class="page-header">
            <h1><i class="fas fa-edit"></i> Update Task Status</h1>
            <a href="<?php echo BASE_URL; ?>/team/my_tasks.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <form method="POST" action="" class="form">
                <div class="form-group">
                    <label for="status">
                        <i class="fas fa-tasks"></i> Status *
                    </label>
                    <select id="status" name="status" required>
                        <option value="">Select Status</option>
                        <?php if ($request['status'] === 'Assigned'): ?>
                        <option value="In_Progress">In Progress</option>
                        <?php endif; ?>
                        <?php if (in_array($request['status'], ['Assigned', 'In_Progress'])): ?>
                        <option value="Completed">Completed</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="message">
                        <i class="fas fa-comment"></i> Update Message (Optional)
                    </label>
                    <textarea id="message" name="message" rows="4" placeholder="Add any notes about the progress or completion"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                    <a href="<?php echo BASE_URL; ?>/team/my_tasks.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>



