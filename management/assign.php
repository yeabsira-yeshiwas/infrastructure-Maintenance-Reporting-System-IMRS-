<?php
$pageTitle = 'Assign Task';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAnyRole(['Maintenance_Manager', 'Admin']);

$conn = getDBConnection();
$requestId = intval($_GET['id'] ?? 0);
$error = '';

if ($requestId === 0) {
    closeDBConnection($conn);
    header('Location: ' . BASE_URL . '/management/requests.php?error=invalid_request');
    exit();
}

$stmt = $conn->prepare("SELECT mr.*, it.type_name AS infra_type_name FROM maintenance_requests mr LEFT JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id WHERE mr.request_id = ? AND mr.status IN ('Approved', 'Assigned', 'In_Progress')");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    header('Location: ' . BASE_URL . '/management/requests.php?error=invalid_request');
    exit();
}

$request = $result->fetch_assoc();
$stmt->close();

$infraType = $request['infra_type_name'] ?? '';

// Only list maintenance team members whose department (specialty) matches the request's infrastructure type
$stmt = $conn->prepare("SELECT user_id, full_name, email, department FROM users WHERE role = 'Maintenance_Team' AND status = 'Active' AND (department = ? OR department = '' OR department IS NULL) ORDER BY full_name");
$stmt->bind_param("s", $infraType);
$stmt->execute();
$teamMembers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignedTo = intval($_POST['assigned_to'] ?? 0);
    $scheduledDate = $_POST['scheduled_date'] ?? '';
    $notes = sanitizeInput($_POST['notes'] ?? '');

    if ($assignedTo === 0) {
        $error = 'Please select a team member to assign this task to.';
    } else {
        // Validate assigned member specialty matches request infrastructure type
        $checkDept = $conn->prepare("SELECT department FROM users WHERE user_id = ? LIMIT 1");
        $checkDept->bind_param("i", $assignedTo);
        $checkDept->execute();
        $deptRow = $checkDept->get_result()->fetch_assoc();
        $checkDept->close();
        $assignedDept = trim((string)($deptRow['department'] ?? ''));
        $infraType = $request['infra_type_name'] ?? '';
        if ($assignedDept !== '' && strcasecmp($assignedDept, $infraType) !== 0) {
            $error = 'Selected team member does not match the infrastructure type of the request.';
        } else {
        $assignedBy = getCurrentUserId();
        $conn->begin_transaction();

        try {
            // Only use is_active logic if column exists (migration applied)
            if (columnExists('task_assignments', 'is_active')) {
                // Deactivate previous active assignment (if any) and notify previous assignee
                $prevStmt = $conn->prepare("SELECT assignment_id, assigned_to FROM task_assignments WHERE request_id = ? AND is_active = 1 ORDER BY assignment_id DESC LIMIT 1");
                $prevStmt->bind_param("i", $requestId);
                $prevStmt->execute();
                $prevRow = $prevStmt->get_result()->fetch_assoc();
                $prevStmt->close();

                if ($prevRow && (int)$prevRow['assigned_to'] !== $assignedTo) {
                    $deact = $conn->prepare("UPDATE task_assignments SET is_active = 0 WHERE assignment_id = ?");
                    $deact->bind_param("i", $prevRow['assignment_id']);
                    $deact->execute();
                    $deact->close();

                    // Notify previous assignee that they have been unassigned
                    createNotification((int)$prevRow['assigned_to'], $requestId, 'task_reassigned', 'Task Reassigned', 'This task has been reassigned to another team member.', $conn);
                }

                // Insert new active assignment
                $stmt = $conn->prepare("INSERT INTO task_assignments (request_id, assigned_by, assigned_to, scheduled_date, notes, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                $scheduledDate = !empty($scheduledDate) ? $scheduledDate : null;
                $stmt->bind_param("iiiss", $requestId, $assignedBy, $assignedTo, $scheduledDate, $notes);
                $stmt->execute();
                $stmt->close();
            } else {
                // Fallback: insert assignment without is_active column
                $stmt = $conn->prepare("INSERT INTO task_assignments (request_id, assigned_by, assigned_to, scheduled_date, notes) VALUES (?, ?, ?, ?, ?)");
                $scheduledDate = !empty($scheduledDate) ? $scheduledDate : null;
                $stmt->bind_param("iiiss", $requestId, $assignedBy, $assignedTo, $scheduledDate, $notes);
                $stmt->execute();
                $stmt->close();
            }

            $updatedPriority = sanitizeInput($_POST['priority'] ?? $request['priority']);
            if (!in_array($updatedPriority, ['Low', 'Medium', 'High', 'Urgent'], true)) {
                $updatedPriority = $request['priority'];
            }

            $priorityStmt = $conn->prepare("UPDATE maintenance_requests SET priority = ? WHERE request_id = ?");
            $priorityStmt->bind_param("si", $updatedPriority, $requestId);
            $priorityStmt->execute();
            $priorityStmt->close();

            updateRequestStatus($requestId, 'Assigned', 'Task assigned or reassigned to maintenance team', $conn);
            $stmt = $conn->prepare("UPDATE maintenance_requests SET assigned_at = NOW() WHERE request_id = ?");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $stmt->close();

            createNotification($assignedTo, $requestId, 'task_assigned', 'New Task Assigned', 'You have been assigned a new maintenance task.', $conn);

            $stmt = $conn->prepare("SELECT user_id FROM maintenance_requests WHERE request_id = ?");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $requesterResult = $stmt->get_result();
            $requester = $requesterResult->fetch_assoc();
            $stmt->close();
            if (!empty($requester['user_id'])) {
                createNotification($requester['user_id'], $requestId, 'task_assigned', 'Task Assigned', 'Your maintenance request has been assigned to a team member.', $conn);
            }

            logSystemAction('Assign Task', 'task_assignments', $requestId, 'Task assigned to team member', $conn);

            $conn->commit();
            closeDBConnection($conn);
            header('Location: ' . BASE_URL . '/management/requests.php?success=task_assigned');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to assign task. Please try again.';
        }
    }
}

}

