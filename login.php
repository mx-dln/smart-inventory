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
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
            logAudit($pdo, 'Login Failed', 'Authentication', 'Failed login attempt for username: ' . $username);
        }
    }
}

// Get demo accounts
$demoAccounts = [
    ['role' => 'Administrator', 'username' => 'admin', 'password' => 'admin123', 'icon' => 'fa-user-shield', 'color' => 'emerald'],
    ['role' => 'Inventory Manager', 'username' => 'inventory', 'password' => 'inventory123', 'icon' => 'fa-boxes', 'color' => 'blue'],
    ['role' => 'Production Staff', 'username' => 'production', 'password' => 'production123', 'icon' => 'fa-industry', 'color' => 'purple'],
    ['role' => 'Faculty', 'username' => 'faculty', 'password' => 'faculty123', 'icon' => 'fa-chalkboard-teacher', 'color' => 'amber'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .login-gradient {
            background: linear-gradient(135deg, #0f766e 0%, #1e293b 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -8px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body class="min-h-screen login-gradient flex items-center justify-center p-4">
    <div class="w-full max-w-5xl">
        <div class="grid lg:grid-cols-5 gap-8 items-start">
            <!-- Login Form -->
            <div class="lg:col-span-2">
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

                    <form method="POST" action="" id="loginForm">
                        <?php echo csrfField(); ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <div class="relative">
                                <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
                                <input type="text" name="username" id="username" required
                                       class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                                       placeholder="Enter username">
                            </div>
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <div class="relative">
                                <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                                <input type="password" name="password" id="password" required
                                       class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm"
                                       placeholder="Enter password">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 rounded-lg transition-colors">
                            <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick Login Accounts -->
            <div class="lg:col-span-3">
                <div class="text-center mb-6">
                    <h2 class="text-white text-lg font-semibold">Quick Login</h2>
                    <p class="text-emerald-200 text-sm">Click a role to auto-fill credentials</p>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <?php foreach ($demoAccounts as $account): ?>
                    <div class="bg-white/10 backdrop-blur-sm rounded-xl p-5 card-hover cursor-pointer border border-white/20
                                hover:bg-white/20 transition-all"
                         onclick="fillCredentials('<?php echo $account['username']; ?>', '<?php echo $account['password']; ?>')">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas <?php echo $account['icon']; ?> text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-medium text-sm"><?php echo $account['role']; ?></h3>
                                <p class="text-emerald-200 text-xs">Click to login</p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-8 text-center">
                    <p class="text-emerald-200 text-xs">
                        <i class="fas fa-info-circle mr-1"></i>
                        Demo accounts for evaluation purposes only
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
    function fillCredentials(username, password) {
        document.getElementById('username').value = username;
        document.getElementById('password').value = password;
        // Add a subtle animation
        document.getElementById('username').style.borderColor = '#10b981';
        document.getElementById('password').style.borderColor = '#10b981';
        setTimeout(() => {
            document.getElementById('username').style.borderColor = '';
            document.getElementById('password').style.borderColor = '';
        }, 2000);
    }
    </script>
</body>
</html>
