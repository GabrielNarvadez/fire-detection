<?php
session_start();

// Users JSON file path
$usersFile = __DIR__ . '/users.json';

// Initialize users file if it doesn't exist
if (!file_exists($usersFile)) {
    $defaultUsers = [
        [
            'id' => 1,
            'fullname' => 'Admin User',
            'email' => 'admin@fire.com',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'user_type' => 'personnel',
            'created_at' => date('Y-m-d H:i:s')
        ],
        [
            'id' => 2,
            'fullname' => 'Firefighter User',
            'email' => 'firefighter@fire.com',
            'password' => password_hash('fire123', PASSWORD_DEFAULT),
            'user_type' => 'firefighter',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    file_put_contents($usersFile, json_encode($defaultUsers, JSON_PRETTY_PRINT));
}

$error = '';
$success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';

    if (empty($email) || empty($password) || empty($user_type)) {
        $error = 'Please fill in all fields.';
    } else {
        $users = json_decode(file_get_contents($usersFile), true) ?: [];
        $foundUser = null;

        foreach ($users as $user) {
            if ($user['email'] === $email && $user['user_type'] === $user_type) {
                if (password_verify($password, $user['password'])) {
                    $foundUser = $user;
                    break;
                }
            }
        }

        if ($foundUser) {
            $_SESSION['user_id'] = $foundUser['id'];
            $_SESSION['user_name'] = $foundUser['fullname'];
            $_SESSION['user_email'] = $foundUser['email'];
            $_SESSION['user_type'] = $foundUser['user_type'];
            $_SESSION['logged_in'] = true;

            // Redirect based on user type
            if ($foundUser['user_type'] === 'firefighter') {
                header('Location: firefighter.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'Invalid email, password, or user type.';
        }
    }
}

// Check if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['user_type'] === 'firefighter') {
        header('Location: firefighter.php');
    } else {
        header('Location: index.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fire Detection System</title>
    <link rel="stylesheet" href="assets/index.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .auth-container {
            width: 100%;
            max-width: 420px;
        }
        .auth-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        }
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .logo-icon {
            font-size: 60px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .auth-header h1 {
            color: #fff;
            font-size: 24px;
            margin-bottom: 8px;
        }
        .auth-header p {
            color: #888;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #ccc;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #e94560;
            background: rgba(255, 255, 255, 0.12);
        }
        .form-group input::placeholder {
            color: #666;
        }
        .form-group select option {
            background: #1a1a2e;
            color: #fff;
        }
        .btn-auth {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #e94560, #ff6b6b);
            border: none;
            border-radius: 10px;
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(233, 69, 96, 0.4);
        }
        .auth-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .auth-footer p {
            color: #888;
            font-size: 14px;
        }
        .auth-footer a {
            color: #e94560;
            text-decoration: none;
            font-weight: 600;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
        .error-message {
            background: rgba(233, 69, 96, 0.2);
            border: 1px solid #e94560;
            color: #ff6b6b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        .success-message {
            background: rgba(46, 213, 115, 0.2);
            border: 1px solid #2ed573;
            color: #2ed573;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        .demo-credentials {
            background: rgba(102, 126, 234, 0.15);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        .demo-credentials h4 {
            color: #667eea;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .demo-credentials p {
            color: #888;
            font-size: 12px;
            margin: 5px 0;
        }
        .demo-credentials code {
            color: #e94560;
            background: rgba(233, 69, 96, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <div class="logo-icon">🔥</div>
                </div>
                <h1>Welcome Back</h1>
                <p>Sign in to access the Command Center</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
                <div class="success-message">Account created successfully! Please log in.</div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="e.g., user@example.com" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="form-group">
                    <label for="user_type">Sign in as</label>
                    <select id="user_type" name="user_type" required>
                        <option value="personnel">Personnel</option>
                        <option value="firefighter">Firefighter</option>
                    </select>
                </div>

                <button type="submit" class="btn-auth">Login</button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
            </div>

            <div class="demo-credentials">
                <h4>🔑 Demo Credentials</h4>
                <p><strong>Personnel:</strong> <code>admin@fire.com</code> / <code>admin123</code></p>
                <p><strong>Firefighter:</strong> <code>firefighter@fire.com</code> / <code>fire123</code></p>
            </div>
        </div>
    </div>

</body>
</html>