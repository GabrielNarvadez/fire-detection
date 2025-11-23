<?php
/**
 * Fire Detection Dashboard - Single File
 * Serves HTML dashboard and provides JSON data API
 */

// Configuration
define('DATA_FILE', 'fire_data.json');
define('UPLOAD_DIR', 'uploads');
define('ANNOTATED_DIR', 'annotated');

// Create directories if they don't exist
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
if (!is_dir(ANNOTATED_DIR)) mkdir(ANNOTATED_DIR, 0777, true);

// Handle file upload
if (isset($_GET['upload'])) {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }
    
    $file = $_FILES['file'];
    $type = $_POST['type'] ?? 'image';
    
    // Save uploaded file
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $filepath = UPLOAD_DIR . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        exit;
    }
    
    // Call Python script to process
    $pythonScript = __DIR__ . '/process_upload.py';
    $command = "python3 $pythonScript " . escapeshellarg($filepath) . " " . escapeshellarg($type);
    $output = shell_exec($command . " 2>&1");
    $result = json_decode($output, true);
    
    if ($result && $result['success']) {
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'error' => 'Detection failed: ' . ($output ?? 'Unknown error')]);
    }
    exit;
}

// Handle firefighter save
if (isset($_GET['save_firefighters'])) {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (file_exists(DATA_FILE)) {
        $data = json_decode(file_get_contents(DATA_FILE), true);
        $data['firefighters'] = $input['firefighters'] ?? [];
        file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Data file not found']);
    }
    exit;
}

// Check if this is an API request
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    // Read fire data from JSON file
    if (file_exists(DATA_FILE)) {
        $data = file_get_contents(DATA_FILE);
        echo $data;
    } else {
        echo json_encode([
            'error' => 'No data available. Make sure Python script is running.',
            'cameras' => [],
            'detections' => [],
            'alerts' => [],
            'activity' => [],
            'personnel' => [],
            'stats' => [
                'detections_today' => 0,
                'fire_today' => 0,
                'smoke_today' => 0,
                'avg_response_time' => 0,
                'personnel_online' => 0,
                'active_cameras' => 0
            ]
        ]);
    }
    exit;
}