closeDBConnection($conn);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container management-shell">
    <section class="management-hero">
        <h1><i class="fas fa-user-check"></i> Assign Task</h1>
        <p>Move approved work into execution by assigning the right technician, scheduling the job, and attaching manager instructions.</p>
        <div class="management-hero-meta">
            <span class="management-chip"><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($request['request_number']); ?></span>
            <span class="management-chip"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($request['priority']); ?> priority</span>
        </div>
    </section>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <div class="management-grid-2">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Assignment Form</h2>
                    <p>Select the technician, set an optional schedule, and add execution notes.</p>
                </div>
                <a href="<?php echo BASE_URL; ?>/management/requests.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            <div class="panel-body">
                <form method="POST" action="<?php echo BASE_URL; ?>/management/assign.php?id=<?php echo (int) $requestId; ?>" class="form">
            <div class="form-group">
                <label for="assigned_to">
                    <i class="fas fa-user"></i> Assign To *
                </label>
                <select id="assigned_to" name="assigned_to" required>
                    <option value="">Select Team Member</option>
                    <?php foreach ($teamMembers as $member): ?>
                    <option value="<?php echo $member['user_id']; ?>">
                        <?php echo htmlspecialchars($member['full_name'] . ' (' . $member['email'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="priority">
                    <i class="fas fa-flag"></i> Priority
                </label>
                <select id="priority" name="priority">
                    <?php foreach (['Low', 'Medium', 'High', 'Urgent'] as $priority): ?>
                    <option value="<?php echo $priority; ?>" <?php echo $request['priority'] === $priority ? 'selected' : ''; ?>>
                        <?php echo $priority; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="scheduled_date">
                    <i class="fas fa-calendar"></i> Scheduled Date
                </label>
                <input type="date" id="scheduled_date" name="scheduled_date">
            </div>

            <div class="form-group">
                <label for="notes">
                    <i class="fas fa-comment"></i> Notes
                </label>
                <textarea id="notes" name="notes" rows="4" placeholder="Add any special instructions or notes"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-check"></i> Assign Task
                </button>
                <a href="<?php echo BASE_URL; ?>/management/requests.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
            </div>
        </section>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h3>Request Snapshot</h3>
                    <p>Confirm the work order before assigning it.</p>
                </div>
            </div>
            <div class="panel-body detail-list">
                <div class="detail-row">
                    <div class="detail-row-label">Title</div>
                    <div class="detail-row-value"><?php echo htmlspecialchars($request['title']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-row-label">Priority</div>
                    <div class="detail-row-value"><?php echo htmlspecialchars($request['priority']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-row-label">Status</div>
                    <div class="detail-row-value"><?php echo htmlspecialchars(str_replace('_', ' ', $request['status'])); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-row-label">Submitted</div>
                    <div class="detail-row-value"><?php echo formatDate($request['submitted_at'], 'M d, Y'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-row-label">Description</div>
                    <div class="detail-row-value"><?php echo htmlspecialchars($request['description']); ?></div>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
