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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        $conn = getDBConnection();
        
        // Allow all registered users to login (Student, Staff, Proctor, Office Head, Maintenance Manager, Team, Admin, General User)
        $stmt = $conn->prepare("SELECT user_id, username, email, password_hash, full_name, role, status FROM users WHERE (username = ? OR email = ? OR institutional_id = ?)");
        $stmt->bind_param("sss", $username, $username, $username);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['status'] === 'Inactive') {
                $error = 'Your account has been deactivated. Please contact administrator.';
            } elseif (verifyPassword($password, $user['password_hash'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                // Log login
                logSystemAction('User Login', 'users', $user['user_id'], 'User logged in successfully');
                
                // Redirect based on role
                header('Location: ' . BASE_URL . '/index.php');
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
        
        $stmt->close();
        closeDBConnection($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --login-bg: #f4f6f9;
            --login-card: #ffffff;
            --login-text: #1f2a37;
            --login-muted: #6b7280;
            --login-border: #e5e7eb;
            --login-primary: #1e88e5;
            --login-primary-dark: #1669c1;
            --login-soft: #f7f9fc;
        }

        body.auth-page {
            display: block;
            min-height: 100vh;
            background: radial-gradient(1200px 600px at 10% 10%, #e9f2ff 0%, transparent 60%),
                        radial-gradient(1000px 500px at 90% 20%, #f3f7ff 0%, transparent 60%),
                        var(--login-bg);
            color: var(--login-text);
        }

        .login-topbar {
            background: #fff;
            border-bottom: 1px solid var(--login-border);
        }

        .login-topbar-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .login-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }

        .login-logo i {
            color: var(--login-primary);
        }

        .login-nav {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 0.95rem;
            color: var(--login-muted);
        }

        .login-nav a {
            color: inherit;
            text-decoration: none;
        }

        .login-nav .login-register-btn {
            color: #fff;
            background: var(--login-primary);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
        }

        .login-hero {
            text-align: center;
            margin: 30px auto 18px;
            max-width: 640px;
            padding: 0 16px;
        }

        .login-hero h1 {
            margin: 0 0 6px;
            font-size: 1.8rem;
        }

        .login-hero p {
            margin: 0;
            color: var(--login-muted);
            font-size: 0.98rem;
        }

        .auth-container {
            max-width: 500px;
            margin: 0 auto 50px;
            padding: 0 16px 50px;
        }

        .auth-card {
            background: var(--login-card);
            border: 1px solid var(--login-border);
            border-radius: 16px;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
            padding: 30px 32px;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
        }

        .form-group input {
            border-radius: 10px;
            border: 1px solid var(--login-border);
            padding: 12px 14px;
            background: #fff;
            width: 100%;
            margin-top: 6px;
        }

        .btn.btn-primary.btn-block {
            background: var(--login-primary);
            border-radius: 10px;
            font-weight: 600;
            padding: 12px 14px;
            width: 100%;
            border: none;
            color: white;
            cursor: pointer;
            margin-top: 20px;
            font-size: 1rem;
        }

        .btn.btn-primary.btn-block:hover {
            background: var(--login-primary-dark);
        }

        .login-guest-btn {
            margin-top: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            background: #fff;
            color: var(--login-primary);
            border: 1px solid var(--login-border);
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer-note {
            text-align: center;
            font-size: 0.8rem;
            color: var(--login-muted);
            margin-top: 20px;
        }

        .auth-footer {
            margin-top: 20px;
            text-align: center;
            color: var(--login-muted);
        }

        .auth-footer a {
            color: var(--login-primary);
            text-decoration: none;
            font-weight: 600;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        @media (max-width: 760px) {
            .login-topbar-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .login-nav {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body class="auth-page">
    <header class="login-topbar">
        <div class="login-topbar-inner">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="login-logo">
                    <i class="fas fa-layer-group"></i>
                    <span>IMRS</span>
                </div>
            </div>
            <nav class="login-nav">
                <!-- <a href="<?php echo BASE_URL; ?>/index.php">Home</a> 
                <a href="<?php echo BASE_URL; ?>/public/about.php">About</a>
                <a href="<?php echo BASE_URL; ?>/public/contact.php">Contact</a> -->
                <a class="login-register-btn" href="<?php echo BASE_URL; ?>/auth/register.php">Register</a>
            </nav>
        </div>
    </header>

    <section class="login-hero">
        <h1>IMRS Secure Login</h1>
        <p>Bahir Dar Institute of Technology Maintenance System</p>
    </section>

    <div class="auth-container">
        <div class="auth-card">
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success']) && $_GET['success'] === 'registered'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Registration successful! Please login with your credentials.
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form" id="loginForm">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Username, Email, or Institutional ID
                    </label>
                    <input type="text" id="username" name="username" placeholder="Enter your credentials" required autofocus>
                    <small style="color: var(--login-muted); display: block; margin-top: 4px;">
                        Students/Staff use Institutional ID (BDU1234567) or username
                    </small>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>

                <a class="login-guest-btn" href="<?php echo BASE_URL; ?>/public/submit_request.php">
                    <i class="fas fa-user-clock"></i> Continue as Guest
                </a>

                <!-- <div class="login-footer-note"> 
                    BIT standard encryption
                </div>-->
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="<?php echo BASE_URL; ?>/auth/register.php">Register here</a></p>
                <!-- <p style="margin-top: 10px;"><a href="<?php echo BASE_URL; ?>/public/submit_request.php">Submit request without registration</a></p> -->
            </div>
        </div>
    </div>
</body>
</html>