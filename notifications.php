<?php
$pageTitle = 'Notifications';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$conn = getDBConnection();
$userId = getCurrentUserId();

// Redirects must run before any HTML output (header.php)
if (isset($_GET['mark_read'])) {
    $notificationId = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $userId);
    $stmt->execute();
    $stmt->close();
    closeDBConnection($conn);
    header('Location: ' . BASE_URL . '/notifications.php');
    exit();
}

if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    closeDBConnection($conn);
    header('Location: ' . BASE_URL . '/notifications.php');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-bell"></i> Notifications</h1>
        <?php if (!empty($notifications)): ?>
        <!-- Mark All as Read button removed -->
        <?php endif; ?>
    </div>
    
    <div class="card">
        <?php if (!empty($notifications)): ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $notification): ?>
            <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                <div class="notification-icon">
                    <i class="fas fa-<?php 
                        echo $notification['notification_type'] === 'new_request' ? 'file-alt' : 
                            ($notification['notification_type'] === 'request_approved' ? 'check-circle' : 
                            ($notification['notification_type'] === 'request_rejected' ? 'times-circle' : 
                            ($notification['notification_type'] === 'task_assigned' ? 'user-check' : 'bell'))); 
                    ?>"></i>
                </div>
                <div class="notification-content">
                    <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                    <small><?php echo formatDate($notification['created_at']); ?></small>
                </div>
                <div class="notification-actions">
                    <?php if ($notification['request_id']): ?>
                    <a href="<?php echo BASE_URL; ?>/requests/view.php?id=<?php echo $notification['request_id']; ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-eye"></i> View Request
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <p>No notifications</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>



