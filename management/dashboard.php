<?php
$pageTitle = 'Maintenance Manager Dashboard';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAnyRole(['Maintenance_Manager', 'Admin']);

$conn = getDBConnection();
$currentUserId = getCurrentUserId();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action'] ?? '');
    $requestId = intval($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        $error = 'Invalid request selected.';
    } else {
        $stmt = $conn->prepare("SELECT mr.*, u.full_name, u.role AS requester_role, u.user_id AS requester_user_id
            FROM maintenance_requests mr
            JOIN users u ON mr.user_id = u.user_id
            WHERE mr.request_id = ?");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request) {
            $error = 'Request could not be found.';
        } elseif ($action === 'approve_general') {
            if ($request['requester_role'] !== 'General_User' || $request['status'] !== 'Pending') {
                $error = 'Only pending general user requests can be approved here.';
            } else {
                $conn->begin_transaction();
                try {
                    $updateStmt = $conn->prepare("UPDATE maintenance_requests SET status = 'Approved', approved_at = NOW() WHERE request_id = ?");
                    $updateStmt->bind_param("i", $requestId);
                    $updateStmt->execute();
                    $updateStmt->close();

                    $message = 'General user request approved by Maintenance Manager.';
                    $statusStmt = $conn->prepare("INSERT INTO status_updates (request_id, updated_by, old_status, new_status, update_message) VALUES (?, ?, ?, 'Approved', ?)");
                    $statusStmt->bind_param("iiss", $requestId, $currentUserId, $request['status'], $message);
                    $statusStmt->execute();
                    $statusStmt->close();

                    createNotification($request['requester_user_id'], $requestId, 'request_approved', 'Request Approved', 'Your maintenance request has been approved and moved to assignment.', $conn);
                    logSystemAction('Approve General User Request', 'maintenance_requests', $requestId, $message, $conn);
                    $conn->commit();

                    header('Location: ' . BASE_URL . '/management/dashboard.php?success=request_approved');
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Failed to approve the request.';
                }
            }
        } elseif ($action === 'reject_general') {
            $reason = sanitizeInput($_POST['rejection_reason'] ?? '');

            if ($request['requester_role'] !== 'General_User' || $request['status'] !== 'Pending') {
                $error = 'Only pending general user requests can be rejected here.';
            } elseif ($reason === '') {
                $error = 'A rejection reason is required.';
            } else {
                $conn->begin_transaction();
                try {
                    $updateStmt = $conn->prepare("UPDATE maintenance_requests SET status = 'Rejected' WHERE request_id = ?");
                    $updateStmt->bind_param("i", $requestId);
                    $updateStmt->execute();
                    $updateStmt->close();

                    $message = 'General user request rejected by Maintenance Manager: ' . $reason;
                    $statusStmt = $conn->prepare("INSERT INTO status_updates (request_id, updated_by, old_status, new_status, update_message) VALUES (?, ?, ?, 'Rejected', ?)");
                    $statusStmt->bind_param("iiss", $requestId, $currentUserId, $request['status'], $message);
                    $statusStmt->execute();
                    $statusStmt->close();

                    createNotification($request['requester_user_id'], $requestId, 'request_rejected', 'Request Rejected', 'Your maintenance request was rejected. Reason: ' . $reason, $conn);
                    logSystemAction('Reject General User Request', 'maintenance_requests', $requestId, $message, $conn);
                    $conn->commit();

                    header('Location: ' . BASE_URL . '/management/dashboard.php?success=request_rejected');
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Failed to reject the request.';
                }
            }
        } elseif ($action === 'approve_completion') {
            if ($request['status'] !== 'Completed') {
                $error = 'Only completed tasks can be closed.';
            } else {
                $conn->begin_transaction();
                try {
                    $updateStmt = $conn->prepare("UPDATE maintenance_requests SET status = 'Closed', closed_at = NOW() WHERE request_id = ?");
                    $updateStmt->bind_param("i", $requestId);
                    $updateStmt->execute();
                    $updateStmt->close();

                    $message = 'Work completion approved by Maintenance Manager.';
                    $statusStmt = $conn->prepare("INSERT INTO status_updates (request_id, updated_by, old_status, new_status, update_message) VALUES (?, ?, ?, 'Closed', ?)");
                    $statusStmt->bind_param("iiss", $requestId, $currentUserId, $request['status'], $message);
                    $statusStmt->execute();
                    $statusStmt->close();

                    createNotification($request['requester_user_id'], $requestId, 'request_closed', 'Work Order Closed', 'Your maintenance request has been reviewed and closed by the Maintenance Manager.', $conn);
                    logSystemAction('Approve Task Completion', 'maintenance_requests', $requestId, $message, $conn);
                    $conn->commit();

                    header('Location: ' . BASE_URL . '/management/dashboard.php?success=completion_approved');
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Failed to close the completed work order.';
                }
            }
        } elseif ($action === 'reopen_task') {
            $reason = sanitizeInput($_POST['reopen_reason'] ?? '');

            if ($request['status'] !== 'Completed') {
                $error = 'Only completed tasks can be reopened.';
            } elseif ($reason === '') {
                $error = 'A reopening reason is required.';
            } else {
                $conn->begin_transaction();
                try {
                    $updateStmt = $conn->prepare("UPDATE maintenance_requests SET status = 'In_Progress', completed_at = NULL WHERE request_id = ?");
                    $updateStmt->bind_param("i", $requestId);
                    $updateStmt->execute();
                    $updateStmt->close();

                    $message = 'Task reopened by Maintenance Manager: ' . $reason;
                    $statusStmt = $conn->prepare("INSERT INTO status_updates (request_id, updated_by, old_status, new_status, update_message) VALUES (?, ?, ?, 'In_Progress', ?)");
                    $statusStmt->bind_param("iiss", $requestId, $currentUserId, $request['status'], $message);
                    $statusStmt->execute();
                    $statusStmt->close();

                    $assigneeStmt = $conn->prepare("SELECT assigned_to FROM task_assignments WHERE request_id = ? ORDER BY assignment_id DESC LIMIT 1");
                    $assigneeStmt->bind_param("i", $requestId);
                    $assigneeStmt->execute();
                    $assignee = $assigneeStmt->get_result()->fetch_assoc();
                    $assigneeStmt->close();

                    if ($assignee && !empty($assignee['assigned_to'])) {
                        createNotification((int) $assignee['assigned_to'], $requestId, 'task_reopened', 'Task Reopened', 'A task assigned to you has been reopened by the Maintenance Manager.', $conn);
                    }
                    createNotification($request['requester_user_id'], $requestId, 'task_reopened', 'Task Reopened', 'Your maintenance request has been reopened for additional work.', $conn);
                    logSystemAction('Reopen Task', 'maintenance_requests', $requestId, $message, $conn);
                    $conn->commit();

                    header('Location: ' . BASE_URL . '/management/dashboard.php?success=task_reopened');
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Failed to reopen the task.';
                }
            }
        }
    }
}

