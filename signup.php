<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Fire Detection System</title>
    <link rel="stylesheet" href="assets/index.css">
    <link rel="stylesheet" href="assets/auth.css">
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

            <form action="" method="POST">
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" placeholder="e.g., John Doe" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="e.g., user@example.com" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Create a strong password" required>
                </div>
                 <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required>
                </div>
                <div class="form-group">
                    <label for="user_type">Register as</label>
                    <select id="user_type" name="user_type" required>
                        <option value="personnel">Personnel</option>
                        <option value="firefighter">Firefighter</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-auth">Create Account</button>
            </form>

            <div class="approval-note" id="approvalNote" style="display: none;">
                <strong>Note:</strong> Firefighter accounts require administrator approval. You will be notified once your account is activated.
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
        userTypeSelect.addEventListener('change', function() {
            if (this.value === 'firefighter') {
                approvalNote.style.display = 'block';
            } else {
                approvalNote.style.display = 'none';
            }
        });
    </script>

</body>
</html>
