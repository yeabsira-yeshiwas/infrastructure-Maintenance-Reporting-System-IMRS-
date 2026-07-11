<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

$currentUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'full_name' => $_SESSION['full_name'],
    'role' => $_SESSION['role']
];

$notificationCount = getUnreadNotificationsCount($currentUser['id']);
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$styleFile = __DIR__ . '/../assets/css/style.css';
$styleVersion = file_exists($styleFile) ? filemtime($styleFile) : APP_VERSION;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo urlencode((string) $styleVersion); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fas fa-tools"></i>
            <div>
                <strong>IMRS</strong>
                <span>Maintenance Portal</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-section-label">Workspace</div>
            <ul class="nav-menu">
                <?php if (hasAnyRole(['Maintenance_Manager', 'Admin'])): ?>
                <li><a class="<?php echo str_contains($currentPath, '/management/dashboard.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/management/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <?php else: ?>
                <li><a class="<?php echo $currentPath === BASE_URL . '/index.php' || str_ends_with($currentPath, '/index.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <?php endif; ?>

                <?php if (hasAnyRole(['Student', 'Staff', 'General_User'])): ?>
                <li><a class="<?php echo str_contains($currentPath, '/requests/create.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/requests/create.php"><i class="fas fa-plus-circle"></i> New Request</a></li>
                <li><a class="<?php echo str_contains($currentPath, '/requests/my_requests.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/requests/my_requests.php"><i class="fas fa-list"></i> My Requests</a></li>
                <?php endif; ?>

                <?php if (hasAnyRole(['Proctor', 'Office_Head'])): ?>
                <li><a class="<?php echo str_contains($currentPath, '/approvals/pending.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/approvals/pending.php"><i class="fas fa-check-circle"></i> Pending Approvals</a></li>
                <?php endif; ?>

                <?php if (hasRole('Maintenance_Team')): ?>
                <li><a class="<?php echo str_contains($currentPath, '/team/my_tasks.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/team/my_tasks.php"><i class="fas fa-clipboard-list"></i> My Tasks</a></li>
                <?php endif; ?>

                <?php if (hasAnyRole(['Maintenance_Manager', 'Admin'])): ?>
                <li><a class="<?php echo str_contains($currentPath, '/management/requests.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/management/requests.php"><i class="fas fa-list-check"></i> All Requests</a></li>
                <li><a class="<?php echo str_contains($currentPath, '/management/dashboard.php') && str_contains($_SERVER['REQUEST_URI'] ?? '', 'pending-approvals') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/management/dashboard.php#pending-approvals"><i class="fas fa-hourglass-half"></i> Pending Approvals</a></li>
                <li><a class="<?php echo str_contains($currentPath, '/management/assign.php') || (str_contains($currentPath, '/management/requests.php') && str_contains($_SERVER['REQUEST_URI'] ?? '', 'status=Approved')) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/management/requests.php?status=Approved"><i class="fas fa-user-check"></i> Assign Tasks</a></li>
                <li><a class="<?php echo str_contains($currentPath, '/management/dashboard.php') && str_contains($_SERVER['REQUEST_URI'] ?? '', 'active-tasks') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/management/dashboard.php#active-tasks"><i class="fas fa-spinner"></i> Active Tasks</a></li>
                <li><a class="<?php echo str_contains($currentPath, '/management/dashboard.php') && str_contains($_SERVER['REQUEST_URI'] ?? '', 'completion-review') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/management/dashboard.php#completion-review"><i class="fas fa-clipboard-check"></i> Completed Tasks</a></li>
                <li><a class="<?php echo str_contains($currentPath, '/management/create_user.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/management/create_user.php"><i class="fas fa-user-plus"></i> Create User</a></li>
                <li><a class="<?php echo str_contains($currentPath, '/management/users.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/management/users.php"><i class="fas fa-users"></i> User Management</a></li>
                <li><a class="<?php echo str_contains($currentPath, '/management/reports.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/management/reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <a href="<?php echo BASE_URL; ?>/profile.php" class="sidebar-utility <?php echo str_contains($currentPath, '/profile.php') ? 'active' : ''; ?>">
                <span><i class="fas fa-user"></i> Profile</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/auth/logout.php" class="sidebar-utility">
                <span><i class="fas fa-sign-out-alt"></i> Logout</span>
            </a>
        </div>
    </aside>

    <div class="content-shell">
    <header class="topbar">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <a href="javascript:history.back()" class="btn-back" title="Go Back">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="topbar-copy">
                <span class="topbar-label">Operational Workspace</span>
                <strong><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></strong>
            </div>
        </div>
        <div class="topbar-actions">
            <a href="<?php echo BASE_URL; ?>/notifications.php?mark_all_read=1" class="notification-icon">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                    <span class="badge-count"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
            </a>
            <div class="user-display">
                <i class="fas fa-user-circle"></i>
                <div class="user-meta">
                    <div class="user-name-static"><?php echo htmlspecialchars($currentUser['full_name']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars(str_replace('_', ' ', $currentUser['role'])); ?></div>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php
            $messages = [
                'request_created' => 'Maintenance request created successfully!',
                'request_approved' => 'Request approved successfully!',
                'request_rejected' => 'Request rejected.',
                'task_assigned' => 'Task assigned successfully!',
                'status_updated' => 'Status updated successfully!',
                'profile_updated' => 'Profile updated successfully!',
                'user_created' => 'User created successfully!',
                'user_updated' => 'User updated successfully!',
                'completion_approved' => 'Work order closed successfully!',
                'task_reopened' => 'Task reopened successfully.'
            ];
            echo $messages[$_GET['success']] ?? 'Operation completed successfully!';
            ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php
            $messages = [
                'access_denied' => 'You do not have permission to access this page.',
                'invalid_request' => 'Invalid request.',
                'file_upload_error' => 'File upload failed.',
                'database_error' => 'Database error occurred.'
            ];
            echo $messages[$_GET['error']] ?? 'An error occurred!';
            ?>
        </div>
        <?php endif; ?>
