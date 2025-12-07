<?php
session_start();
include 'assets/functions.php';

// Check if firefighter is logged in
if (!isset($_SESSION['firefighter_id'])) {
    header('Location: login.php');
    exit;
}

// Get firefighter data
$db = getDB();
$stmt = $db->prepare("SELECT * FROM firefighters WHERE id = :id");
$stmt->bindValue(':id', $_SESSION['firefighter_id'], SQLITE3_INTEGER);
$result = $stmt->execute();

if ($result) {
    $firefighter = $result->fetchArray(SQLITE3_ASSOC);
} else {
    $firefighter = null;
}

if (!$firefighter) {
    // Invalid session, redirect to login
    session_destroy();
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firefighter Alert Receiver</title>


    <link rel="stylesheet" href="assets/firefighter.css">
</head>
<body>
    <div class="header">
        <div class="logo">
            <div class="logo-icon">🚒</div>
            <h1>Firefighter Alert</h1>
        </div>
        <div class="header-right">
            <div class="firefighter-info">
                <span class="firefighter-name"><?php echo htmlspecialchars($firefighter['name'] ?? 'Unknown'); ?></span>
                <span class="station-info">Station <?php echo htmlspecialchars($firefighter['station'] ?? 'N/A'); ?></span>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
            <div class="status-badge" id="statusBadge">● STANDBY</div>
        </div>
    </div>

    <div class="container">
        <!-- Info Bar -->
        <div class="info-bar">
            <div class="info-item">👨‍🚒 <span><?php echo htmlspecialchars($firefighter['name']); ?></span></div>
            <div class="info-item">📅 <span id="currentDate">-</span></div>
            <div class="info-item">🕐 <span id="currentTime">-</span></div>
            <div class="info-item">🏢 <span>Station <?php echo htmlspecialchars($firefighter['station']); ?></span></div>
        </div>

        <!-- No Alert State -->
        <div class="no-alert" id="noAlert">
            <div class="pulse-ring">
                <div class="no-alert-icon">✅</div>
            </div>
            <h2>All Clear</h2>
            <p>No active alerts. Stay ready for dispatch.</p>
        </div>

        <!-- Active Alert -->
        <div class="alert-active" id="alertActive">
            <div class="alert-card">
                <div class="alert-icon" id="alertIcon">🔥</div>
                <div class="alert-title" id="alertTitle">FIRE ALERT</div>
                <div class="alert-subtitle">Immediate response required</div>
                <div class="alert-details">
                    <div class="alert-detail-row">
                        <div class="icon">📍</div>
                        <div>
                            <div class="label">Location</div>
                            <div class="value" id="alertLocation">-</div>
                        </div>
                    </div>
                    <div class="alert-detail-row">
                        <div class="icon">🏢</div>
                        <div>
                            <div class="label">Area</div>
                            <div class="value" id="alertArea">-</div>
                        </div>
                    </div>
                    <div class="alert-detail-row">
                        <div class="icon">📊</div>
                        <div>
                            <div class="label">Confidence</div>
                            <div class="value" id="alertConfidence">-</div>
                        </div>
                    </div>
                </div>
                <div class="alert-time" id="alertTime">⏱️ Received just now</div>
                <div class="alert-actions">
                    <button class="btn btn-respond" onclick="respondToAlert()">🚒 Responding</button>
                    <button class="btn btn-acknowledge" onclick="acknowledgeAlert()">✓ Acknowledge</button>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-box">
                <div class="value" id="statResponded">0</div>
                <div class="label">Responded</div>
            </div>
            <div class="stat-box">
                <div class="value" id="statToday">0</div>
                <div class="label">Today</div>
            </div>
            <div class="stat-box">
                <div class="value" id="statAvgTime">-</div>
                <div class="label">Avg Time</div>
            </div>
        </div>

        <!-- History Section -->
        <div class="history-section">
            <div class="section-title">📋 Recent Alerts</div>
            <div id="historyList">
                <div class="empty-history">
                    <div class="icon">📭</div>
                    <p>No recent alerts</p>
                </div>
            </div>
        </div>

        <div class="footer">Fire Detection System v1.0 • Stay Safe</div>
    </div>

    <button class="float-btn" id="soundToggle" onclick="toggleSound()">🔔</button>
    <button class="demo-btn" onclick="simulateAlert()">⚡ Test Alert</button>



        <script src="assets/firefighter.js"></script>

</body>
</html>