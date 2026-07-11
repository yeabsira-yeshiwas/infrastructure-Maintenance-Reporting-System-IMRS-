<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

$error = '';
$selectedRole = $_GET['role'] ?? ($_POST['role'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = sanitizeInput($_POST['role'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $institutionalId = sanitizeInput($_POST['institutional_id'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $officeHead = sanitizeInput($_POST['office_head'] ?? '');
    
    // Validation based on role
    if (empty($role) || !in_array($role, ['Student', 'Staff', 'General_User'])) {
        $error = 'Please select a valid role.';
    } elseif (empty($username) || empty($password) || empty($fullName)) {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/^[A-Za-z\s]+$/', $fullName)) {
        $error = 'Full name must contain letters only.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Role-specific validation
        if ($role === 'Student' || $role === 'Staff') {
            if (empty($institutionalId)) {
                $error = 'Institutional ID is required.';
            } elseif (!preg_match('/^BDU\d{7}$/i', $institutionalId)) {
                $error = 'Institutional ID must be in format BDU1234567 (BDU followed by 7 digits).';
            }
            
            if ($role === 'Staff' && empty($officeHead)) {
                $error = 'Office Head (Department Head) is required for Staff.';
            }
        } elseif ($role === 'General_User') {
            if (empty($email)) {
                $error = 'Email is required for General Users.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address.';
            }
        }
        
        if (empty($error)) {
            $conn = getDBConnection();
            
            // Check if username already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Username already exists.';
            } else {
                // Check email for General User or institutional ID for Student/Staff
                if ($role === 'General_User') {
                    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                    $checkStmt->bind_param("s", $email);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    if ($checkResult->num_rows > 0) {
                        $error = 'Email already exists.';
                    }
                    $checkStmt->close();
                } else {
                    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE institutional_id = ?");
                    $checkStmt->bind_param("s", $institutionalId);
                    $checkStmt->execute();
                    $checkResult = $checkStmt->get_result();
                    if ($checkResult->num_rows > 0) {
                        $error = 'Institutional ID already exists.';
                    }
                    $checkStmt->close();
                }
                
                if (empty($error)) {
                    $passwordHash = hashPassword($password);
                    
                    // For Student/Staff, use institutional ID as email if not provided
                    if ($role === 'Student' || $role === 'Staff') {
                        $email = $institutionalId . '@bit.edu.et'; // Default email format
                    }
                    
                    // Insert user
                    if ($role === 'Staff') {
                        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role, institutional_id, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssss", $username, $email, $passwordHash, $fullName, $role, $institutionalId, $officeHead);
                    } elseif ($role === 'Student') {
                        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role, institutional_id) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssss", $username, $email, $passwordHash, $fullName, $role, $institutionalId);
                    } else {
                        // General_User
                        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssss", $username, $email, $passwordHash, $fullName, $role);
                    }
                    
                    if ($stmt->execute()) {
                        $userId = $conn->insert_id;
                        logSystemAction('User Registration', 'users', $userId, "New $role registered");
                        header('Location: ' . BASE_URL . '/auth/login.php?success=registered&role=' . urlencode($role));
                        exit();
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                    $stmt->close();
                }
            }
            
            $stmt->close();
            closeDBConnection($conn);
        }
    }
    
    $selectedRole = $role;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --reg-bg: #f4f6f9;
            --reg-card: #ffffff;
            --reg-text: #1f2a37;
            --reg-muted: #6b7280;
            --reg-border: #e5e7eb;
            --reg-primary: #1e88e5;
            --reg-primary-dark: #1669c1;
            --reg-soft: #f7f9fc;
        }

        body.auth-page {
            display: block;
            min-height: 100vh;
            background: radial-gradient(1200px 600px at 10% 10%, #e9f2ff 0%, transparent 60%),
                        radial-gradient(1000px 500px at 90% 20%, #f3f7ff 0%, transparent 60%),
                        var(--reg-bg);
            color: var(--reg-text);
        }

        .reg-topbar {
            background: #fff;
            border-bottom: 1px solid var(--reg-border);
        }

        .reg-topbar-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .reg-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }

        .reg-logo i {
            color: var(--reg-primary);
        }

        .reg-nav {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 0.95rem;
            color: var(--reg-muted);
        }

        .reg-nav a {
            color: inherit;
            text-decoration: none;
        }

        .reg-nav .reg-login-btn {
            color: #fff;
            background: var(--reg-primary);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
        }

        .reg-hero {
            text-align: center;
            margin: 30px auto 18px;
            max-width: 640px;
            padding: 0 16px;
        }

        .reg-hero h1 {
            margin: 0 0 6px;
            font-size: 1.8rem;
        }

        .reg-hero p {
            margin: 0;
            color: var(--reg-muted);
            font-size: 0.98rem;
        }

        .auth-container {
            max-width: 900px;
            margin: 0 auto 50px;
            padding: 0 16px 50px;
        }

        .auth-card {
            background: var(--reg-card);
            border: 1px solid var(--reg-border);
            border-radius: 16px;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
            padding: 24px 26px;
        }

        .auth-header {
            display: none;
        }

        .reg-role-tabs {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            background: var(--reg-soft);
            border: 1px solid var(--reg-border);
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 18px;
        }

        .reg-role-tabs button {
            border: none;
            background: transparent;
            padding: 10px 8px;
            border-radius: 10px;
            font-weight: 600;
            color: var(--reg-muted);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .reg-role-tabs button.active {
            background: #fff;
            color: var(--reg-primary);
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
        }

        .reg-section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--reg-text);
            margin: 6px 0 10px;
        }

        .reg-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 18px;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
        }

        .form-group input,
        .form-group select {
            border-radius: 10px;
            border: 1px solid var(--reg-border);
            padding: 10px 12px;
            background: #fff;
        }

        .form-group small {
            color: var(--reg-muted);
        }

        .reg-footer-note {
            text-align: center;
            font-size: 0.8rem;
            color: var(--reg-muted);
            margin-top: 16px;
        }

        .btn.btn-primary.btn-block {
            background: var(--reg-primary);
            border-radius: 10px;
            font-weight: 600;
            padding: 12px 14px;
        }

        .btn.btn-primary.btn-block:hover {
            background: var(--reg-primary-dark);
        }

        .auth-footer {
            margin-top: 16px;
            text-align: center;
            color: var(--reg-muted);
        }

        @media (max-width: 760px) {
            .reg-grid {
                grid-template-columns: 1fr;
            }

            .reg-topbar-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .reg-nav {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
    <script>
        function showRoleForm() {
            const role = document.getElementById('role_select').value;
            const roleForms = document.querySelectorAll('.role-form');
            
            roleForms.forEach(form => {
                form.style.display = 'none';
                // Disable all inputs in this form section so they aren't validated or submitted
                form.querySelectorAll('input, select').forEach(input => {
                    input.disabled = true;
                });
            });

            if (role) {
                const activeForm = document.getElementById('role_' + role);
                if (activeForm) {
                    activeForm.style.display = 'block';
                    // Enable all inputs in the active form section
                    activeForm.querySelectorAll('input, select').forEach(input => {
                        input.disabled = false;
                    });
                }
                
                document.getElementById('role').value = role;
                document.querySelectorAll('.role-tab').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.role === role);
                });
            }
        }
    </script>
</head>
<body class="auth-page">
    <header class="reg-topbar">
        <div class="reg-topbar-inner">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="javascript:history.back()" class="btn-back" title="Go Back">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="reg-logo">
                    <i class="fas fa-layer-group"></i>
                    <span>IMRS</span>
                </div>
            </div>
            <nav class="reg-nav">
                <!-- <a href="<?php echo BASE_URL; ?>/index.php">Home</a> 
                <a href="<?php echo BASE_URL; ?>/public/about.php">About</a>
                <a href="<?php echo BASE_URL; ?>/public/contact.php">Contact</a> -->
                <a class="reg-login-btn" href="<?php echo BASE_URL; ?>/auth/login.php">Login</a>
            </nav>
        </div>
    </header>

    <section class="reg-hero">
        <h1>User Registration</h1>
        <p>Create your account to submit and track maintenance requests at Bahir Dar Institute of Technology.</p>
    </section>

    <div class="auth-container">
        <div class="auth-card">
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form" id="registerForm">
                <div class="reg-section-title">Select Your Role</div>
                <div class="reg-role-tabs" role="tablist">
                    <button type="button" class="role-tab <?php echo $selectedRole === 'Student' ? 'active' : ''; ?>" data-role="Student">Student</button>
                    <button type="button" class="role-tab <?php echo $selectedRole === 'Staff' ? 'active' : ''; ?>" data-role="Staff">Staff</button>
                    <button type="button" class="role-tab <?php echo $selectedRole === 'General_User' ? 'active' : ''; ?>" data-role="General_User">General User</button>
                </div>

                <select id="role_select" name="role_select" required style="display: none;">
                    <option value="">-- Select Role --</option>
                    <option value="Student" <?php echo $selectedRole === 'Student' ? 'selected' : ''; ?>>Student</option>
                    <option value="Staff" <?php echo $selectedRole === 'Staff' ? 'selected' : ''; ?>>Staff</option>
                    <option value="General_User" <?php echo $selectedRole === 'General_User' ? 'selected' : ''; ?>>General User</option>
                </select>
                <input type="hidden" id="role" name="role" value="<?php echo htmlspecialchars($selectedRole); ?>">

                <div id="role_Student" class="role-form" style="display: <?php echo $selectedRole === 'Student' ? 'block' : 'none'; ?>;">
                    <div class="reg-section-title">Student Details</div>
                    <div class="reg-grid">
                        <div class="form-group">
                            <label for="student_full_name">
                                <i class="fas fa-id-card"></i> Full Name *
                            </label>
                            <input type="text" id="student_full_name" name="full_name" required pattern="[A-Za-z\s]+" title="Full name must contain letters only.">
                        </div>

                        <div class="form-group">
                            <label for="student_username">
                                <i class="fas fa-user"></i> Username *
                            </label>
                            <input type="text" id="student_username" name="username" required>
                        </div>

                        <div class="form-group">
                            <label for="student_institutional_id">
                                <i class="fas fa-id-badge"></i> Institutional ID *
                            </label>
                            <input type="text" id="student_institutional_id" name="institutional_id" required
                                   pattern="BDU\d{7}" placeholder="BDU1234567" style="text-transform: uppercase;">
                            <small>Format: BDU followed by 7 digits (e.g., BDU1234567)</small>
                        </div>

                        <div class="form-group">
                            <label for="student_password">
                                <i class="fas fa-lock"></i> Password *
                            </label>
                            <input type="password" id="student_password" name="password" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label for="student_confirm_password">
                                <i class="fas fa-lock"></i> Confirm Password *
                            </label>
                            <input type="password" id="student_confirm_password" name="confirm_password" required minlength="6">
                        </div>
                    </div>
                </div>

                <div id="role_Staff" class="role-form" style="display: <?php echo $selectedRole === 'Staff' ? 'block' : 'none'; ?>;">
                    <div class="reg-section-title">Staff Details</div>
                    <div class="reg-grid">
                        <div class="form-group">
                            <label for="staff_full_name">
                                <i class="fas fa-id-card"></i> Full Name *
                            </label>
                            <input type="text" id="staff_full_name" name="full_name" required pattern="[A-Za-z\s]+" title="Full name must contain letters only.">
                        </div>

                        <div class="form-group">
                            <label for="staff_username">
                                <i class="fas fa-user"></i> Username *
                            </label>
                            <input type="text" id="staff_username" name="username" required>
                        </div>

                        <div class="form-group">
                            <label for="staff_institutional_id">
                                <i class="fas fa-id-badge"></i> Institutional ID *
                            </label>
                            <input type="text" id="staff_institutional_id" name="institutional_id" required
                                   pattern="BDU\d{7}" placeholder="BDU1234567" style="text-transform: uppercase;">
                            <small>Format: BDU followed by 7 digits (e.g., BDU1234567)</small>
                        </div>

                        <div class="form-group">
                            <label for="office_head">
                                <i class="fas fa-user-tie"></i> Office Head (Department Head) *
                            </label>
                            <input type="text" id="office_head" name="office_head" required placeholder="Name of your department head">
                        </div>

                        <div class="form-group">
                            <label for="staff_password">
                                <i class="fas fa-lock"></i> Password *
                            </label>
                            <input type="password" id="staff_password" name="password" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label for="staff_confirm_password">
                                <i class="fas fa-lock"></i> Confirm Password *
                            </label>
                            <input type="password" id="staff_confirm_password" name="confirm_password" required minlength="6">
                        </div>
                    </div>
                </div>

                <div id="role_General_User" class="role-form" style="display: <?php echo $selectedRole === 'General_User' ? 'block' : 'none'; ?>;">
                    <div class="reg-section-title">General User Details</div>
                    <div class="reg-grid">
                        <div class="form-group">
                            <label for="general_full_name">
                                <i class="fas fa-id-card"></i> Full Name *
                            </label>
                            <input type="text" id="general_full_name" name="full_name" required pattern="[A-Za-z\s]+" title="Full name must contain letters only.">
                        </div>

                        <div class="form-group">
                            <label for="general_username">
                                <i class="fas fa-user"></i> Username *
                            </label>
                            <input type="text" id="general_username" name="username" required>
                        </div>

                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i> Email *
                            </label>
                            <input type="email" id="email" name="email" required>
                        </div>

                        <div class="form-group">
                            <label for="general_password">
                                <i class="fas fa-lock"></i> Password *
                            </label>
                            <input type="password" id="general_password" name="password" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label for="general_confirm_password">
                                <i class="fas fa-lock"></i> Confirm Password *
                            </label>
                            <input type="password" id="general_confirm_password" name="confirm_password" required minlength="6">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="display: <?php echo $selectedRole ? 'block' : 'none'; ?>;">
                    <i class="fas fa-user-plus"></i> Register Account
                </button>

                <!-- <div class="reg-footer-note"> 
                    Secure institutional portal • 256-bit encryption
                </div> -->
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="<?php echo BASE_URL; ?>/auth/login.php">Login here</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // Show submit button when role is selected
        document.getElementById('role_select').addEventListener('change', function() {
            const submitBtn = document.querySelector('button[type="submit"]');
            if (this.value) {
                submitBtn.style.display = 'block';
            } else {
                submitBtn.style.display = 'none';
            }
        });

        document.querySelectorAll('.role-tab').forEach(btn => {
            btn.addEventListener('click', function() {
                const role = this.dataset.role;
                const select = document.getElementById('role_select');
                select.value = role;
                showRoleForm();
                updateFormFields();
                const submitBtn = document.querySelector('button[type="submit"]');
                submitBtn.style.display = 'block';
            });
        });
        
        // Auto-uppercase institutional ID
        document.querySelectorAll('input[name="institutional_id"]').forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        });
        
        // Show/hide fields based on role
        function updateFormFields() {
            const role = document.getElementById('role_select').value;
            const roleRoot = document.getElementById('role_' + role);
            const instIdField = roleRoot ? roleRoot.querySelector('input[name="institutional_id"]') : null;
            const emailField = roleRoot ? roleRoot.querySelector('input[name="email"]') : null;
            const officeHeadField = roleRoot ? roleRoot.querySelector('input[name="office_head"]') : null;
            
            if (role === 'Student' || role === 'Staff') {
                if (instIdField) instIdField.required = true;
                if (emailField) emailField.required = false;
                if (officeHeadField) {
                    officeHeadField.required = (role === 'Staff');
                    officeHeadField.parentElement.style.display = (role === 'Staff') ? 'block' : 'none';
                }
            } else if (role === 'General_User') {
                if (instIdField) instIdField.required = false;
                if (emailField) emailField.required = true;
                if (officeHeadField) {
                    officeHeadField.required = false;
                    officeHeadField.parentElement.style.display = 'none';
                }
            }
        }
        
        // Initialize state based on PHP-selected role
        showRoleForm();
        updateFormFields();
    </script>
</body>
</html>
