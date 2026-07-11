<?php
$pageTitle = 'Profile';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$conn = getDBConnection();
$userId = getCurrentUserId();
$error = '';

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $department = sanitizeInput($_POST['department'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($fullName) || empty($email)) {
        $error = 'Full name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $emailStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $emailStmt->bind_param("si", $email, $userId);
        $emailStmt->execute();
        if ($emailStmt->get_result()->num_rows > 0) {
            $error = 'Email address is already in use.';
        } else {
            $updateStmt = null;
            if (!empty($newPassword)) {
                if (empty($currentPassword) || !verifyPassword($currentPassword, $user['password_hash'])) {
                    $error = 'Current password is incorrect.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($newPassword) < 6) {
                    $error = 'Password must be at least 6 characters long.';
                } else {
                    $passwordHash = hashPassword($newPassword);
                    $updateStmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, department = ?, phone = ?, password_hash = ? WHERE user_id = ?");
                    $updateStmt->bind_param("sssssi", $fullName, $email, $department, $phone, $passwordHash, $userId);
                }
            } else {
                $updateStmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, department = ?, phone = ? WHERE user_id = ?");
                $updateStmt->bind_param("ssssi", $fullName, $email, $department, $phone, $userId);
            }

            if ($error === '' && $updateStmt !== null) {
                if ($updateStmt->execute()) {
                    $updateStmt->close();
                    $emailStmt->close();
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['email'] = $email;
                    logSystemAction('Update Profile', 'users', $userId, 'Profile updated');
                    closeDBConnection($conn);
                    header('Location: ' . BASE_URL . '/profile.php?success=profile_updated');
                    exit();
                }
                $error = 'Failed to update profile.';
                $updateStmt->close();
            } elseif ($updateStmt !== null && $error !== '') {
                $updateStmt->close();
            }
        }
        $emailStmt->close();
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

closeDBConnection($conn);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user"></i> My Profile</h1>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="<?php echo BASE_URL; ?>/profile.php" class="form">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                <small>Username cannot be changed</small>
            </div>

            <div class="form-group">
                <label for="full_name">
                    <i class="fas fa-id-card"></i> Full Name *
                </label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i> Email *
                </label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="role">
                    <i class="fas fa-user-tag"></i> Role
                </label>
                <input type="text" id="role" value="<?php echo htmlspecialchars($user['role']); ?>" disabled>
                <small>Role cannot be changed</small>
            </div>

             <?php if (strtolower(trim($user['role'])) === 'staff') { ?>
                          <div class="form-group">
                     <label for="department">
                            <i class="fas fa-building"></i> Department
                              </label>
                <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                         </div>
                       <?php } ?>



            <hr>

            <h3>Change Password</h3>
            <small>Leave blank if you don't want to change your password</small>

            <div class="form-group">
                <label for="current_password">
                    <i class="fas fa-lock"></i> Current Password
                </label>
                <input type="password" id="current_password" name="current_password">
            </div>

            <div class="form-group">
                <label for="new_password">
                    <i class="fas fa-lock"></i> New Password
                </label>
                <input type="password" id="new_password" name="new_password" minlength="6">
            </div>

            <div class="form-group">
                <label for="confirm_password">
                    <i class="fas fa-lock"></i> Confirm New Password
                </label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="6">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Profile
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
