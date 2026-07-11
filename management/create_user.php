<?php
$pageTitle = 'Create User';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAnyRole(['Maintenance_Manager', 'Admin']);

$conn = getDBConnection();
$error = '';
$canonicalStudentLocs = getCanonicalStudentLocations();

$infraStmt = $conn->query("SELECT type_name FROM infrastructure_types ORDER BY type_name ASC");
$infraTypes = $infraStmt->fetch_all(MYSQLI_ASSOC);
$infraStmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? '');
    $department = sanitizeInput($_POST['department'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');

    if ($role === 'Maintenance_Team') {
        $department = sanitizeInput($_POST['maintenance_specialty'] ?? '');
    }
    
    $currentUserRole = getCurrentUserRole();

    $proctorLocations = [];
    if ($role === 'Proctor') {
        foreach ($_POST['proctor_locations'] ?? [] as $raw) {
            $raw = trim((string) $raw);
            if (in_array($raw, $canonicalStudentLocs, true)) {
                $proctorLocations[] = $raw;
            }
        }
        $proctorLocations = array_values(array_unique($proctorLocations));
    }

    if (empty($username) || empty($email) || empty($password) || empty($fullName) || empty($role)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (in_array($role, ['Student', 'Staff'], true)) {
        $error = 'Students and Staff must register themselves. They cannot be created here.';
    } elseif ($currentUserRole === 'Maintenance_Manager' && in_array($role, ['Maintenance_Manager', 'Admin'], true)) {
        $error = 'Maintenance Manager cannot create Maintenance Manager or Admin accounts.';
    } elseif ($role === 'Proctor' && empty($proctorLocations)) {
        $error = 'Proctor must be assigned at least one location from the list.';
    } else {
        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $checkStmt->bind_param("ss", $username, $email);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Username or email already exists.';
        } else {
            $passwordHash = hashPassword($password);
            $conn->begin_transaction();
            try {
                $insertStmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role, department, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->bind_param("sssssss", $username, $email, $passwordHash, $fullName, $role, $department, $phone);

                if (!$insertStmt->execute()) {
                    throw new Exception('insert user failed');
                }
                $userId = $conn->insert_id;
                $insertStmt->close();

                if ($role === 'Proctor') {
                    $plStmt = $conn->prepare("INSERT INTO proctor_locations (proctor_user_id, location_name) VALUES (?, ?)");
                    foreach ($proctorLocations as $locName) {
                        $plStmt->bind_param("is", $userId, $locName);
                        $plStmt->execute();
                    }
                    $plStmt->close();
                }

                $conn->commit();
                $checkStmt->close();
                logSystemAction('Create User', 'users', $userId, "User created: $role - $fullName");
                closeDBConnection($conn);
                header('Location: ' . BASE_URL . '/management/users.php?success=user_created');
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to create user. Please try again.';
            }
        }
        $checkStmt->close();
    }
}

closeDBConnection($conn);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-container management-shell">
    <section class="management-hero">
        <h1><i class="fas fa-user-plus"></i> Create User</h1>
        <p>Provision operational accounts for approvers, managers, and technicians with the right role boundaries from the start.</p>
        <div class="management-hero-meta">
            <span class="management-chip"><i class="fas fa-id-badge"></i> Role-based access control</span>
            <span class="management-chip"><i class="fas fa-user-lock"></i> <?php echo htmlspecialchars(getCurrentUserRole()); ?> permissions active</span>
        </div>
    </section>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <div class="management-grid-2">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Account Details</h2>
                    <p>Define identity, contact information, role, and initial password.</p>
                </div>
                <a href="<?php echo BASE_URL; ?>/management/users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            <div class="panel-body">
                <form method="POST" action="<?php echo BASE_URL; ?>/management/create_user.php" class="form management-form" id="create-user-form">
            <div class="form-group">
                <label for="full_name">
                    <i class="fas fa-id-card"></i> Full Name *
                </label>
                <input type="text" id="full_name" name="full_name" required>
            </div>

            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Username *
                </label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i> Email *
                </label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="role">
                    <i class="fas fa-user-tag"></i> Role *
                </label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="Proctor">Proctor</option>
                    <option value="Office_Head">Office Head</option>
                    <option value="Maintenance_Team">Maintenance Team</option>
                    <?php if (getCurrentUserRole() !== 'Maintenance_Manager'): ?>
                    <option value="Maintenance_Manager">Maintenance Manager</option>
                    <option value="Admin">Admin</option>
                    <?php endif; ?>
                </select>
                <small>Students and Staff must register themselves.</small>
            </div>

            <div class="form-group full-width" id="proctor-locations-block" style="display: none;">
                <label><i class="fas fa-map-marker-alt"></i> Assigned locations for Proctor *</label>
                <p class="form-note" style="margin-bottom: 0.75rem;">Students who submit requests for these locations will notify this proctor for approval.</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.5rem;">
                    <?php foreach ($canonicalStudentLocs as $loc): ?>
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: normal;">
                        <input type="checkbox" name="proctor_locations[]" value="<?php echo htmlspecialchars($loc); ?>">
                        <?php echo htmlspecialchars($loc); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group" id="maintenance-specialty-block" style="display: none;">
                <label for="maintenance_specialty">
                    <i class="fas fa-tools"></i> Infrastructure Type / Department *
                </label>
                <select id="maintenance_specialty" name="maintenance_specialty">
                    <option value="">Select Specialty</option>
                    <?php foreach ($infraTypes as $type): ?>
                    <option value="<?php echo htmlspecialchars($type['type_name']); ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Required for maintenance team assignment filter.</small>
            </div>

            <!-- <div class="form-group" id="department-block"> 
                <label for="department">
                    <i class="fas fa-building"></i> Department/Location
                </label>
                <input type="text" id="department" name="department">
                <small>Optional field for general management notes.</small>
            </div> -->

            <div class="form-group">
                <label for="phone">
                    <i class="fas fa-phone"></i> Phone
                </label>
                <input type="text" id="phone" name="phone">
            </div>

            <div class="form-group full-width">
                <label for="password">
                    <i class="fas fa-lock"></i> Password *
                </label>
                <input type="password" id="password" name="password" required minlength="6">
                <small>User will use this password to login. They can change it later.</small>
            </div>

            <div class="form-group full-width">
                <label for="confirm_password">
                    <i class="fas fa-lock"></i> Confirm Password *
                </label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
            </div>

            <div class="form-actions full-width">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Create User
                </button>
                <a href="<?php echo BASE_URL; ?>/management/users.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
            </div>
        </section>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h3>Provisioning Notes</h3>
                    <p>Use the correct account type for each point in the maintenance workflow.</p>
                </div>
            </div>
            <div class="panel-body detail-list">
                <div class="detail-row">
                    <div class="detail-row-label">Proctor</div>
                    <div class="detail-row-value">Approves student requests for assigned locations only.</div>
                </div>
                <div class="detail-row">
                    <div class="detail-row-label">Office Head</div>
                    <div class="detail-row-value">Staff choose their office head on each request; that person approves.</div>
                </div>
                <div class="detail-row">
                    <div class="detail-row-label">Maintenance Team</div>
                    <div class="detail-row-value">Executes assigned maintenance work</div>
                </div>
                <div class="detail-row">
                    <div class="detail-row-label">Manager boundary</div>
                    <div class="detail-row-value"><?php echo getCurrentUserRole() === 'Maintenance_Manager' ? 'Cannot create Admin or Maintenance Manager' : 'Can provision all management roles'; ?></div>
                </div>
                <p class="form-note">For shared operational accounts, prefer institutional email addresses and department names so downstream assignment and reporting stay readable.</p>
            </div>
        </aside>
    </div>
</div>

<script>
(function () {
    var role = document.getElementById('role');
    var proctorBlock = document.getElementById('proctor-locations-block');
    var maintenanceBlock = document.getElementById('maintenance-specialty-block');
    var departmentBlock = document.getElementById('department-block');

    function toggle() {
        if (!role) return;
        
        if (proctorBlock) proctorBlock.style.display = role.value === 'Proctor' ? 'block' : 'none';
        if (maintenanceBlock) maintenanceBlock.style.display = role.value === 'Maintenance_Team' ? 'block' : 'none';
        
        if (departmentBlock) {
            // Hide for Proctor, Office Head, and Maintenance Team as per user request
            if (['Proctor', 'Office_Head', 'Maintenance_Team'].includes(role.value)) {
                departmentBlock.style.display = 'none';
            } else {
                departmentBlock.style.display = 'block';
            }
        }
    }
    if (role) role.addEventListener('change', toggle);
    toggle();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
