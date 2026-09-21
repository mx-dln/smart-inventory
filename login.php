<?php
require_once __DIR__ . '/config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle logout message
if (isset($_GET['logout'])) {
    $success = 'You have been logged out successfully.';
}

// Handle session timeout
if (isset($_GET['timeout'])) {
    $error = 'Your session has expired. Please login again.';
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCsrf($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.username = ? AND u.status = 'Active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['last_activity'] = time();
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Log audit
            logAudit($pdo, 'Login', 'Authentication', 'User ' . $username . ' logged in successfully.');
            
            // Flag to show welcome splash screen on dashboard
            $_SESSION['show_splash'] = true;
            $_SESSION['splash_user'] = $user['full_name'] ?? $user['username'];
            
            header('Location: index.php');
            exit;
        } else {
            // Distinguish between user not found vs wrong password
            if ($user) {
                $error = 'Wrong password. Try a different password.';
                logAudit($pdo, 'Login Failed', 'Authentication', 'Wrong password attempt for username: ' . $username);
            } else {
                $error = 'Username not found.';
                logAudit($pdo, 'Login Failed', 'Authentication', 'Login attempt with unknown username: ' . $username);
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .login-gradient {
            background: linear-gradient(135deg, #0f766e 0%, #1e293b 100%);
        }
    </style>
</head>
<body class="min-h-screen login-gradient flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="mx-auto mb-4">
                    <img src="assets/images/isu_logo.png" alt="ISU Logo" class="h-20 w-auto">
                </div>
                <h1 class="text-xl font-bold text-gray-900"><?php echo APP_NAME; ?></h1>
                <p class="text-sm text-gray-500 mt-1"><?php echo SCHOOL_NAME; ?></p>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo h($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo h($success); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" autocomplete="off">
                <?php echo csrfField(); ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
<input type="text" name="username" id="username" required autocomplete="off"
                                       class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                                       placeholder="Enter username">
                    </div>
                </div>
<div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                                <input type="password" name="password" id="password" required autocomplete="new-password"
                                       class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                                       placeholder="Enter password">
                                <button type="button" id="togglePassword" tabindex="-1"
                                        style="position:absolute;right:8px;top:50%;transform:translateY(-50%);width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:transparent;border:none;cursor:pointer;color:#9ca3af;padding:0;">
                                    <i class="fas fa-eye" id="passwordIcon" style="font-size:16px;"></i>
                                </button>
                            </div>
                        </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 rounded-lg transition-colors">
                    <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                </button>
            </form>
        </div>
    </div>

    <!-- Login Splash Screen (shows on submit) -->
    <style>
    @keyframes splashBounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
    #loginSplash{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;background:linear-gradient(135deg,#059669 0%,#0f172a 100%)}
    #loginSplash.show{display:flex}
    #loginSplash .dot{width:10px;height:10px;background:#fff;border-radius:9999px;animation:splashBounce .8s ease infinite}
    #loginSplash .logo{height:96px;width:auto;margin:0 auto 24px}
    #loginSplash h1{font-size:1.5rem;font-weight:700;color:#fff;margin-bottom:8px}
    #loginSplash .sub{color:#a7f3d0;font-size:.875rem;margin-bottom:24px}
    #loginSplash .text{color:#fff;font-size:1rem;font-weight:500;margin-top:24px}
    </style>
    <div id="loginSplash">
        <div style="text-align:center;padding:0 24px">
            <img src="assets/images/isu_logo.png" alt="ISU Logo" class="logo">
            <h1><?php echo APP_NAME; ?></h1>
            <p class="sub"><?php echo SCHOOL_NAME; ?></p>
            <div style="display:flex;justify-content:center;gap:8px">
                <span class="dot" style="animation-delay:0ms"></span>
                <span class="dot" style="animation-delay:150ms"></span>
                <span class="dot" style="animation-delay:300ms"></span>
            </div>
            <p class="text">Signing in...</p>
        </div>
    </div>

    <script>
    // Show splash immediately when submitting login
    document.addEventListener('DOMContentLoaded', function() {
        var toggleBtn = document.getElementById('togglePassword');
        var loginForm = document.getElementById('loginForm');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                var input = document.getElementById('password');
                var icon = document.getElementById('passwordIcon');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }

        if (loginForm) {
            loginForm.addEventListener('submit', function() {
                document.getElementById('loginSplash').classList.add('show');
            });
        }
    });
    </script>
</body>
</html>
