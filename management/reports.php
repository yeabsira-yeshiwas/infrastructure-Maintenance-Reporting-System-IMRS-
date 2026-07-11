<?php
$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/functions.php';

requireAnyRole(['Maintenance_Manager', 'Admin']);

$conn = getDBConnection();
$selectedMonth = $_GET['month'] ?? 'all';
$selectedDepartment = $_GET['department'] ?? 'all';

function buildReportWhereClause($selectedMonth, $selectedDepartment) {
    $where = [];
    $params = [];
    $types = '';

    if ($selectedMonth !== 'all') {
        $where[] = "DATE_FORMAT(mr.submitted_at, '%Y-%m') = ?";
        $params[] = $selectedMonth;
        $types .= 's';
    }

    if ($selectedDepartment !== 'all') {
        $where[] = "it.type_name = ?";
        $params[] = $selectedDepartment;
        $types .= 's';
    }

    return ['where' => $where, 'params' => $params, 'types' => $types];
}

$filterConfig = buildReportWhereClause($selectedMonth, $selectedDepartment);
$whereClause = !empty($filterConfig['where']) ? ' WHERE ' . implode(' AND ', $filterConfig['where']) : '';

$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM maintenance_requests mr JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id" . $whereClause . " GROUP BY status");
if (!empty($filterConfig['params'])) {
    $stmt->bind_param($filterConfig['types'], ...$filterConfig['params']);
}
$stmt->execute();
$statusStats = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $statusStats[$row['status']] = $row['count'];
}
$stmt->close();

$stmt = $conn->prepare("SELECT it.type_name, COUNT(*) as count
    FROM maintenance_requests mr
    JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id" . $whereClause . "
    GROUP BY it.type_id, it.type_name
    ORDER BY count DESC");
if (!empty($filterConfig['params'])) {
    $stmt->bind_param($filterConfig['types'], ...$filterConfig['params']);
}
$stmt->execute();
$typeStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT priority, COUNT(*) as count FROM maintenance_requests mr JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id" . $whereClause . " GROUP BY priority");
if (!empty($filterConfig['params'])) {
    $stmt->bind_param($filterConfig['types'], ...$filterConfig['params']);
}
$stmt->execute();
$priorityStats = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $priorityStats[$row['priority']] = $row['count'];
}
$stmt->close();

$recentSql = "SELECT mr.*, it.type_name, l.location_name,
    u.full_name as full_name
    FROM maintenance_requests mr
    JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
    JOIN locations l ON mr.location_id = l.location_id
    JOIN users u ON mr.user_id = u.user_id" . $whereClause . "
    ORDER BY mr.submitted_at DESC
    LIMIT 20";
$stmt = $conn->prepare($recentSql);
if (!empty($filterConfig['params'])) {
    $stmt->bind_param($filterConfig['types'], ...$filterConfig['params']);
}
$stmt->execute();
$recentRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$monthStmt = $conn->query("SELECT DISTINCT DATE_FORMAT(submitted_at, '%Y-%m') AS month_value FROM maintenance_requests ORDER BY month_value DESC");
$availableMonths = $monthStmt->fetch_all(MYSQLI_ASSOC);
$monthStmt->close();

$departmentStmt = $conn->query("SELECT DISTINCT type_name FROM infrastructure_types ORDER BY type_name");
$departments = $departmentStmt->fetch_all(MYSQLI_ASSOC);
$departmentStmt->close();

closeDBConnection($conn);
?>

<div class="page-container management-shell">
    <section class="management-hero">
        <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
        <p>Review request volume, operational distribution, and recent activity across the maintenance system from a single executive view.</p>
        <div class="management-hero-meta">
            <span class="management-chip"><i class="fas fa-wave-square"></i> Live operational summary</span>
            <span class="management-chip"><i class="fas fa-clipboard-check"></i> <?php echo array_sum($statusStats); ?> total requests</span>
            <a href="<?php echo BASE_URL; ?>/management/reports_pdf.php?month=<?php echo urlencode($selectedMonth); ?>&department=<?php echo urlencode($selectedDepartment); ?>" target="_blank" class="btn btn-primary" style="margin-left:1rem;">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>
    </section>

    <form method="get" class="filter-bar" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;margin-bottom:1.5rem;">
        <label style="display:flex;flex-direction:column;font-size:0.95rem;gap:0.25rem;">
            <span>Month</span>
            <select name="month" class="form-control" style="min-width:180px;">
                <option value="all" <?php echo $selectedMonth === 'all' ? 'selected' : ''; ?>>All months</option>
                <?php foreach ($availableMonths as $month): ?>
                    <option value="<?php echo htmlspecialchars($month['month_value']); ?>" <?php echo $selectedMonth === $month['month_value'] ? 'selected' : ''; ?>><?php echo date('M Y', strtotime($month['month_value'] . '-01')); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label style="display:flex;flex-direction:column;font-size:0.95rem;gap:0.25rem;">
            <span>Department</span>
            <select name="department" class="form-control" style="min-width:220px;">
                <option value="all" <?php echo $selectedDepartment === 'all' ? 'selected' : ''; ?>>All departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?php echo htmlspecialchars($department['type_name']); ?>" <?php echo $selectedDepartment === $department['type_name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($department['type_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
        <a href="<?php echo BASE_URL; ?>/management/reports.php" class="btn btn-secondary">Reset</a>
    </form>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon stat-primary">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo array_sum($statusStats); ?></h3>
                <p>Total Requests</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $statusStats['Pending'] ?? 0; ?></h3>
                <p>Pending</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $statusStats['Completed'] ?? 0; ?></h3>
                <p>Completed</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-info">
                <i class="fas fa-spinner"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo ($statusStats['In_Progress'] ?? 0) + ($statusStats['Assigned'] ?? 0); ?></h3>
                <p>In Progress</p>
            </div>
        </div>
    </div>

    <div class="dashboard-section panel">
        <div class="panel-header">
            <div>
                <h2><i class="fas fa-chart-pie"></i> Requests by Type</h2>
                <p>Identify the infrastructure categories creating the highest maintenance demand.</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Infrastructure Type</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($typeStats as $stat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stat['type_name']); ?></td>
                            <td><?php echo $stat['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="dashboard-section panel">
        <div class="panel-header">
            <div>
                <h2><i class="fas fa-exclamation-triangle"></i> Requests by Priority</h2>
                <p>Measure urgency concentration to guide staffing and response planning.</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Priority</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (['Urgent', 'High', 'Medium', 'Low'] as $priority): ?>
                        <tr>
                            <td><?php echo getPriorityBadge($priority); ?></td>
                            <td><?php echo $priorityStats[$priority] ?? 0; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="dashboard-section panel">
        <div class="panel-header">
            <div>
                <h2><i class="fas fa-history"></i> Recent Requests</h2>
                <p>Inspect the latest submissions and their current processing status.</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request #</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Submitted By</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRequests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['request_number']); ?></td>
                            <td><?php echo htmlspecialchars($request['title']); ?></td>
                            <td><?php echo htmlspecialchars($request['type_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['location_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                            <td><?php echo getStatusBadge($request['status']); ?></td>
                            <td><?php echo formatDate($request['submitted_at'], 'M d, Y'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
