<?php
$pageTitle = 'User Management';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAnyRole(['Maintenance_Manager', 'Admin']);

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($userId > 0 && $action === 'toggle_status') {
        $currentUserRole = getCurrentUserRole();

        if ($currentUserRole === 'Maintenance_Manager') {
            $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'Active', 'Inactive', 'Active') WHERE user_id = ? AND role != 'Admin'");
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'Active', 'Inactive', 'Active') WHERE user_id = ?");
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
        logSystemAction('Update User Status', 'users', $userId, 'User status toggled');
    }

    closeDBConnection($conn);
    header('Location: ' . BASE_URL . '/management/users.php?success=user_updated');
    exit();
}

$currentUserRole = getCurrentUserRole();
if ($currentUserRole === 'Maintenance_Manager') {
    $stmt = $conn->query("SELECT * FROM users WHERE role != 'Admin' ORDER BY created_at DESC");
} else {
    $stmt = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
}
$users = $stmt->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);

require_once __DIR__ . '/../includes/header.php';

$activeUsers = 0;
$inactiveUsers = 0;
$roleCounts = [];

foreach ($users as $user) {
    if ($user['status'] === 'Active') {
        $activeUsers++;
    } else {
        $inactiveUsers++;
    }

    $roleCounts[$user['role']] = ($roleCounts[$user['role']] ?? 0) + 1;
}
?>

<div class="page-container management-shell">
    <section class="management-hero">
        <h1><i class="fas fa-users"></i> User Management</h1>
        <p>Control access across the maintenance workflow, monitor active accounts, and keep operational roles current from one management surface.</p>
        <div class="management-hero-meta">
            <span class="management-chip"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars(getCurrentUserRole()); ?> access</span>
            <span class="management-chip"><i class="fas fa-user-check"></i> <?php echo $activeUsers; ?> active users</span>
            <span class="management-chip"><i class="fas fa-user-slash"></i> <?php echo $inactiveUsers; ?> inactive users</span>
        </div>
    </section>

    <section class="management-grid">
        <div class="summary-card">
            <h3>Total Users</h3>
            <strong><?php echo count($users); ?></strong>
            <span>All registered operational and requester accounts.</span>
        </div>
        <div class="summary-card">
            <h3>Approvers</h3>
            <strong><?php echo ($roleCounts['Proctor'] ?? 0) + ($roleCounts['Office_Head'] ?? 0); ?></strong>
            <span>Proctors and office heads currently in the system.</span>
        </div>
        <div class="summary-card">
            <h3>Maintenance Team</h3>
            <strong><?php echo $roleCounts['Maintenance_Team'] ?? 0; ?></strong>
            <span>Technicians available for assignment and task execution.</span>
        </div>
        <div class="summary-card">
            <h3>Leadership</h3>
            <strong><?php echo ($roleCounts['Maintenance_Manager'] ?? 0) + ($roleCounts['Admin'] ?? 0); ?></strong>
            <span>Management and high-privilege accounts.</span>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>User Directory</h2>
                <p>Review account status, role assignment, and basic identity details.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/management/create_user.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Create User
            </a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong>#<?php echo $user['user_id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge"><?php echo htmlspecialchars(str_replace('_', ' ', $user['role'])); ?></span></td>
                        <td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge <?php echo $user['status'] === 'Active' ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo htmlspecialchars($user['status']); ?>
                            </span>
                        </td>
                        <td><?php echo formatDate($user['created_at'], 'Y-m-d'); ?></td>
                        <td>
                            <form method="POST" action="<?php echo BASE_URL; ?>/management/users.php" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <button type="submit" class="btn btn-sm <?php echo $user['status'] === 'Active' ? 'btn-warning' : 'btn-success'; ?>">
                                    <i class="fas fa-<?php echo $user['status'] === 'Active' ? 'ban' : 'check'; ?>"></i>
                                    <?php echo $user['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
 </div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
