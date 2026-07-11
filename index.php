<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$conn = getDBConnection();
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

if (in_array($userRole, ['Maintenance_Manager', 'Admin'], true)) {
    closeDBConnection($conn);
    header('Location: ' . BASE_URL . '/management/dashboard.php');
    exit();
}

// Get statistics based on user role
$stats = [];

if (hasAnyRole(['Student', 'Staff', 'General_User'])) {
    // User's own requests
    if ($userRole === 'General_User') {
        // General User stats - include all statuses since requests skip approval
        $stmt = $conn->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN status = 'In_Progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
            FROM maintenance_requests WHERE user_id = ?");
    } else {
        // Student/Staff stats
        $stmt = $conn->prepare("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
            FROM maintenance_requests WHERE user_id = ?");
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Recent requests
    $stmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name 
        FROM maintenance_requests mr
        JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
        JOIN locations l ON mr.location_id = l.location_id
        WHERE mr.user_id = ?
        ORDER BY mr.submitted_at DESC LIMIT 5");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $recentRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} elseif (hasAnyRole(['Proctor', 'Office_Head'])) {
    $approverId = (int) getCurrentUserId();
    if ($userRole === 'Proctor') {
        $stmt = $conn->prepare("SELECT COUNT(*) as pending
            FROM maintenance_requests mr
            JOIN users u ON mr.user_id = u.user_id
            JOIN locations l ON mr.location_id = l.location_id
            WHERE mr.status = 'Pending' AND u.role = 'Student'
            AND EXISTS (
                SELECT 1 FROM proctor_locations pl
                WHERE pl.proctor_user_id = ? AND pl.location_name = l.location_name
            )");
        $stmt->bind_param("i", $approverId);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as pending
            FROM maintenance_requests mr
            JOIN users u ON mr.user_id = u.user_id
            WHERE mr.status = 'Pending' AND u.role = 'Staff'
            AND mr.assigned_office_head_id = ?");
        $stmt->bind_param("i", $approverId);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats = ['pending' => $result['pending']];
    $stmt->close();

    if ($userRole === 'Proctor') {
        $stmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, u.full_name
            FROM maintenance_requests mr
            JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
            JOIN locations l ON mr.location_id = l.location_id
            JOIN users u ON mr.user_id = u.user_id
            WHERE mr.status = 'Pending' AND u.role = 'Student'
            AND EXISTS (
                SELECT 1 FROM proctor_locations pl
                WHERE pl.proctor_user_id = ? AND pl.location_name = l.location_name
            )
            ORDER BY mr.submitted_at DESC LIMIT 5");
        $stmt->bind_param("i", $approverId);
    } else {
        $stmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, u.full_name
            FROM maintenance_requests mr
            JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
            JOIN locations l ON mr.location_id = l.location_id
            JOIN users u ON mr.user_id = u.user_id
            WHERE mr.status = 'Pending' AND u.role = 'Staff'
            AND mr.assigned_office_head_id = ?
            ORDER BY mr.submitted_at DESC LIMIT 5");
        $stmt->bind_param("i", $approverId);
    }
    $stmt->execute();
    $recentRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} elseif (getCurrentUserRole() === 'Admin') {
    // Admin statistics
    $stmt = $conn->prepare("SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM maintenance_requests) as total_requests,
        (SELECT COUNT(*) FROM maintenance_requests WHERE status = 'Pending') as pending_requests,
        (SELECT COUNT(*) FROM maintenance_requests WHERE status = 'Completed') as completed_requests");
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Recent all requests
    $stmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, u.full_name
        FROM maintenance_requests mr
        JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
        JOIN locations l ON mr.location_id = l.location_id
        JOIN users u ON mr.user_id = u.user_id
        ORDER BY mr.submitted_at DESC LIMIT 5");
    $stmt->execute();
    $recentRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} elseif (getCurrentUserRole() === 'Maintenance_Manager') {
    // Manager statistics
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
        SUM(CASE WHEN status = 'In_Progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
        FROM maintenance_requests WHERE status IN ('Approved', 'Assigned', 'In_Progress', 'Completed')");
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Recent approved requests
    $stmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, u.full_name
        FROM maintenance_requests mr
        JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
        JOIN locations l ON mr.location_id = l.location_id
        JOIN users u ON mr.user_id = u.user_id
        WHERE mr.status = 'Approved'
        ORDER BY mr.approved_at DESC LIMIT 5");
    $stmt->execute();
    $recentRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} elseif (getCurrentUserRole() === 'Maintenance_Team') {
    // Team member's assigned tasks
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN mr.status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
        SUM(CASE WHEN mr.status = 'In_Progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN mr.status = 'Completed' THEN 1 ELSE 0 END) as completed
        FROM task_assignments ta
        JOIN maintenance_requests mr ON ta.request_id = mr.request_id
        WHERE ta.assigned_to = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Recent assigned tasks
    $stmt = $conn->prepare("SELECT mr.*, it.type_name, l.location_name, ta.scheduled_date
        FROM task_assignments ta
        JOIN maintenance_requests mr ON ta.request_id = mr.request_id
        JOIN infrastructure_types it ON mr.infrastructure_type_id = it.type_id
        JOIN locations l ON mr.location_id = l.location_id
        WHERE ta.assigned_to = ?
        ORDER BY ta.assigned_at DESC LIMIT 5");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $recentRequests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

closeDBConnection($conn);

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard">
    <div class="page-header page-header--dashboard">
        <div class="page-header-copy">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($currentUser['full_name']); ?>!</p>
        </div>
    </div>
    
    <?php if (hasRole('General_User')): ?>
    <div class="dashboard-banner dashboard-banner--general">
        <h3><i class="fas fa-info-circle"></i> Welcome, General User!</h3>
        <p>Your requests are automatically approved and sent directly to the maintenance team. No approval needed!</p>
    </div>
    
    <!-- <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;"> 
        <a href="<?php echo BASE_URL; ?>/requests/create.php" class="btn btn-primary" style="flex: 1; min-width: 200px;">
            <i class="fas fa-plus-circle"></i> Submit New Request
        </a>
        <a href="<?php echo BASE_URL; ?>/requests/my_requests.php" class="btn btn-info" style="flex: 1; min-width: 200px;">
            <i class="fas fa-list"></i> View All My Requests
        </a>
    </div> -->
    <?php endif; ?>
    
    <div class="stats-grid">
        <?php if (hasAnyRole(['Student', 'Staff', 'General_User'])): ?>
            <div class="stat-card">
                <div class="stat-icon stat-primary">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total'] ?? 0; ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-success">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['approved'] ?? 0; ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-info">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['completed'] ?? 0; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
        <?php elseif (hasAnyRole(['Proctor', 'Office_Head'])): ?>
            <div class="stat-card">
                <div class="stat-icon stat-warning">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                    <p>Pending Approvals</p>
                </div>
            </div>
        <?php elseif (getCurrentUserRole() === 'Admin'): ?>  
            <div class="stat-card">
                <div class="stat-icon stat-primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_users'] ?? 0; ?></h3>
                    <p>Total Users</p>
                </div>
            </div> 
            <div class="stat-card">
                <div class="stat-icon stat-info">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_requests'] ?? 0; ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['pending_requests'] ?? 0; ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['completed_requests'] ?? 0; ?></h3>
                    <p>Completed Requests</p>
                </div>
            </div>
        <?php elseif (getCurrentUserRole() === 'Maintenance_Manager'): ?> 
            <div class="stat-card">
                <div class="stat-icon stat-primary">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['approved'] ?? 0; ?></h3>
                    <p>Approved Requests</p>
                </div>
            </div> 
            <div class="stat-card">
                <div class="stat-icon stat-info">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['assigned'] ?? 0; ?></h3>
                    <p>Assigned Tasks</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-warning">
                    <i class="fas fa-spinner"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['in_progress'] ?? 0; ?></h3>
                    <p>In Progress</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['completed'] ?? 0; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
        <?php elseif (getCurrentUserRole() === 'Maintenance_Team'): ?>
            <div class="stat-card">
                <div class="stat-icon stat-primary">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total'] ?? 0; ?></h3>
                    <p>Total Tasks</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-info">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['assigned'] ?? 0; ?></h3>
                    <p>Assigned</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-warning">
                    <i class="fas fa-spinner"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['in_progress'] ?? 0; ?></h3>
                    <p>In Progress</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['completed'] ?? 0; ?></h3>
                    <p>Completed</p>
                </div>
            </div>
        <?php endif; ?>
    
    <?php if (hasRole('General_User')): ?>
    <div class="dashboard-section">
        <div class="dashboard-section__head">
            <h2><i class="fas fa-history"></i> Recent Requests</h2>
            <a href="<?php echo BASE_URL; ?>/requests/my_requests.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-list"></i> View All
            </a>
        </div>
        <?php if (!empty($recentRequests)): ?>
        <div class="card card--page">
            <div class="table-container table-container--rounded">
                <table class="data-table data-table--comfortable">
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
                        <?php foreach ($recentRequests as $request): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($request['request_number']); ?></strong></td>
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
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>You haven't submitted any requests yet.</p>
                <a href="<?php echo BASE_URL; ?>/requests/create.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Submit Your First Request
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="dashboard-section">
        <h2><i class="fas fa-lightbulb"></i> Quick Tips</h2>
        <div class="card card--tips">
            <div class="tip-grid">
                <div class="tip-card tip-card--info">
                    <h4><i class="fas fa-bolt"></i> Fast Approval</h4>
                    <p>Your requests skip the approval process and go directly to the maintenance team.</p>
                </div>
                <div class="tip-card tip-card--success">
                    <h4><i class="fas fa-bell"></i> Get Notified</h4>
                    <p>You&apos;ll receive notifications when your request status changes.</p>
                </div>
                <div class="tip-card tip-card--warning">
                    <h4><i class="fas fa-paperclip"></i> Add Photos</h4>
                    <p>Upload mandatory photos or documents to help the maintenance team understand the issue better.</p>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="dashboard-section">
        <h2><i class="fas fa-history"></i> Recent Activity</h2>
        <div class="card card--page">
        <div class="table-container table-container--rounded">
            <table class="data-table data-table--comfortable">
                <thead>
                    <tr>
                        <th>Request #</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Date</th>
                        <?php if (hasAnyRole(['Proctor', 'Office_Head', 'Maintenance_Manager', 'Admin'])): ?>
                        <th>Submitted By</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recentRequests)): ?>
                        <?php foreach ($recentRequests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['request_number']); ?></td>
                            <td><?php echo htmlspecialchars($request['title']); ?></td>
                            <td><?php echo htmlspecialchars($request['type_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['location_name']); ?></td>
                            <td><?php echo getStatusBadge($request['status']); ?></td>
                            <td><?php echo formatDate($request['submitted_at'], 'M d, Y'); ?></td>
                            <?php if (hasAnyRole(['Proctor', 'Office_Head', 'Maintenance_Manager', 'Admin'])): ?>
                            <td><?php echo htmlspecialchars($request['full_name'] ?? 'N/A'); ?></td>
                            <?php endif; ?>
                            <td>
                                <a href="<?php echo BASE_URL; ?>/requests/view.php?id=<?php echo $request['request_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo hasAnyRole(['Proctor', 'Office_Head', 'Maintenance_Manager', 'Admin']) ? '8' : '7'; ?>" class="text-center">
                                No recent activity
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
