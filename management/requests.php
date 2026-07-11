<?php
$pageTitle = 'Manage Requests';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

requireAnyRole(['Maintenance_Manager', 'Admin']);

$conn = getDBConnection();
$statusFilter = $_GET['status'] ?? 'all';

$whereClause = "mr.status IN ('Approved', 'Assigned', 'In_Progress', 'Completed')";
if ($statusFilter !== 'all') {
    $whereClause = "mr.status = '" . $conn->real_escape_string($statusFilter) . "'";
}

$stmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name,
    COALESCE(u.full_name, mr.submitter_name) as full_name
    FROM maintenance_requests mr
    JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
    JOIN locations l ON mr.location_id = l.location_id
    LEFT JOIN users u ON mr.user_id = u.user_id
    WHERE $whereClause
    ORDER BY mr.priority DESC, mr.approved_at DESC");
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);

$requestCounts = [
    'Approved' => 0,
    'Assigned' => 0,
    'In_Progress' => 0,
    'Completed' => 0
];

foreach ($requests as $request) {
    if (isset($requestCounts[$request['status']])) {
        $requestCounts[$request['status']]++;
    }
}
?>

<div class="page-container management-shell">
    <section class="management-hero">
        <h1><i class="fas fa-tasks"></i> Manage Requests</h1>
        <p>Track approved work orders, move them into assignment, and maintain visibility on the live operational pipeline.</p>
        <div class="management-hero-meta">
            <span class="management-chip"><i class="fas fa-list-check"></i> <?php echo count($requests); ?> visible requests</span>
            <span class="management-chip"><i class="fas fa-filter"></i> Filter: <?php echo htmlspecialchars(str_replace('_', ' ', $statusFilter)); ?></span>
        </div>
    </section>

    <section class="management-grid">
        <div class="summary-card">
            <h3>Approved</h3>
            <strong><?php echo $requestCounts['Approved']; ?></strong>
            <span>Ready for assignment.</span>
        </div>
        <div class="summary-card">
            <h3>Assigned</h3>
            <strong><?php echo $requestCounts['Assigned']; ?></strong>
            <span>Already pushed to the maintenance team.</span>
        </div>
        <div class="summary-card">
            <h3>In Progress</h3>
            <strong><?php echo $requestCounts['In_Progress']; ?></strong>
            <span>Currently under execution.</span>
        </div>
        <div class="summary-card">
            <h3>Completed</h3>
            <strong><?php echo $requestCounts['Completed']; ?></strong>
            <span>Finished and closed work.</span>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Request Pipeline</h2>
                <p>Filter the queue by execution stage and open work orders for detail or assignment.</p>
            </div>
            <div class="filter-bar">
                <a href="?status=all" class="btn btn-sm <?php echo $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
                <a href="?status=Approved" class="btn btn-sm <?php echo $statusFilter === 'Approved' ? 'btn-primary' : 'btn-secondary'; ?>">Approved</a>
                <a href="?status=Assigned" class="btn btn-sm <?php echo $statusFilter === 'Assigned' ? 'btn-primary' : 'btn-secondary'; ?>">Assigned</a>
                <a href="?status=In_Progress" class="btn btn-sm <?php echo $statusFilter === 'In_Progress' ? 'btn-primary' : 'btn-secondary'; ?>">In Progress</a>
                <a href="?status=Completed" class="btn btn-sm <?php echo $statusFilter === 'Completed' ? 'btn-primary' : 'btn-secondary'; ?>">Completed</a>
            </div>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Request #</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Submitted By</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($requests)): ?>
                        <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($request['request_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($request['title']); ?></td>
                            <td><?php echo htmlspecialchars($request['type_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['location_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                            <td><?php echo getPriorityBadge($request['priority']); ?></td>
                            <td><?php echo getStatusBadge($request['status']); ?></td>
                            <td class="table-actions">
                                <a href="<?php echo BASE_URL; ?>/requests/view.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($request['status'] === 'Approved'): ?>
                                <a href="<?php echo BASE_URL; ?>/management/assign.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-user-check"></i> Assign
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No requests found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
