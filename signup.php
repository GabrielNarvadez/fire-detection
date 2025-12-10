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

// Handle signup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';

    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password) || empty($user_type)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $users = json_decode(file_get_contents($usersFile), true) ?: [];
        
        // Check if email already exists
        $emailExists = false;
        foreach ($users as $user) {
            if ($user['email'] === $email) {
                $emailExists = true;
                break;
            }
        }

        if ($emailExists) {
            $error = 'An account with this email already exists.';
        } else {
            // Generate new ID
            $maxId = 0;
            foreach ($users as $user) {
                if ($user['id'] > $maxId) {
                    $maxId = $user['id'];
                }
            }

            // Create new user
            $newUser = [
                'id' => $maxId + 1,
                'fullname' => $fullname,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'user_type' => $user_type,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $users[] = $newUser;
            
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT))) {
                header('Location: login.php?registered=1');
                exit;
            } else {
                $error = 'Failed to create account. Please try again.';
            }
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
    <title>Sign Up - Fire Detection System</title>
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
            margin-bottom: 18px;
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
        .approval-note {
            background: rgba(255, 165, 2, 0.15);
            border: 1px solid rgba(255, 165, 2, 0.3);
            border-radius: 10px;
            padding: 12px;
            margin-top: 15px;
            font-size: 13px;
            color: #ffa502;
        }
        .approval-note strong {
            color: #ffc048;
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <div class="logo-icon">🚀</div>
                </div>
                <h1>Create Your Account</h1>
                <p>Join the fire and smoke detection team</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" placeholder="e.g., John Doe" required 
                           value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="e.g., user@example.com" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Create a strong password (min 6 chars)" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required>
                </div>
                <div class="form-group">
                    <label for="user_type">Register as</label>
                    <select id="user_type" name="user_type" required>
                        <option value="personnel" <?php echo (($_POST['user_type'] ?? '') === 'personnel') ? 'selected' : ''; ?>>Personnel</option>
                        <option value="firefighter" <?php echo (($_POST['user_type'] ?? '') === 'firefighter') ? 'selected' : ''; ?>>Firefighter</option>
                    </select>
                </div>

                <button type="submit" class="btn-auth">Create Account</button>
            </form>

            <div class="approval-note" id="approvalNote" style="display: none;">
                <strong>Note:</strong> Firefighter accounts will have access to the Firefighter Alert Receiver dashboard.
            </div>

            <div class="auth-footer">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>

    <script>
        // Show approval note when firefighter is selected
        const userTypeSelect = document.getElementById('user_type');
        const approvalNote = document.getElementById('approvalNote');
        
        function updateNote() {
            if (userTypeSelect.value === 'firefighter') {
                approvalNote.style.display = 'block';
            } else {
                approvalNote.style.display = 'none';
            }
        }
        
        userTypeSelect.addEventListener('change', updateNote);
        updateNote(); // Check on page load
    </script>

</body>
</html>