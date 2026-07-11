<?php
$pageTitle = 'Approve Request';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAnyRole(['Proctor', 'Office_Head']);

$conn = getDBConnection();
$requestId = intval($_GET['id'] ?? 0);
$error = '';

if ($requestId === 0) {
    closeDBConnection($conn);
    header('Location: ' . BASE_URL . '/approvals/pending.php?error=invalid_request');
    exit();
}

$stmt = $conn->prepare("SELECT mr.*, u.role as user_role
    FROM maintenance_requests mr
    JOIN users u ON mr.user_id = u.user_id
    WHERE mr.request_id = ? AND mr.status IN ('Pending', 'Completed')");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    header('Location: ' . BASE_URL . '/approvals/pending.php?error=invalid_request');
    exit();
}

$request = $result->fetch_assoc();
$stmt->close();
$currentUserRole = getCurrentUserRole();
$isCompletionReview = $request['status'] === 'Completed';
$pageTitle = $isCompletionReview ? 'Approve Completion' : 'Approve Request';

if (($currentUserRole === 'Proctor' && $request['user_role'] !== 'Student') ||
    ($currentUserRole === 'Office_Head' && $request['user_role'] !== 'Staff')) {
    closeDBConnection($conn);
    header('Location: ' . BASE_URL . '/approvals/pending.php?error=access_denied');
    exit();
}

if ($currentUserRole === 'Office_Head' && (int) ($request['assigned_office_head_id'] ?? 0) !== (getCurrentUserId() ?? 0)) {
    closeDBConnection($conn);
    header('Location: ' . BASE_URL . '/approvals/pending.php?error=access_denied');
    exit();
}

if ($currentUserRole === 'Proctor') {
    $plStmt = $conn->prepare("SELECT 1 FROM proctor_locations pl
        INNER JOIN maintenance_requests mr ON mr.request_id = ?
        INNER JOIN locations l ON l.location_id = mr.location_id
        WHERE pl.proctor_user_id = ? AND TRIM(pl.location_name) = TRIM(l.location_name)
        LIMIT 1");
    $pid = getCurrentUserId() ?? 0;
    $plStmt->bind_param("ii", $requestId, $pid);
    $plStmt->execute();
    if ($plStmt->get_result()->num_rows === 0) {
        $plStmt->close();
        closeDBConnection($conn);
        header('Location: ' . BASE_URL . '/approvals/pending.php?error=access_denied');
        exit();
    }
    $plStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback = sanitizeInput($_POST['feedback'] ?? '');
    $approverId = getCurrentUserId();
    $roleLabel = str_replace('_', ' ', $currentUserRole);

    $conn->begin_transaction();

    try {
        if ($isCompletionReview) {
            $approvalFeedback = 'COMPLETION_APPROVAL|' . $feedback;
            $stmt = $conn->prepare("INSERT INTO approvals (request_id, approver_id, approver_role, decision, feedback) VALUES (?, ?, ?, 'Approved', ?)");
            $stmt->bind_param("iiss", $requestId, $approverId, $currentUserRole, $approvalFeedback);
            $stmt->execute();
            $stmt->close();

            createNotification($request['user_id'], $requestId, 'completion_approved', 'Completion Approved', 'Your completed maintenance task has been approved by ' . $roleLabel . '.', $conn);

            $managerStmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'Maintenance_Manager' LIMIT 1");
            $managerStmt->execute();
            $managerResult = $managerStmt->get_result();
            if ($managerResult->num_rows > 0) {
                $manager = $managerResult->fetch_assoc();
                createNotification($manager['user_id'], $requestId, 'completion_reviewed', 'Completion Approved', 'A completed maintenance task has been reviewed and approved by ' . $roleLabel . '.', $conn);
            }
            $managerStmt->close();

            logSystemAction('Approve Completion', 'maintenance_requests', $requestId, 'Completion approved by ' . $roleLabel, $conn);
        } else {
            $stmt = $conn->prepare("INSERT INTO approvals (request_id, approver_id, approver_role, decision, feedback) VALUES (?, ?, ?, 'Approved', ?)");
            $stmt->bind_param("iiss", $requestId, $approverId, $currentUserRole, $feedback);
            $stmt->execute();
            $stmt->close();

            updateRequestStatus($requestId, 'Approved', 'Request approved by ' . $roleLabel, $conn);

            $stmt = $conn->prepare("UPDATE maintenance_requests SET approved_at = NOW() WHERE request_id = ?");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $stmt->close();

            createNotification($request['user_id'], $requestId, 'request_approved', 'Request Approved', 'Your maintenance request has been approved.', $conn);

            $managerStmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'Maintenance_Manager' LIMIT 1");
            $managerStmt->execute();
            $managerResult = $managerStmt->get_result();
            if ($managerResult->num_rows > 0) {
                $manager = $managerResult->fetch_assoc();
                createNotification($manager['user_id'], $requestId, 'new_approved_request', 'New Approved Request', 'A new maintenance request has been approved and requires assignment.', $conn);
            }
            $managerStmt->close();

            logSystemAction('Approve Request', 'maintenance_requests', $requestId, 'Request approved', $conn);
        }

        $conn->commit();
        closeDBConnection($conn);
        header('Location: ' . BASE_URL . '/approvals/pending.php?success=' . ($isCompletionReview ? 'completion_approved' : 'request_approved'));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        if (!empty($conn->error)) {
            error_log('approve.php: ' . $conn->error);
        }
        $error = 'Failed to approve request. Please try again.';
    }
}

closeDBConnection($conn);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container approvals-shell">
    <section class="approvals-hero approvals-hero--compact">
        <div class="approvals-hero-inner">
            <span class="approvals-eyebrow"><i class="fas fa-check-circle"></i> Confirm approval</span>
            <h1><?php echo $isCompletionReview ? 'Approve Completion' : 'Approve Request'; ?></h1>
            <p class="approvals-lead"><?php echo $isCompletionReview ? 'Review the completed maintenance task before it is forwarded to the maintenance manager.' : 'Optional feedback is shared with the requester. The maintenance manager will be notified next.'; ?></p>
        </div>
        <a href="<?php echo BASE_URL; ?>/approvals/pending.php" class="btn btn-secondary approvals-hero__back">
            <i class="fas fa-arrow-left"></i> Pending list
        </a>
    </section>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <div class="card card--page form-card">
        <div class="form-card__head">
            <h2 class="form-card__title"><i class="fas fa-comment-dots"></i> Feedback</h2>
            <p class="form-card__hint">Add context for the requester or leave blank.</p>
        </div>
        <div class="form-card__body">
        <form method="POST" action="<?php echo BASE_URL; ?>/approvals/approve.php?id=<?php echo (int) $requestId; ?>" class="form">
            <div class="form-group">
                <label for="feedback">
                    <i class="fas fa-comment"></i> Feedback (Optional)
                </label>
                <textarea id="feedback" name="feedback" rows="4" placeholder="Add any comments or notes about this approval"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Approve Request
                </button>
                <a href="<?php echo BASE_URL; ?>/approvals/pending.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
