<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fire Detection System</title>
    <link rel="stylesheet" href="assets/index.css">
    <link rel="stylesheet" href="assets/auth.css">
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

                <button type="submit" class="btn btn-primary btn-auth">Login</button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
            </div>
        </div>
    </div>

</body>
</html>
