<?php 
session_start();

// Check if user is logged in as personnel
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Optional: Restrict to personnel only (comment out if you want both types to access)
if ($_SESSION['user_type'] !== 'personnel') {
    header('Location: firefighter.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

include 'assets/functions.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fire & Smoke Detection Command Center</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


      <link rel="stylesheet" href="assets/index.css">

</head>
<body>
    <!-- Emergency Modal -->
    <div class="modal" id="emergencyModal">
        <div class="emergency-content">
            <div style="font-size: 72px;">🔥</div>
            <h2>FIRE DETECTED!</h2>
            <p><strong>Location:</strong> <span id="emergencyLocation"></span></p>
            <p><strong>Camera:</strong> <span id="emergencyCamera"></span></p>
            <p><strong>Confidence:</strong> <span id="emergencyConfidence"></span></p>
            <div class="modal-actions">
                <button class="btn btn-primary" style="background: #fff; color: #e94560;" onclick="notifyNearestStations()">SELECT FIREFIGHTERS TO NOTIFY</button>
                <button class="btn btn-secondary" style="border-color: #fff;" onclick="closeEmergency()">ACKNOWLEDGE</button>
            </div>
        </div>
    </div>

    <!-- Notification Selection Modal -->
    <div class="modal" id="notificationModal">
        <div class="modal-content" style="max-width: 700px; max-height: 80vh; overflow-y: auto;">
            <h2 style="margin-bottom: 20px;">📞 Select Who to Notify</h2>
            <div style="text-align: left; margin: 20px 0;">
                <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: rgba(102, 126, 234, 0.2); border-radius: 8px; margin-bottom: 15px; cursor: pointer;">
                    <input type="checkbox" id="notifyAll" onchange="toggleNotifyAll()" style="width: 20px; height: 20px;">
                    <strong style="font-size: 16px;">NOTIFY ALL FIREFIGHTERS</strong>
                </label>
                <div id="firefighterCheckboxes"></div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-success" onclick="sendNotifications()">📱 SEND SMS NOTIFICATIONS</button>
                <button class="btn btn-secondary" onclick="closeNotificationModal()">CANCEL</button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Firefighter Modal -->
    <div class="modal" id="firefighterModal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;" id="firefighterModalTitle">Add Firefighter</h2>
            <div class="form-group">
                <label>Name:</label>
                <input type="text" id="firefighterName" placeholder="Enter full name">
            </div>
            <div class="form-group">
                <label>Phone Number:</label>
                <input type="tel" id="firefighterPhone" placeholder="+63-917-123-4567">
            </div>
            <div class="form-group">
                <label>Station:</label>
                <select id="firefighterStation">
                    <option value="1">Station 1</option>
                    <option value="2">Station 2</option>
                </select>
            </div>
            <div class="modal-actions">
                <button class="btn btn-primary" onclick="saveFirefighter()">💾 SAVE</button>
                <button class="btn btn-secondary" onclick="closeFirefighterModal()">CANCEL</button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Personnel Modal -->
    <div class="modal" id="personnelModal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;" id="personnelModalTitle">Add Personnel</h2>
            <div class="form-group">
                <label>Name:</label>
                <input type="text" id="personnelName" placeholder="Enter full name">
            </div>
            <div class="form-group">
                <label>Role:</label>
                <input type="text" id="personnelRole" placeholder="e.g., System Administrator">
            </div>
            <div class="form-group">
                <label>Type:</label>
                <select id="personnelType">
                    <option value="admin">Admin</option>
                    <option value="firefighter">Firefighter</option>
                    <option value="operator">Operator</option>
                    <option value="technician">Technician</option>
                </select>
            </div>
            <div class="form-group">
                <label>Phone (optional):</label>
                <input type="tel" id="personnelPhone" placeholder="+63-917-123-4567">
            </div>
            <div class="form-group">
                <label>Station (optional):</label>
                <select id="personnelStation">
                    <option value="">No Station</option>
                    <option value="1">Station 1</option>
                    <option value="2">Station 2</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status:</label>
                <select id="personnelStatus">
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                </select>
            </div>
            <div class="modal-actions">
                <button class="btn btn-primary" onclick="savePersonnel()">💾 SAVE</button>
                <button class="btn btn-secondary" onclick="closePersonnelModal()">CANCEL</button>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <div class="logo">
                <div class="logo-icon">🔥</div>
                <h1>FIRE & SMOKE DETECTION COMMAND CENTER</h1>
            </div>
            <div class="system-status">
                <div class="status-badge status-operational" id="systemStatus">SYSTEM OPERATIONAL</div>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="user-info" style="color: #888; font-size: 14px;">
                👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                <a href="?logout=1" style="color: #e94560; margin-left: 15px; text-decoration: none; font-weight: 600;">Logout</a>
            </div>
            <div class="datetime" id="datetime"></div>
        </div>
    </div>

    <!-- Main Dashboard -->
    <div class="container">
        <!-- Stats Row -->
        <div class="dashboard-grid">
            <div class="panel stat-card">
                <div class="panel-header">
                    <span class="panel-title">Active Cameras</span>
                </div>
                <div class="stat-value" id="activeCameras">0</div>
                <div class="stat-label">Online / 2 Total</div>
                <div class="stat-change">System Status</div>
            </div>

            <div class="panel stat-card">
                <div class="panel-header">
                    <span class="panel-title">Detections Today</span>
                </div>
                <div class="stat-value" id="detectionsToday">0</div>
                <div class="stat-label">Fire & Smoke Events</div>
                <div class="stat-change" id="detectionChange">No detections</div>
            </div>

            <div class="panel stat-card">
                <div class="panel-header">
                    <span class="panel-title">Avg Response Time</span>
                </div>
                <div class="stat-value" id="avgResponse">3.2</div>
                <div class="stat-label">Minutes</div>
                <div class="stat-change">Target: < 5 min</div>
            </div>

            <div class="panel stat-card">
                <div class="panel-header">
                    <span class="panel-title">Personnel Online</span>
                </div>
                <div class="stat-value" id="personnelOnline">0</div>
                <div class="stat-label">Firefighters & Admins</div>
                <div class="stat-change">Ready to Respond</div>
            </div>
        </div>

        <!-- Alerts -->
        <div class="dashboard-grid">
            <div class="panel alert-panel">
                <div class="panel-header">
                    <span class="panel-title">🚨 Active Alerts</span>
                </div>
                <div id="alertsList"></div>
            </div>
        </div>

        <!-- Management Panel (Firefighters & Personnel) -->
        <div class="dashboard-grid">
            <div class="panel" style="grid-column: span 12;">
                <div class="panel-header">
                    <span class="panel-title">👥 Team Management</span>
                </div>
                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('firefighters')">👨‍🚒 Firefighters</button>
                    <button class="tab-btn" onclick="switchTab('personnel')">👤 Personnel</button>
                </div>
                
                <!-- Firefighters Tab -->
                <div class="tab-content active" id="tab-firefighters">
                    <div style="margin-bottom: 15px;">
                        <button class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;" onclick="showAddFirefighter()">+ Add Firefighter</button>
                    </div>
                    <div class="management-list" id="firefighterList"></div>
                </div>
                
                <!-- Personnel Tab -->
                <div class="tab-content" id="tab-personnel">
                    <div style="margin-bottom: 15px;">
                        <button class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;" onclick="showAddPersonnel()">+ Add Personnel</button>
                    </div>
                    <div class="management-list" id="personnelManagementList"></div>
                </div>
            </div>
        </div>

        <!-- Cameras -->
        <div class="dashboard-grid">
            <div class="panel camera-panel">
                <div class="panel-header">
                    <span class="panel-title">📹 Camera 1 - Visual ML</span>
                    <span id="cam1Status" style="color: #888;">● OFFLINE</span>
                </div>
                <div class="camera-feed">
                    <img id="camera1Feed" src="camera_frames/camera1_live.jpg" 
                         style="width: 100%; height: 100%; object-fit: cover;" 
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: #666;">
                        No camera feed
                    </div>
                </div>
                <div style="font-size: 12px; color: #888;">EVSU Campus</div>
            </div>

            <div class="panel camera-panel">
                <div class="panel-header">
                    <span class="panel-title">🌡️ Camera 2 - Thermal</span>
                    <span id="cam2Status" style="color: #888;">● OFFLINE</span>
                </div>
                <div class="camera-feed">
                    <img id="camera2Feed" src="camera_frames/camera2_live.jpg" 
                         style="width: 100%; height: 100%; object-fit: cover;" 
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: #666;">
                        No camera feed
                    </div>
                </div>
                <div style="font-size: 12px; color: #888;">EVSU Campus</div>
            </div>
        </div>

        <!-- Map and Personnel -->
        <div class="dashboard-grid">
            <div class="panel map-panel">
                <div class="panel-header">
                    <span class="panel-title">🗺️ Location Map</span>
                </div>
                <div id="map"></div>
            </div>

            <div class="panel personnel-panel">
                <div class="panel-header">
                    <span class="panel-title">👥 Personnel Status</span>
                </div>
                <div class="personnel-list" id="personnelList"></div>
            </div>
        </div>

        <!-- Charts and Activity -->
        <div class="dashboard-grid">
            <div class="panel chart-panel">
                <div class="panel-header">
                    <span class="panel-title">📊 Detection History (30-min intervals)</span>
                </div>
                <div class="chart-container">
                    <canvas id="detectionChart"></canvas>
                </div>
            </div>

            <div class="panel activity-panel">
                <div class="panel-header">
                    <span class="panel-title">📋 Activity Log</span>
                </div>
                <div class="activity-list" id="activityLog"></div>
            </div>
        </div>
    </div>

    <script src="assets/index.js"></script>

</body>
</html>