<?php
$pageTitle = 'Pending Approvals';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAnyRole(['Proctor', 'Office_Head']);

$conn = getDBConnection();
$currentUserRole = getCurrentUserRole();
$currentUserId = getCurrentUserId();

if ($currentUserRole === 'Proctor') {
    $pendingStmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, u.full_name, u.email
        FROM maintenance_requests mr
        JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
        JOIN locations l ON mr.location_id = l.location_id
        JOIN users u ON mr.user_id = u.user_id
        WHERE mr.status = 'Pending' AND u.role = 'Student'
        AND EXISTS (
            SELECT 1 FROM proctor_locations pl
            WHERE pl.proctor_user_id = ? AND TRIM(pl.location_name) = TRIM(l.location_name)
        )
        ORDER BY mr.submitted_at ASC");
    $pendingStmt->bind_param("i", $currentUserId);

    $completionStmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, u.full_name, u.email
        FROM maintenance_requests mr
        JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
        JOIN locations l ON mr.location_id = l.location_id
        JOIN users u ON mr.user_id = u.user_id
        WHERE mr.status = 'Completed' AND u.role = 'Student'
        AND EXISTS (
            SELECT 1 FROM proctor_locations pl
            WHERE pl.proctor_user_id = ? AND TRIM(pl.location_name) = TRIM(l.location_name)
        )
        AND NOT EXISTS (
            SELECT 1 FROM approvals a
            WHERE a.request_id = mr.request_id
              AND a.decision = 'Approved'
              AND a.feedback LIKE 'COMPLETION_APPROVAL|%'
        )
        ORDER BY mr.completed_at DESC");
    $completionStmt->bind_param("i", $currentUserId);
} else {
    $pendingStmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, u.full_name, u.email
        FROM maintenance_requests mr
        JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
        JOIN locations l ON mr.location_id = l.location_id
        JOIN users u ON mr.user_id = u.user_id
        WHERE mr.status = 'Pending' AND u.role = 'Staff'
        AND mr.assigned_office_head_id = ?
        ORDER BY mr.submitted_at ASC");
    $pendingStmt->bind_param("i", $currentUserId);

    $completionStmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, u.full_name, u.email
        FROM maintenance_requests mr
        JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
        JOIN locations l ON mr.location_id = l.location_id
        JOIN users u ON mr.user_id = u.user_id
        WHERE mr.status = 'Completed' AND u.role = 'Staff'
        AND mr.assigned_office_head_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM approvals a
            WHERE a.request_id = mr.request_id
              AND a.decision = 'Approved'
              AND a.feedback LIKE 'COMPLETION_APPROVAL|%'
        )
        ORDER BY mr.completed_at DESC");
    $completionStmt->bind_param("i", $currentUserId);
}

$pendingStmt->execute();
$requests = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pendingStmt->close();

$completionStmt->execute();
$completionRequests = $completionStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$completionStmt->close();

closeDBConnection($conn);

