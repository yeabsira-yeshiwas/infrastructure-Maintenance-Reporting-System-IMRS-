<?php
$pageTitle = 'My Requests';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

requireAnyRole(['Student', 'Staff', 'General_User']);

$conn = getDBConnection();
$userId = getCurrentUserId();

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get total count
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM maintenance_requests WHERE user_id = ?");
$countStmt->bind_param("i", $userId);
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($totalRecords / $limit);

// Get requests
$stmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name
    FROM maintenance_requests mr
    JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
    JOIN locations l ON mr.location_id = l.location_id
    WHERE mr.user_id = ?
    ORDER BY mr.submitted_at DESC
    LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $userId, $limit, $offset);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-list"></i> My Maintenance Requests</h1>
        <a href="<?php echo BASE_URL; ?>/requests/create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Request
        </a>
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
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($requests)): ?>
                        <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['request_number']); ?></td>
                            <td><?php echo htmlspecialchars($request['title']); ?></td>
                            <td><?php echo htmlspecialchars($request['type_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['location_name']); ?></td>
                            <td><?php echo getPriorityBadge($request['priority']); ?></td>
                            <td><?php echo getStatusBadge($request['status']); ?></td>
                            <td><?php echo formatDate($request['submitted_at'], 'M d, Y'); ?></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/requests/view.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No requests found. <a href="<?php echo BASE_URL; ?>/requests/create.php">Create your first request</a></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>" class="btn btn-sm">Previous</a>
            <?php endif; ?>
            
            <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>" class="btn btn-sm">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