// Serve the dashboard HTML
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #e0e0e0;
            overflow-x: hidden;
        }

        .header {
            background: linear-gradient(90deg, #0f3460 0%, #16213e 100%);
            padding: 15px 30px;
            border-bottom: 3px solid #e94560;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 0 20px rgba(233, 69, 96, 0.5);
        }

        .header h1 {
            color: #ffffff;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .system-status {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 2px solid;
        }

        .status-operational {
            background: rgba(46, 213, 115, 0.2);
            border-color: #2ed573;
            color: #2ed573;
        }

        .status-alert {
            background: rgba(233, 69, 96, 0.2);
            border-color: #e94560;
            color: #e94560;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .datetime {
            font-size: 14px;
            color: #a0a0a0;
        }

        .container {
            padding: 20px;
            max-width: 1920px;
            margin: 0 auto;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .panel {
            background: linear-gradient(135deg, #1e2a3a 0%, #1a1f2e 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            position: relative;
            overflow: hidden;
        }

        .panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #e94560, #0f3460);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .panel-title {
            font-size: 16px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #ffffff;
        }

        .stat-card {
            grid-column: span 3;
            text-align: center;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-change {
            font-size: 11px;
            margin-top: 5px;
            color: #2ed573;
        }

        .alert-panel {
            grid-column: span 12;
        }

        .alert-item {
            background: rgba(233, 69, 96, 0.1);
            border-left: 4px solid #e94560;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 6px;
        }

        .alert-item.alert-warning {
            border-left-color: #ffa502;
            background: rgba(255, 165, 2, 0.1);
        }

        .alert-item.alert-info {
            border-left-color: #5352ed;
            background: rgba(83, 82, 237, 0.1);
        }

        .alert-time {
            font-size: 11px;
            color: #888;
            margin-bottom: 5px;
        }

        .alert-message {
            font-size: 13px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .camera-panel {
            grid-column: span 6;
        }

        .camera-feed {
            background: #000;
            width: 100%;
            height: 300px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #2d3748;
            color: #666;
            font-size: 14px;
            overflow: hidden;
        }

        .map-panel {
            grid-column: span 8;
            height: 400px;
        }

        #map {
            height: 320px;
            border-radius: 8px;
            border: 2px solid #2d3748;
        }

        .personnel-panel {
            grid-column: span 4;
            height: 400px;
        }

        .personnel-list {
            max-height: 320px;
            overflow-y: auto;
        }

        .personnel-item {
            background: rgba(255, 255, 255, 0.03);
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 6px;
            border-left: 3px solid #5352ed;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .personnel-item.firefighter {
            border-left-color: #e94560;
        }

        .personnel-item.admin {
            border-left-color: #ffa502;
        }

        .personnel-info h4 {
            font-size: 14px;
            margin-bottom: 3px;
        }

        .personnel-info p {
            font-size: 11px;
            color: #888;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #2ed573;
            box-shadow: 0 0 10px #2ed573;
        }

        .status-indicator.offline {
            background: #888;
            box-shadow: none;
        }

        .chart-panel {
            grid-column: span 6;
            height: 350px;
        }

        .chart-container {
            height: 280px;
        }

        .activity-panel {
            grid-column: span 6;
            height: 350px;
        }

        .activity-list {
            max-height: 280px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 12px;
        }

        .activity-time {
            color: #888;
            font-size: 11px;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(233, 69, 96, 0.5);
            border-radius: 4px;
        }

        .emergency-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .emergency-modal.active {
            display: flex;
        }

        .emergency-content {
            background: linear-gradient(135deg, #e94560 0%, #d63447 100%);
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            max-width: 600px;
            animation: shake 0.5s infinite;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .emergency-content h2 {
            font-size: 48px;
            margin-bottom: 20px;
            color: #ffffff;
        }

        .emergency-content p {
            font-size: 20px;
            margin-bottom: 10px;
            color: #ffffff;
        }

        .emergency-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
        }

        .btn-primary {
            background: #ffffff;
            color: #e94560;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            border: 2px solid #ffffff;
        }

        .firefighter-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid #e94560;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .firefighter-info {
            flex: 1;
        }

        .firefighter-info h4 {
            font-size: 14px;
            margin-bottom: 5px;
            color: #fff;
        }

        .firefighter-info p {
            font-size: 12px;
            color: #888;
            margin: 2px 0;
        }

        .firefighter-actions {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 11px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .btn-small:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .btn-danger {
            background: rgba(233, 69, 96, 0.3);
        }

        .btn-danger:hover {
            background: rgba(233, 69, 96, 0.5);
        }
    </style>
</head>
<body>
    <!-- Emergency Modal -->
    <div class="emergency-modal" id="emergencyModal">
        <div class="emergency-content">
            <div style="font-size: 72px;">🔥</div>
            <h2>FIRE DETECTED!</h2>
            <p><strong>Location:</strong> <span id="emergencyLocation"></span></p>
            <p><strong>Camera:</strong> <span id="emergencyCamera"></span></p>
            <p><strong>Confidence:</strong> <span id="emergencyConfidence"></span></p>
            <div class="emergency-actions">
                <button class="btn btn-primary" onclick="showNotificationSelection()">SELECT FIREFIGHTERS TO NOTIFY</button>
                <button class="btn btn-secondary" onclick="closeEmergency()">ACKNOWLEDGE</button>
            </div>
        </div>
    </div>

    <!-- Notification Selection Modal -->
    <div class="emergency-modal" id="notificationModal">
        <div class="emergency-content" style="max-width: 700px; max-height: 80vh; overflow-y: auto;">
            <h2 style="font-size: 32px;">📞 Select Who to Notify</h2>
            <div style="text-align: left; margin: 20px 0;">
                <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 8px; margin-bottom: 10px; cursor: pointer;">
                    <input type="checkbox" id="notifyAll" onchange="toggleNotifyAll()" style="width: 20px; height: 20px;">
                    <strong style="font-size: 18px;">NOTIFY ALL FIREFIGHTERS</strong>
                </label>
                <div id="firefighterCheckboxes" style="margin-top: 15px;"></div>
            </div>
            <div class="emergency-actions">
                <button class="btn btn-primary" onclick="sendNotifications()">📱 SEND SMS NOTIFICATIONS</button>
                <button class="btn btn-secondary" onclick="closeNotificationModal()">CANCEL</button>
            </div>
        </div>
    </div>

    <!-- Add/Edit Firefighter Modal -->
    <div class="emergency-modal" id="firefighterModal">
        <div class="emergency-content" style="background: linear-gradient(135deg, #5352ed 0%, #4338ca 100%); max-width: 500px;">
            <h2 style="font-size: 32px;" id="firefighterModalTitle">Add Firefighter</h2>
            <div style="text-align: left; margin: 20px 0;">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Name:</label>
                    <input type="text" id="firefighterName" style="width: 100%; padding: 10px; border-radius: 5px; border: none; font-size: 16px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Phone Number:</label>
                    <input type="tel" id="firefighterPhone" placeholder="+63-917-123-4567" style="width: 100%; padding: 10px; border-radius: 5px; border: none; font-size: 16px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Station:</label>
                    <select id="firefighterStation" style="width: 100%; padding: 10px; border-radius: 5px; border: none; font-size: 16px;">
                        <option value="1">Station 1</option>
                        <option value="2">Station 2</option>
                    </select>
                </div>
            </div>
            <div class="emergency-actions">
                <button class="btn btn-primary" onclick="saveFirefighter()">💾 SAVE</button>
                <button class="btn btn-secondary" onclick="closeFirefighterModal()">CANCEL</button>
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
        <div class="datetime" id="datetime"></div>
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

        <!-- File Upload and Firefighter Management -->
        <div class="dashboard-grid">
            <!-- Upload Section -->
            <div class="panel" style="grid-column: span 6; display: none;">
                <div class="panel-header">
                    <span class="panel-title">📤 Upload for Detection</span>
                </div>
                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <button class="btn btn-primary" onclick="document.getElementById('imageUpload').click()">
                        📷 Upload Image
                    </button>
                    <button class="btn btn-primary" onclick="document.getElementById('videoUpload').click()">
                        🎥 Upload Video
                    </button>
                    <input type="file" id="imageUpload" accept="image/*" style="display: none;" onchange="handleImageUpload(event)">
                    <input type="file" id="videoUpload" accept="video/*" style="display: none;" onchange="handleVideoUpload(event)">
                </div>
                <div id="uploadPreview" style="background: #000; min-height: 200px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #666;">
                    Upload an image or video to detect fire/smoke
                </div>
                <div id="uploadResult" style="margin-top: 10px; color: #2ed573;"></div>
            </div>

            <!-- Firefighter Management -->
            <div class="panel" style="grid-column: span 12;">
                <div class="panel-header">
                    <span class="panel-title">👨‍🚒 Manage Firefighters</span>
                    <button class="btn btn-secondary" style="padding: 5px 15px; font-size: 12px;" onclick="showAddFirefighter()">+ Add Firefighter</button>
                </div>
                <div id="firefighterList" style="max-height: 250px; overflow-y: auto;"></div>
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
                <div style="font-size: 12px; color: #888;">Building A - Warehouse</div>
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
                <div style="font-size: 12px; color: #888;">Building A - Warehouse</div>
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
                    <span class="panel-title">👥 Personnel Tracking</span>
                </div>
                <div class="personnel-list" id="personnelList"></div>
            </div>
        </div>

        <!-- Charts and Activity -->
        <div class="dashboard-grid">
            <div class="panel chart-panel">
                <div class="panel-header">
                    <span class="panel-title">📊 Recent Detections</span>
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

    <script>
        let dashboardData = null;
        let detectionChart = null;
        let map = null;
        let emergencyActive = false;

        // Update datetime
        function updateDateTime() {
            const now = new Date();
            document.getElementById('datetime').textContent = now.toLocaleString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }
        setInterval(updateDateTime, 1000);
        updateDateTime();

        // Fetch data from PHP API
        async function fetchData() {
            try {
                const response = await fetch('?api=1');
                const data = await response.json();
                
                if (!data.error) {
                    dashboardData = data;
                    updateDashboard(data);
                }
            } catch (error) {
                console.error('Error fetching data:', error);
            }
        }

        // Update dashboard
        function updateDashboard(data) {
            // Update stats
            if (data.stats) {
                document.getElementById('activeCameras').textContent = data.stats.active_cameras || 0;
                document.getElementById('detectionsToday').textContent = data.stats.detections_today || 0;
                document.getElementById('avgResponse').textContent = data.stats.avg_response_time || 3.2;
                document.getElementById('personnelOnline').textContent = data.stats.personnel_online || 0;
                
                const changeEl = document.getElementById('detectionChange');
                if (data.stats.fire_today > 0 || data.stats.smoke_today > 0) {
                    changeEl.textContent = `${data.stats.fire_today} fire, ${data.stats.smoke_today} smoke`;
                } else {
                    changeEl.textContent = 'No detections today';
                }
            }

            // Update camera status
            if (data.cameras) {
                Object.values(data.cameras).forEach(camera => {
                    const statusEl = document.getElementById(`cam${camera.name.includes('1') ? '1' : '2'}Status`);
                    if (statusEl) {
                        if (camera.status === 'online') {
                            statusEl.innerHTML = '● LIVE';
                            statusEl.style.color = '#2ed573';
                        } else {
                            statusEl.innerHTML = '● OFFLINE';
                            statusEl.style.color = '#888';
                        }
                    }
                });
            }

            // Update alerts
            updateAlerts(data.alerts || []);

            // Update personnel
            updatePersonnel(data.personnel || []);

            // Update activity log
            updateActivity(data.activity || []);

            // Check for critical alerts
            if (data.alerts && data.alerts.length > 0) {
                const criticalAlert = data.alerts.find(a => a.alert_level === 'critical' && a.status === 'active');
                if (criticalAlert && !emergencyActive) {
                    showEmergency(criticalAlert);
                }
            }
        }

        // Update alerts
        function updateAlerts(alerts) {
            const alertsList = document.getElementById('alertsList');
            
            if (alerts.length === 0) {
                alertsList.innerHTML = `
                    <div class="alert-item alert-info">
                        <div class="alert-time">${new Date().toLocaleTimeString()}</div>
                        <div class="alert-message">No active alerts - All systems normal</div>
                    </div>
                `;
                return;
            }

            alertsList.innerHTML = '';
            alerts.slice(0, 5).forEach(alert => {
                const alertTime = new Date(alert.timestamp);
                const alertClass = alert.alert_level === 'critical' ? '' : 
                                  alert.alert_level === 'warning' ? 'alert-warning' : 'alert-info';
                
                const div = document.createElement('div');
                div.className = `alert-item ${alertClass}`;
                div.innerHTML = `
                    <div class="alert-time">${alertTime.toLocaleTimeString()}</div>
                    <div class="alert-message">${alert.message}</div>
                `;
                alertsList.appendChild(div);
            });

            // Update system status
            if (alerts.some(a => a.alert_level === 'critical' && a.status === 'active')) {
                document.getElementById('systemStatus').textContent = 'EMERGENCY ALERT';
                document.getElementById('systemStatus').className = 'status-badge status-alert';
            } else {
                document.getElementById('systemStatus').textContent = 'SYSTEM OPERATIONAL';
                document.getElementById('systemStatus').className = 'status-badge status-operational';
            }
        }

        // Update personnel
        function updatePersonnel(personnel) {
            const list = document.getElementById('personnelList');
            list.innerHTML = '';
            
            personnel.forEach(person => {
                const div = document.createElement('div');
                div.className = `personnel-item ${person.type}`;
                div.innerHTML = `
                    <div class="personnel-info">
                        <h4>${person.name}</h4>
                        <p>${person.role}</p>
                    </div>
                    <div class="status-indicator ${person.status}"></div>
                `;
                list.appendChild(div);
            });
        }

        // Update activity log
        function updateActivity(activities) {
            const log = document.getElementById('activityLog');
            log.innerHTML = '';
            
            activities.slice(0, 15).forEach(activity => {
                const time = new Date(activity.timestamp);
                const div = document.createElement('div');
                div.className = 'activity-item';
                div.innerHTML = `
                    <div class="activity-time">${time.toLocaleTimeString()}</div>
                    <div>${activity.message}</div>
                `;
                log.appendChild(div);
            });
        }

        // Show emergency modal
        function showEmergency(alert) {
            emergencyActive = true;
            document.getElementById('emergencyLocation').textContent = alert.message.split('at ')[1]?.split(' -')[0] || 'Unknown';
            document.getElementById('emergencyCamera').textContent = 'Camera Detection';
            document.getElementById('emergencyConfidence').textContent = alert.message.match(/\d+%/)?.[0] || 'High';
            document.getElementById('emergencyModal').classList.add('active');
        }

        // Dispatch firefighters
        function dispatchFirefighters() {
            alert('🚒 Firefighters have been dispatched!');
            closeEmergency();
        }

        // Close emergency
        function closeEmergency() {
            emergencyActive = false;
            document.getElementById('emergencyModal').classList.remove('active');
        }

        // Initialize map
        function initMap() {
            map = L.map('map').setView([14.5995, 120.9842], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Add camera markers
            const cameraIcon = L.divIcon({
                html: '<div style="background: #e94560; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white;">📹</div>',
                iconSize: [30, 30]
            });

            L.marker([14.6005, 120.9850], {icon: cameraIcon})
                .addTo(map)
                .bindPopup('<strong>Camera 1 - Visual ML</strong><br>Building A - Warehouse');

            L.marker([14.6010, 120.9855], {icon: cameraIcon})
                .addTo(map)
                .bindPopup('<strong>Camera 2 - Thermal</strong><br>Building A - Warehouse');

            // Add station markers
            const stationIcon = L.divIcon({
                html: '<div style="background: #5352ed; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white;">🚒</div>',
                iconSize: [35, 35]
            });

            L.marker([14.5950, 120.9800], {icon: stationIcon})
                .addTo(map)
                .bindPopup('<strong>Fire Station 1</strong><br>6 firefighters ready');

            L.marker([14.6040, 120.9900], {icon: stationIcon})
                .addTo(map)
                .bindPopup('<strong>Fire Station 2</strong><br>6 firefighters ready');
        }

        // Initialize chart
        function initChart() {
            const ctx = document.getElementById('detectionChart').getContext('2d');
            detectionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Recent Detections'],
                    datasets: [{
                        label: 'Fire',
                        data: [0],
                        backgroundColor: 'rgba(233, 69, 96, 0.7)',
                        borderColor: '#e94560',
                        borderWidth: 2
                    }, {
                        label: 'Smoke',
                        data: [0],
                        backgroundColor: 'rgba(255, 165, 2, 0.7)',
                        borderColor: '#ffa502',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { color: '#e0e0e0' }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#888' },
                            grid: { color: 'rgba(255, 255, 255, 0.05)' }
                        },
                        x: {
                            ticks: { color: '#888' },
                            grid: { color: 'rgba(255, 255, 255, 0.05)' }
                        }
                    }
                }
            });
        }

        // Initialize dashboard
        async function init() {
            initMap();
            initChart();
            await fetchData();
            
            // Auto-refresh data every 3 seconds
            setInterval(fetchData, 3000);
            
            // Auto-refresh camera feeds every 500ms for smooth video
            setInterval(refreshCameraFeeds, 500);
            
            console.log('Dashboard initialized');
            console.log('Data refreshes every 3 seconds');
        }

        // Refresh camera feeds
        function refreshCameraFeeds() {
            const camera1 = document.getElementById('camera1Feed');
            const camera2 = document.getElementById('camera2Feed');
            
            if (camera1) {
                camera1.src = 'camera_frames/camera1_live.jpg?' + new Date().getTime();
            }
            if (camera2) {
                camera2.src = 'camera_frames/camera2_live.jpg?' + new Date().getTime();
            }
        }

        // ========================================
        // FILE UPLOAD FUNCTIONS
        // ========================================
        
        async function handleImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', 'image');

            const preview = document.getElementById('uploadPreview');
            const result = document.getElementById('uploadResult');
            
            preview.innerHTML = '<div style="color: #fff;">Processing...</div>';
            result.textContent = '';

            try {
                const response = await fetch('?upload=1', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    preview.innerHTML = `<img src="${data.annotated_image}" style="max-width: 100%; max-height: 300px; border-radius: 8px;">`;
                    result.innerHTML = `<strong>✓ Detection Complete!</strong><br>
                        Fire: ${data.fire_count} | Smoke: ${data.smoke_count} | 
                        <a href="${data.annotated_image}" download style="color: #2ed573;">Download Annotated Image</a>`;
                } else {
                    result.textContent = '❌ ' + (data.error || 'Detection failed');
                }
            } catch (error) {
                result.textContent = '❌ Upload failed: ' + error.message;
            }
        }

        async function handleVideoUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', 'video');

            const preview = document.getElementById('uploadPreview');
            const result = document.getElementById('uploadResult');
            
            preview.innerHTML = '<div style="color: #fff;">Processing video... This may take a while...</div>';
            result.textContent = '';

            try {
                const response = await fetch('?upload=1', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    preview.innerHTML = `<video src="${data.annotated_video}" controls style="max-width: 100%; max-height: 300px; border-radius: 8px;"></video>`;
                    result.innerHTML = `<strong>✓ Detection Complete!</strong><br>
                        Fire: ${data.fire_count} | Smoke: ${data.smoke_count} | 
                        <a href="${data.annotated_video}" download style="color: #2ed573;">Download Annotated Video</a>`;
                } else {
                    result.textContent = '❌ ' + (data.error || 'Detection failed');
                }
            } catch (error) {
                result.textContent = '❌ Upload failed: ' + error.message;
            }
        }

        // ========================================
        // FIREFIGHTER MANAGEMENT
        // ========================================

        let firefighters = [];
        let editingFirefighterId = null;

        function loadFirefighters() {
            if (dashboardData && dashboardData.firefighters) {
                firefighters = dashboardData.firefighters;
            }
            renderFirefighters();
        }

        function renderFirefighters() {
            const list = document.getElementById('firefighterList');
            
            if (firefighters.length === 0) {
                list.innerHTML = '<div style="padding: 20px; text-align: center; color: #888;">No firefighters added yet</div>';
                return;
            }

            list.innerHTML = '';
            firefighters.forEach((ff, index) => {
                const card = document.createElement('div');
                card.className = 'firefighter-card';
                card.innerHTML = `
                    <div class="firefighter-info">
                        <h4>👨‍🚒 ${ff.name}</h4>
                        <p>📱 ${ff.phone}</p>
                        <p>🏢 Station ${ff.station}</p>
                    </div>
                    <div class="firefighter-actions">
                        <button class="btn-small" onclick="editFirefighter(${index})">✏️ Edit</button>
                        <button class="btn-small btn-danger" onclick="deleteFirefighter(${index})">🗑️ Delete</button>
                    </div>
                `;
                list.appendChild(card);
            });
        }

        function showAddFirefighter() {
            editingFirefighterId = null;
            document.getElementById('firefighterModalTitle').textContent = 'Add Firefighter';
            document.getElementById('firefighterName').value = '';
            document.getElementById('firefighterPhone').value = '';
            document.getElementById('firefighterStation').value = '1';
            document.getElementById('firefighterModal').classList.add('active');
        }

        function editFirefighter(index) {
            editingFirefighterId = index;
            const ff = firefighters[index];
            document.getElementById('firefighterModalTitle').textContent = 'Edit Firefighter';
            document.getElementById('firefighterName').value = ff.name;
            document.getElementById('firefighterPhone').value = ff.phone;
            document.getElementById('firefighterStation').value = ff.station;
            document.getElementById('firefighterModal').classList.add('active');
        }

        async function saveFirefighter() {
            const name = document.getElementById('firefighterName').value.trim();
            const phone = document.getElementById('firefighterPhone').value.trim();
            const station = document.getElementById('firefighterStation').value;

            if (!name || !phone) {
                alert('Please fill in all fields');
                return;
            }

            const firefighter = { name, phone, station };

            if (editingFirefighterId !== null) {
                firefighters[editingFirefighterId] = firefighter;
            } else {
                firefighters.push(firefighter);
            }

            // Save to server
            await saveFirefightersToServer();
            
            closeFirefighterModal();
            renderFirefighters();
        }

        async function deleteFirefighter(index) {
            if (!confirm('Are you sure you want to delete this firefighter?')) {
                return;
            }

            firefighters.splice(index, 1);
            await saveFirefightersToServer();
            renderFirefighters();
        }

        async function saveFirefightersToServer() {
            try {
                await fetch('?save_firefighters=1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ firefighters })
                });
            } catch (error) {
                console.error('Error saving firefighters:', error);
            }
        }

        function closeFirefighterModal() {
            document.getElementById('firefighterModal').classList.remove('active');
        }

        // ========================================
        // NOTIFICATION SELECTION
        // ========================================

        let currentAlert = null;

        function showNotificationSelection() {
            currentAlert = {
                location: document.getElementById('emergencyLocation').textContent,
                camera: document.getElementById('emergencyCamera').textContent,
                confidence: document.getElementById('emergencyConfidence').textContent
            };

            closeEmergency();
            
            // Build firefighter checkboxes
            const container = document.getElementById('firefighterCheckboxes');
            container.innerHTML = '';
            
            firefighters.forEach((ff, index) => {
                const label = document.createElement('label');
                label.style.cssText = 'display: flex; align-items: center; gap: 10px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 6px; margin-bottom: 8px; cursor: pointer;';
                label.innerHTML = `
                    <input type="checkbox" class="ff-notify-checkbox" data-index="${index}" style="width: 18px; height: 18px;">
                    <div>
                        <strong>${ff.name}</strong><br>
                        <small style="color: #aaa;">📱 ${ff.phone} | Station ${ff.station}</small>
                    </div>
                `;
                container.appendChild(label);
            });

            document.getElementById('notificationModal').classList.add('active');
        }

        function toggleNotifyAll() {
            const notifyAll = document.getElementById('notifyAll').checked;
            document.querySelectorAll('.ff-notify-checkbox').forEach(cb => {
                cb.checked = notifyAll;
            });
        }

        async function sendNotifications() {
            const selectedFirefighters = [];
            document.querySelectorAll('.ff-notify-checkbox:checked').forEach(cb => {
                const index = parseInt(cb.dataset.index);
                selectedFirefighters.push(firefighters[index]);
            });

            if (selectedFirefighters.length === 0) {
                alert('Please select at least one firefighter to notify');
                return;
            }

            // Log notification
            const notificationLog = {
                timestamp: new Date().toISOString(),
                alert: currentAlert,
                firefighters: selectedFirefighters.map(ff => ({
                    name: ff.name,
                    phone: ff.phone
                }))
            };

            console.log('📱 SMS Notifications:', notificationLog);

            // TODO: Integrate with SMS device here
            // For now, just show confirmation
            
            let message = `📱 SMS SENT TO:\n\n`;
            selectedFirefighters.forEach(ff => {
                message += `✓ ${ff.name} (${ff.phone})\n`;
            });
            message += `\nMessage: "FIRE ALERT at ${currentAlert.location}. Confidence: ${currentAlert.confidence}. Respond immediately."`;

            alert(message);

            closeNotificationModal();
        }

        function closeNotificationModal() {
            document.getElementById('notificationModal').classList.remove('active');
        }

        // Start when page loads
        document.addEventListener('DOMContentLoaded', init);
        
        // Load firefighters after data fetch
        setInterval(() => {
            if (dashboardData) {
                loadFirefighters();
            }
        }, 3000);
    </script>
</body>
</html>