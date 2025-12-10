<?php
session_start();

// Check if user is logged in as firefighter
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Optional: Restrict to firefighter only (comment out if you want both types to access)
if ($_SESSION['user_type'] !== 'firefighter') {
    header('Location: index.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

require_once 'assets/functions.php';
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
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="user-badge" style="color: #ffa502; font-size: 14px;">
                👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
            <a href="?logout=1" class="logout-link" style="color: #e94560; text-decoration: none; font-weight: 600; font-size: 14px;">Logout</a>
            <div class="status-badge" id="statusBadge">● STANDBY</div>
        </div>
    </div>

    <div class="dashboard-grid">
        <!-- Left Sidebar -->
        <aside class="left-sidebar">
            <!-- Info Bar -->
            <div class="info-bar">
                <div class="info-item">📅 <span id="currentDate">-</span></div>
                <div class="info-item">🕐 <span id="currentTime">-</span></div>
                <div class="info-item">🏢 <span>Station 1</span></div>
            </div>

            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="stat-box">
                    <div class="stat-icon">✅</div>
                    <div class="value" id="statResponded">0</div>
                    <div class="label">Responded</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon">🚨</div>
                    <div class="value" id="statToday">0</div>
                    <div class="label">Today</div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon">⏱️</div>
                    <div class="value" id="statAvgTime">-</div>
                    <div class="label">Avg Time</div>
                </div>
            </div>

            <!-- Weather Section -->
            <div class="weather-section">
                <div class="section-title">🌤️ Dulag Weather</div>
                <div class="weather-grid">
                    <div class="weather-item">
                        <div class="weather-icon">🌡️</div>
                        <div class="weather-value" id="weatherTemp">--°C</div>
                        <div class="weather-label">Temperature</div>
                    </div>
                    <div class="weather-item">
                        <div class="weather-icon">💧</div>
                        <div class="weather-value" id="weatherHumidity">--%</div>
                        <div class="weather-label">Humidity</div>
                    </div>
                    <div class="weather-item">
                        <div class="weather-icon" id="weatherIcon">☀️</div>
                        <div class="weather-value" id="weatherDesc">Loading...</div>
                        <div class="weather-label">Conditions</div>
                    </div>
                    <div class="weather-item">
                        <div class="weather-icon">💨</div>
                        <div class="weather-value" id="weatherWind">-- km/h</div>
                        <div class="weather-label">Wind Speed</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
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
                        <button class="btn btn-acknowledge" onclick="acknowledgeAlert()">✔ Acknowledge</button>
                    </div>
                </div>
            </div>
        </main>

        <!-- Right Sidebar -->
        <aside class="right-sidebar">
            <!-- History Section -->
            <div class="history-section">
                <div class="section-title">📋 Recent Alerts</div>
                <div id="historyList">
                    <div class="empty-history">
                        <div class="icon">🔭</div>
                        <p>No recent alerts</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <div class="footer">Fire Detection System v1.0 • Stay Safe • Dulag, Leyte</div>

    <button class="float-btn" id="soundToggle" onclick="toggleSound()">🔔</button>
    <button class="demo-btn" onclick="simulateAlert()">⚡ Test Alert</button>

    <script src="assets/firefighter.js"></script>
</body>
</html>