<?php
$pageTitle = 'My Tasks';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('Maintenance_Team');

$conn = getDBConnection();
$userId = getCurrentUserId();

// Get assigned tasks
$sql = "SELECT mr.*, it.type_name, l.location_name, ta.scheduled_date, ta.notes as assignment_notes
    FROM task_assignments ta
    JOIN maintenance_requests mr ON ta.request_id = mr.request_id
    JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
    JOIN locations l ON mr.location_id = l.location_id
    WHERE ta.assigned_to = ?";
$hasIsActive = columnExists('task_assignments', 'is_active');
if ($hasIsActive) {
    $sql .= " AND ta.is_active = 1";
}
$sql .= " ORDER BY ta.assigned_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-list"></i> My Tasks</h1>
    </div>
    
    <div class="card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Request #</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Scheduled Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tasks)): ?>
                        <?php foreach ($tasks as $task): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($task['request_number']); ?></td>
                            <td><?php echo htmlspecialchars($task['title']); ?></td>
                            <td><?php echo htmlspecialchars($task['type_name']); ?></td>
                            <td><?php echo htmlspecialchars($task['location_name']); ?></td>
                            <td><?php echo getPriorityBadge($task['priority']); ?></td>
                            <td><?php echo getStatusBadge($task['status']); ?></td>
                            <td><?php echo $task['scheduled_date'] ? formatDate($task['scheduled_date'], 'Y-m-d') : 'Not scheduled'; ?></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/requests/view.php?id=<?php echo $task['request_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if (in_array($task['status'], ['Assigned', 'In_Progress'])): ?>
                                <a href="<?php echo BASE_URL; ?>/team/update_status.php?id=<?php echo $task['request_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Update Status
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No tasks assigned</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



