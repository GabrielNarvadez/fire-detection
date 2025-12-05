<?php
session_start();
include 'assets/functions.php';

// Check if already logged in
if (isset($_SESSION['firefighter_id'])) {
    header('Location: firefighter.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name && $phone) {
        // Authenticate firefighter
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM firefighters WHERE name = :name AND phone = :phone");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($result) {
            $firefighter = $result->fetchArray(SQLITE3_ASSOC);
            if ($firefighter) {
                // Login successful
                $_SESSION['firefighter_id'] = $firefighter['id'];
                $_SESSION['firefighter_name'] = $firefighter['name'];
                header('Location: firefighter.php');
                exit;
            }
        }
        $message = 'Invalid name or phone number. Please try again.';
    } else {
        $message = 'Please enter both name and phone number.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firefighter Login - Fire Detection System</title>
    <link rel="stylesheet" href="assets/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <div class="logo-icon">🚒</div>
                <h1>Firefighter Portal</h1>
                <p>Fire Detection System</p>
            </div>

            <?php if ($message): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form class="login-form" method="POST" action="">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required
                           placeholder="Enter your full name"
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" required
                           placeholder="Enter your phone number"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn-login">Login</button>
            </form>

            <div class="back-link">
                <a href="index.php">← Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