$pendingCount = count($requests);
$completionCount = count($completionRequests);
$queueLabel = $currentUserRole === 'Proctor'
    ? 'Student requests for your assigned locations appear here. Completed tasks also appear below for your review.'
    : 'Staff requests that selected you as office head appear here. Completed tasks also appear below for your review.';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container approvals-shell">
    <section class="approvals-hero" aria-labelledby="pending-approvals-title">
        <div class="approvals-hero-inner">
            <span class="approvals-eyebrow"><i class="fas fa-clipboard-check"></i> Approval queue</span>
            <h1 id="pending-approvals-title">Pending Approvals</h1>
            <p class="approvals-lead"><?php echo htmlspecialchars($queueLabel); ?></p>
            <div class="approvals-hero-meta">
                <span class="approvals-chip approvals-chip--accent">
                    <i class="fas fa-inbox"></i>
                    <?php echo $pendingCount; ?> <?php echo $pendingCount === 1 ? 'request' : 'requests'; ?> waiting
                </span>
                <span class="approvals-chip">
                    <i class="fas fa-user-shield"></i>
                    <?php echo htmlspecialchars(str_replace('_', ' ', $currentUserRole)); ?>
                </span>
            </div>
        </div>
    </section>

    <div class="card card--page table-card">
        <?php if (!empty($requests)): ?>
        <div class="table-card__head">
            <h2 class="table-card__title">Review list</h2>
            <span class="table-card__hint">Use View for details &middot; Approve or Reject to decide</span>
        </div>
        <div class="table-container table-container--rounded">
            <table class="data-table data-table--approvals">
                <thead>
                    <tr>
                        <th scope="col">Request #</th>
                        <th scope="col">Title</th>
                        <th scope="col">Type</th>
                        <th scope="col">Location</th>
                        <th scope="col">Submitted By</th>
                        <th scope="col">Priority</th>
                        <th scope="col">Submitted</th>
                        <th scope="col" class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                    <tr>
                        <td class="cell-mono cell-nowrap"><?php echo htmlspecialchars($request['request_number']); ?></td>
                        <td class="cell-title"><?php echo htmlspecialchars($request['title']); ?></td>
                        <td><?php echo htmlspecialchars($request['type_name']); ?></td>
                        <td><?php echo htmlspecialchars($request['location_name']); ?></td>
                        <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                        <td><?php echo getPriorityBadge($request['priority']); ?></td>
                        <td class="cell-muted cell-nowrap"><?php echo formatDate($request['submitted_at'], 'M d, Y H:i'); ?></td>
                        <td class="col-actions">
                            <div class="action-btn-group" role="group" aria-label="Actions for <?php echo htmlspecialchars($request['request_number']); ?>">
                                <a href="<?php echo BASE_URL; ?>/requests/view.php?id=<?php echo (int) $request['request_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="<?php echo BASE_URL; ?>/approvals/approve.php?id=<?php echo (int) $request['request_id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                                <a href="<?php echo BASE_URL; ?>/approvals/reject.php?id=<?php echo (int) $request['request_id']; ?>" class="btn btn-sm btn-danger">
                                    <i class="fas fa-times"></i> Reject
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state empty-state--rich">
            <div class="empty-state__icon-wrap">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <h2 class="empty-state__title">You&apos;re all caught up</h2>
            <p class="empty-state__text">There are no maintenance requests waiting for your approval right now. New items will show up here automatically.</p>
            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($completionRequests)): ?>
    <div class="card card--page table-card">
        <div class="table-card__head">
            <h2 class="table-card__title">Completion Review</h2>
            <span class="table-card__hint">Tasks completed by the maintenance team that need your approval.</span>
        </div>
        <div class="table-container table-container--rounded">
            <table class="data-table data-table--approvals">
                <thead>
                    <tr>
                        <th scope="col">Request #</th>
                        <th scope="col">Title</th>
                        <th scope="col">Type</th>
                        <th scope="col">Location</th>
                        <th scope="col">Submitted By</th>
                        <th scope="col">Priority</th>
                        <th scope="col">Completed</th>
                        <th scope="col" class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completionRequests as $request): ?>
                    <tr>
                        <td class="cell-mono cell-nowrap"><?php echo htmlspecialchars($request['request_number']); ?></td>
                        <td class="cell-title"><?php echo htmlspecialchars($request['title']); ?></td>
                        <td><?php echo htmlspecialchars($request['type_name']); ?></td>
                        <td><?php echo htmlspecialchars($request['location_name']); ?></td>
                        <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                        <td><?php echo getPriorityBadge($request['priority']); ?></td>
                        <td class="cell-muted cell-nowrap"><?php echo formatDate($request['completed_at'], 'M d, Y H:i'); ?></td>
                        <td class="col-actions">
                            <div class="action-btn-group" role="group" aria-label="Actions for <?php echo htmlspecialchars($request['request_number']); ?>">
                                <a href="<?php echo BASE_URL; ?>/requests/view.php?id=<?php echo (int) $request['request_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="<?php echo BASE_URL; ?>/approvals/approve.php?id=<?php echo (int) $request['request_id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                                <a href="<?php echo BASE_URL; ?>/approvals/reject.php?id=<?php echo (int) $request['request_id']; ?>" class="btn btn-sm btn-danger">
                                    <i class="fas fa-times"></i> Reject
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
