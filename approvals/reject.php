<?php
$pageTitle = 'Reject Request';
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

    if (empty($feedback)) {
        $error = 'Please provide a reason for rejection.';
    } else {
        $approverId = getCurrentUserId();
        $roleLabel = str_replace('_', ' ', $currentUserRole);

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("INSERT INTO approvals (request_id, approver_id, approver_role, decision, feedback) VALUES (?, ?, ?, 'Rejected', ?)");
            $stmt->bind_param("iiss", $requestId, $approverId, $currentUserRole, $feedback);
            $stmt->execute();
            $stmt->close();

            if ($isCompletionReview) {
                updateRequestStatus($requestId, 'In_Progress', 'Completion rejected by ' . $roleLabel . ': ' . $feedback, $conn);

                $assignmentStmt = $conn->prepare("SELECT assigned_to FROM task_assignments WHERE request_id = ? ORDER BY assignment_id DESC LIMIT 1");
                $assignmentStmt->bind_param("i", $requestId);
                $assignmentStmt->execute();
                $assignmentResult = $assignmentStmt->get_result();
                if ($assignmentResult->num_rows > 0) {
                    $assignment = $assignmentResult->fetch_assoc();
                    if (!empty($assignment['assigned_to'])) {
                        createNotification((int) $assignment['assigned_to'], $requestId, 'completion_rejected', 'Completion Rejected', 'A completed task has been rejected and reopened for further work. Reason: ' . $feedback, $conn);
                    }
                }
                $assignmentStmt->close();

                createNotification($request['user_id'], $requestId, 'completion_rejected', 'Completion Rejected', 'The completion review was rejected. Reason: ' . $feedback, $conn);
                logSystemAction('Reject Completion', 'maintenance_requests', $requestId, 'Completion rejected by ' . $roleLabel, $conn);
                $redirect = 'completion_rejected';
            } else {
                updateRequestStatus($requestId, 'Rejected', 'Request rejected by ' . $roleLabel . ': ' . $feedback, $conn);
                createNotification($request['user_id'], $requestId, 'request_rejected', 'Request Rejected', 'Your maintenance request has been rejected. Reason: ' . $feedback, $conn);
                logSystemAction('Reject Request', 'maintenance_requests', $requestId, 'Request rejected', $conn);
                $redirect = 'request_rejected';
            }

            $conn->commit();
            closeDBConnection($conn);
            header('Location: ' . BASE_URL . '/approvals/pending.php?success=' . $redirect);
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            if (!empty($conn->error)) {
                error_log('reject.php: ' . $conn->error);
            }
            $error = 'Failed to reject request. Please try again.';
        }
    }
}

closeDBConnection($conn);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container approvals-shell approvals-shell--reject">
    <section class="approvals-hero approvals-hero--compact approvals-hero--reject">
        <div class="approvals-hero-inner">
            <span class="approvals-eyebrow"><i class="fas fa-times-circle"></i> Rejection</span>
            <h1>Reject Request</h1>
            <p class="approvals-lead">A clear reason helps the requester understand what to do next.</p>
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
            <h2 class="form-card__title"><i class="fas fa-comment-alt"></i> Reason for rejection</h2>
            <p class="form-card__hint">Required &mdash; this message is sent to the requester.</p>
        </div>
        <div class="form-card__body">
        <form method="POST" action="<?php echo BASE_URL; ?>/approvals/reject.php?id=<?php echo (int) $requestId; ?>" class="form">
            <div class="form-group">
                <label for="feedback">
                    <i class="fas fa-comment"></i> Reason for Rejection *
                </label>
                <textarea id="feedback" name="feedback" rows="4" required placeholder="Please provide a reason for rejecting this request"></textarea>
                <small>This feedback will be sent to the requester.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-times"></i> Reject Request
                </button>
                <a href="<?php echo BASE_URL; ?>/approvals/pending.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
            </div>
        </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