$statsStmt = $conn->query("SELECT
    COUNT(*) AS total_requests,
    SUM(CASE WHEN mr.status = 'Pending' AND u.role = 'General_User' THEN 1 ELSE 0 END) AS pending_general,
    SUM(CASE WHEN mr.status = 'Assigned' THEN 1 ELSE 0 END) AS assigned_tasks,
    SUM(CASE WHEN mr.status = 'In_Progress' THEN 1 ELSE 0 END) AS in_progress_tasks,
    SUM(CASE WHEN mr.status = 'Completed' THEN 1 ELSE 0 END) AS completed_tasks,
    SUM(CASE WHEN mr.status = 'Closed' THEN 1 ELSE 0 END) AS closed_tasks
    FROM maintenance_requests mr
    LEFT JOIN users u ON mr.user_id = u.user_id");
$stats = $statsStmt->fetch_assoc();
$statsStmt->close();

$userCountsStmt = $conn->query("SELECT role, COUNT(*) AS count FROM users GROUP BY role");
$userCounts = [];
while ($row = $userCountsStmt->fetch_assoc()) {
    $userCounts[$row['role']] = $row['count'];
}
$userCountsStmt->close();

$pendingStmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, l.building, u.full_name
    FROM maintenance_requests mr
    JOIN users u ON mr.user_id = u.user_id
    JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
    JOIN locations l ON mr.location_id = l.location_id
    WHERE mr.status = 'Pending' AND u.role = 'General_User'
    ORDER BY mr.submitted_at ASC");
$pendingStmt->execute();
$pendingGeneralRequests = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pendingStmt->close();

$allRequestsStmt = $conn->prepare("SELECT mr.request_id, mr.request_number, mr.priority, mr.status, mr.submitted_at,
    COALESCE(u.full_name, mr.submitter_name) AS full_name,
    COALESCE(u.role, 'General_User') AS requester_role,
    it.type_name,
    assigned.full_name AS assigned_to_name
    FROM maintenance_requests mr
    LEFT JOIN users u ON mr.user_id = u.user_id
    JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
    LEFT JOIN task_assignments latest_assignment ON latest_assignment.assignment_id = (
        SELECT ta.assignment_id FROM task_assignments ta
        WHERE ta.request_id = mr.request_id
        ORDER BY ta.assignment_id DESC LIMIT 1
    )
    LEFT JOIN users assigned ON assigned.user_id = latest_assignment.assigned_to
    WHERE mr.status IN ('Approved', 'Assigned', 'In_Progress', 'Completed', 'Closed')
    ORDER BY mr.submitted_at DESC
    LIMIT 12");
$allRequestsStmt->execute();
$allRequests = $allRequestsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$allRequestsStmt->close();

$activeTasksStmt = $conn->prepare("SELECT mr.request_id, mr.request_number, mr.priority, mr.status,
    ta.scheduled_date, assigned.full_name AS assigned_to_name
    FROM maintenance_requests mr
    JOIN task_assignments ta ON ta.assignment_id = (
        SELECT ta2.assignment_id FROM task_assignments ta2
        WHERE ta2.request_id = mr.request_id
        ORDER BY ta2.assignment_id DESC LIMIT 1
    )
    JOIN users assigned ON assigned.user_id = ta.assigned_to
    WHERE mr.status IN ('Assigned', 'In_Progress')
    ORDER BY COALESCE(ta.scheduled_date, CURDATE()) ASC, mr.priority DESC");
$activeTasksStmt->execute();
$activeTasks = $activeTasksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$activeTasksStmt->close();

$completedTasksStmt = $conn->prepare("SELECT mr.request_id, mr.request_number, mr.priority, mr.completed_at,
    assigned.full_name AS assigned_to_name, u.full_name AS requester_name
    FROM maintenance_requests mr
    JOIN users u ON mr.user_id = u.user_id
    LEFT JOIN task_assignments ta ON ta.assignment_id = (
        SELECT ta2.assignment_id FROM task_assignments ta2
        WHERE ta2.request_id = mr.request_id
        ORDER BY ta2.assignment_id DESC LIMIT 1
    )
    LEFT JOIN users assigned ON assigned.user_id = ta.assigned_to
    WHERE mr.status = 'Completed'
    ORDER BY mr.completed_at DESC");
$completedTasksStmt->execute();
$completionReviewTasks = $completedTasksStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$completedTasksStmt->close();

closeDBConnection($conn);

function getRequestSourceLabel(string $role): string {
    if ($role === 'Student') {
        return 'Student via Proctor';
    }
    if ($role === 'Staff') {
        return 'Staff via Office Head';
    }
    if ($role === 'General_User') {
        return 'General User Direct';
    }
    return str_replace('_', ' ', $role);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container management-shell">
    <section class="management-hero">
        <h1><i class="fas fa-shield-alt"></i> Maintenance Manager Dashboard</h1>
        <p>Control approvals, assignments, user access, and team workload from one administrative workspace.</p>
        <div class="management-hero-meta">
            <span class="management-chip"><i class="fas fa-users-cog"></i> <?php echo htmlspecialchars(str_replace('_', ' ', getCurrentUserRole())); ?></span>
            <span class="management-chip"><i class="fas fa-inbox"></i> <?php echo (int) ($stats['pending_general'] ?? 0); ?> pending direct approvals</span>
            <span class="management-chip"><i class="fas fa-clipboard-check"></i> <?php echo (int) ($stats['completed_tasks'] ?? 0); ?> completions to review</span>
        </div>
    </section>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <section class="management-grid">
        <div class="summary-card">
            <h3>Total Requests</h3>
            <strong><?php echo (int) ($stats['total_requests'] ?? 0); ?></strong>
            <span>All requests in the maintenance system.</span>
        </div>
        <div class="summary-card">
            <h3>Pending Approvals</h3>
            <strong><?php echo (int) ($stats['pending_general'] ?? 0); ?></strong>
            <span>General-user requests waiting for manager review.</span>
        </div>
        <div class="summary-card">
            <h3>Assigned Tasks</h3>
            <strong><?php echo (int) ($stats['assigned_tasks'] ?? 0); ?></strong>
            <span>Approved requests already assigned to the team.</span>
        </div>
        <div class="summary-card">
            <h3>In Progress</h3>
            <strong><?php echo (int) ($stats['in_progress_tasks'] ?? 0); ?></strong>
            <span>Work currently under execution.</span>
        </div>
        <div class="summary-card">
            <h3>Completed</h3>
            <strong><?php echo (int) ($stats['completed_tasks'] ?? 0); ?></strong>
            <span>Tasks awaiting manager closure.</span>
        </div>
    </section>

    <section id="pending-approvals" class="panel">
        <div class="panel-header">
            <div>
                <h2>Pending Approvals</h2>
                <p>General-user requests are reviewed directly here by the manager.</p>
            </div>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Issue</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pendingGeneralRequests)): ?>
                        <?php foreach ($pendingGeneralRequests as $request): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($request['request_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['location_name'] . ' / ' . $request['building']); ?></td>
                            <td><?php echo htmlspecialchars($request['type_name']); ?></td>
                            <td><?php echo formatDate($request['submitted_at'], 'M d, Y'); ?></td>
                            <td><?php echo getStatusBadge($request['status']); ?></td>
                            <td>
                                <div class="table-action-group">
                                    <a href="<?php echo BASE_URL; ?>/requests/view.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <form method="POST" action="<?php echo htmlspecialchars(BASE_URL . '/management/dashboard.php'); ?>">
                                        <input type="hidden" name="action" value="approve_general">
                                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" action="<?php echo htmlspecialchars(BASE_URL . '/management/dashboard.php'); ?>" onsubmit="const reason = prompt('Enter rejection reason for the requester:'); if (!reason) { return false; } this.querySelector('input[name=rejection_reason]').value = reason; return true;">
                                        <input type="hidden" name="action" value="reject_general">
                                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                        <input type="hidden" name="rejection_reason" value="">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No general-user approvals are waiting.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>All Requests</h2>
                <p>Approved requests from student, staff, and direct general-user channels.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/management/requests.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> Full Queue
            </a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Requester</th>
                        <th>Source</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allRequests as $request): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($request['request_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                        <td><?php echo htmlspecialchars(getRequestSourceLabel($request['requester_role'])); ?></td>
                        <td><?php echo htmlspecialchars($request['type_name']); ?></td>
                        <td><?php echo getPriorityBadge($request['priority']); ?></td>
                        <td><?php echo getStatusBadge($request['status']); ?></td>
                        <td><?php echo htmlspecialchars($request['assigned_to_name'] ?? 'Unassigned'); ?></td>
                        <td>
                            <div class="table-action-group">
                                <a href="<?php echo BASE_URL; ?>/requests/view.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if (in_array($request['status'], ['Approved', 'Assigned', 'In_Progress'], true)): ?>
                                <a href="<?php echo BASE_URL; ?>/management/assign.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-user-check"></i> <?php echo $request['assigned_to_name'] ? 'Reassign' : 'Assign'; ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="management-grid-2">
        <section id="active-tasks" class="panel">
            <div class="panel-header">
                <div>
                    <h2>Active Tasks</h2>
                    <p>Monitor assigned and in-progress work orders.</p>
                </div>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Task ID</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Deadline</th>
                            <th>Progress</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($activeTasks)): ?>
                            <?php foreach ($activeTasks as $task): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($task['request_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($task['assigned_to_name']); ?></td>
                                <td><?php echo getStatusBadge($task['status']); ?></td>
                                <td><?php echo $task['scheduled_date'] ? formatDate($task['scheduled_date'], 'Y-m-d') : 'Not set'; ?></td>
                                <td><?php echo $task['status'] === 'Assigned' ? 'Waiting to start' : 'Under execution'; ?></td>
                                <td>
                                    <div class="table-action-group">
                                        <a href="<?php echo BASE_URL; ?>/requests/view.php?id=<?php echo $task['request_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="<?php echo BASE_URL; ?>/management/assign.php?id=<?php echo $task['request_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-random"></i> Reassign
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No active tasks at the moment.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h3>User Management</h3>
                    <p>Create and maintain operational accounts.</p>
                </div>
            </div>
            <div class="panel-body detail-list">
                <div class="detail-row">
                    <div class="detail-row-label">Proctors</div>
                    <div class="detail-row-value"><?php echo (int) ($userCounts['Proctor'] ?? 0); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-row-label">Office Heads</div>
                    <div class="detail-row-value"><?php echo (int) ($userCounts['Office_Head'] ?? 0); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-row-label">Maintenance Team</div>
                    <div class="detail-row-value"><?php echo (int) ($userCounts['Maintenance_Team'] ?? 0); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-row-label">General Users</div>
                    <div class="detail-row-value"><?php echo (int) ($userCounts['General_User'] ?? 0); ?></div>
                </div>
                <div class="table-action-group">
                    <a href="<?php echo BASE_URL; ?>/management/create_user.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register User
                    </a>
                    <a href="<?php echo BASE_URL; ?>/management/users.php" class="btn btn-secondary">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                </div>
            </div>
        </aside>
    </div>

    <section id="completion-review" class="panel">
        <div class="panel-header">
            <div>
                <h2>Completion Approval</h2>
                <p>Approve finished work or reopen tasks that require correction.</p>
            </div>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Task ID</th>
                        <th>Requester</th>
                        <th>Assigned To</th>
                        <th>Completed At</th>
                        <th>Priority</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($completionReviewTasks)): ?>
                        <?php foreach ($completionReviewTasks as $task): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($task['request_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($task['requester_name']); ?></td>
                            <td><?php echo htmlspecialchars($task['assigned_to_name'] ?? 'Unassigned'); ?></td>
                            <td><?php echo formatDate($task['completed_at'], 'M d, Y H:i'); ?></td>
                            <td><?php echo getPriorityBadge($task['priority']); ?></td>
                            <td>
                                <div class="table-action-group">
                                    <a href="<?php echo BASE_URL; ?>/requests/view.php?id=<?php echo $task['request_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <form method="POST" action="<?php echo htmlspecialchars(BASE_URL . '/management/dashboard.php'); ?>">
                                        <input type="hidden" name="action" value="approve_completion">
                                        <input type="hidden" name="request_id" value="<?php echo $task['request_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fas fa-check-double"></i> Close
                                        </button>
                                    </form>
                                    <form method="POST" action="<?php echo htmlspecialchars(BASE_URL . '/management/dashboard.php'); ?>" onsubmit="const reason = prompt('Enter reason for reopening this task:'); if (!reason) { return false; } this.querySelector('input[name=reopen_reason]').value = reason; return true;">
                                        <input type="hidden" name="action" value="reopen_task">
                                        <input type="hidden" name="request_id" value="<?php echo $task['request_id']; ?>">
                                        <input type="hidden" name="reopen_reason" value="">
                                        <button type="submit" class="btn btn-sm btn-warning">
                                            <i class="fas fa-undo"></i> Reopen
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No completed tasks are waiting for review.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
