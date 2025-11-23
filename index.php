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

        .panel-icon {
            font-size: 20px;
        }

        /* Alert Panel */
        .alert-panel {
            grid-column: span 4;
        }

        .alert-item {
            background: rgba(233, 69, 96, 0.1);
            border-left: 4px solid #e94560;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .alert-item:hover {
            background: rgba(233, 69, 96, 0.2);
            transform: translateX(5px);
        }

        .alert-warning {
            border-left-color: #ffa502;
            background: rgba(255, 165, 2, 0.1);
        }

        .alert-info {
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

        .alert-location {
            font-size: 11px;
            color: #aaa;
        }

        /* Camera Panels */
        .camera-panel {
            grid-column: span 4;
        }

        .camera-feed {
            background: #000;
            width: 100%;
            height: 250px;
            border-radius: 8px;
            margin-bottom: 10px;
            position: relative;
            overflow: hidden;
            border: 2px solid #2d3748;
        }

        .camera-feed img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .camera-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.7);
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 11px;
        }

        .camera-status {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }

        .detection-box {
            position: absolute;
            border: 3px solid #e94560;
            background: rgba(233, 69, 96, 0.2);
            animation: detection-blink 1s infinite;
        }

        @keyframes detection-blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* Stats Panels */
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
        }

        .stat-up {
            color: #2ed573;
        }

        .stat-down {
            color: #e94560;
        }

        /* Map Panel */
        .map-panel {
            grid-column: span 8;
            height: 500px;
        }

        #map {
            height: 420px;
            border-radius: 8px;
            border: 2px solid #2d3748;
        }

        /* Personnel Panel */
        .personnel-panel {
            grid-column: span 4;
            height: 500px;
        }

        .personnel-list {
            max-height: 420px;
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

        .status-indicator.responding {
            background: #ffa502;
            animation: pulse 1s infinite;
        }

        /* Chart Panel */
        .chart-panel {
            grid-column: span 6;
            height: 350px;
        }

        .chart-container {
            height: 280px;
        }

        /* Emergency Alert Modal */
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
            box-shadow: 0 0 50px rgba(233, 69, 96, 0.8);
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
            transition: all 0.3s;
            text-transform: uppercase;
        }

        .btn-primary {
            background: #ffffff;
            color: #e94560;
        }

        .btn-primary:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            border: 2px solid #ffffff;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Activity Log */
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
            display: flex;
            gap: 10px;
        }

        .activity-timestamp {
            color: #888;
            min-width: 80px;
        }

        .activity-message {
            flex: 1;
        }

        /* Scrollbar Styling */
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

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(233, 69, 96, 0.7);
        }

        /* Detection Indicator */
        .detection-indicator {
            position: fixed;
            top: 100px;
            right: 30px;
            background: rgba(233, 69, 96, 0.95);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(233, 69, 96, 0.6);
            display: none;
            z-index: 1000;
            animation: slideIn 0.5s;
        }

        .detection-indicator.active {
            display: block;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .detection-indicator h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .detection-indicator p {
            font-size: 14px;
            margin-bottom: 5px;
        }

        /* Thermal color gradient for thermal camera */
        .thermal-gradient {
            background: linear-gradient(to right, 
                #000000, #0000ff, #00ffff, #00ff00, 
                #ffff00, #ff0000, #ffffff);
            height: 20px;
            border-radius: 4px;
            margin-top: 10px;
        }

        .thermal-scale {
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            margin-top: 5px;
            color: #888;
        }
    </style>
</head>
<body>
    <!-- Emergency Alert Modal -->
    <div class="emergency-modal" id="emergencyModal">
        <div class="emergency-content">
            <div style="font-size: 72px; margin-bottom: 20px;">🔥</div>
            <h2>FIRE DETECTED!</h2>
            <p><strong>Location:</strong> <span id="emergencyLocation"></span></p>
            <p><strong>Camera:</strong> <span id="emergencyCamera"></span></p>
            <p><strong>Confidence:</strong> <span id="emergencyConfidence"></span></p>
            <div class="emergency-actions">
                <button class="btn btn-primary" onclick="dispatchFirefighters()">DISPATCH FIREFIGHTERS</button>
                <button class="btn btn-secondary" onclick="closeEmergency()">FALSE ALARM</button>
            </div>
        </div>
    </div>

    <!-- Detection Indicator -->
    <div class="detection-indicator" id="detectionIndicator">
        <h3>⚠️ Smoke Detected</h3>
        <p><strong>Camera 1 - Visual</strong></p>
        <p>Confidence: 78%</p>
        <p>Location: Building A, Floor 3</p>
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
        <!-- Top Stats Row -->
        <div class="dashboard-grid">
            <div class="panel stat-card">
                <div class="panel-header">
                    <span class="panel-title">Active Cameras</span>
                </div>
                <div class="stat-value" id="activeCameras">2</div>
                <div class="stat-label">Online / 2 Total</div>
                <div class="stat-change stat-up">✓ All Systems Active</div>
            </div>

            <div class="panel stat-card">
                <div class="panel-header">
                    <span class="panel-title">Detections Today</span>
                </div>
                <div class="stat-value" id="detectionsToday">0</div>
                <div class="stat-label">Fire & Smoke Events</div>
                <div class="stat-change stat-down">↓ 100% vs Yesterday</div>
            </div>

            <div class="panel stat-card">
                <div class="panel-header">
                    <span class="panel-title">Avg Response Time</span>
                </div>
                <div class="stat-value">3.2</div>
                <div class="stat-label">Minutes</div>
                <div class="stat-change stat-up">↓ 18% Improvement</div>
            </div>

            <div class="panel stat-card">
                <div class="panel-header">
                    <span class="panel-title">Personnel Online</span>
                </div>
                <div class="stat-value" id="personnelOnline">12</div>
                <div class="stat-label">Firefighters & Admins</div>
                <div class="stat-change stat-up">✓ Full Staffing</div>
            </div>
        </div>

        <!-- Alerts Row -->
        <div class="dashboard-grid">
            <div class="panel alert-panel">
                <div class="panel-header">
                    <span class="panel-title">🚨 Active Alerts</span>
                    <span class="panel-icon">⚡</span>
                </div>
                <div id="alertsList">
                    <div class="alert-item alert-info">
                        <div class="alert-time">10:45 AM</div>
                        <div class="alert-message">System Health Check - All Clear</div>
                        <div class="alert-location">All Cameras Operational</div>
                    </div>
                    <div class="alert-item alert-info">
                        <div class="alert-time">09:30 AM</div>
                        <div class="alert-message">Shift Change Completed</div>
                        <div class="alert-location">12 Firefighters on Duty</div>
                    </div>
                </div>
            </div>

            <!-- Camera 1 - Visual ML -->
            <div class="panel camera-panel">
                <div class="panel-header">
                    <span class="panel-title">📹 Camera 1 - Visual ML</span>
                    <span style="color: #2ed573; font-size: 12px;">● LIVE</span>
                </div>
                <div class="camera-feed" id="camera1">
                    <div class="camera-overlay">
                        <div>Building A - Warehouse</div>
                        <div style="margin-top: 3px; color: #2ed573;">AI: Active</div>
                    </div>
                    <svg width="100%" height="100%">
                        <rect x="20" y="20" width="80" height="60" fill="rgba(46, 213, 115, 0.1)" stroke="#2ed573" stroke-width="2"/>
                        <text x="25" y="35" fill="#2ed573" font-size="10">SCANNING</text>
                    </svg>
                </div>
                <div class="camera-status">
                    <span>Status: <strong style="color: #2ed573;">Normal</strong></span>
                    <span>Temp: <strong>22°C</strong></span>
                    <span>Confidence: <strong>--</strong></span>
                </div>
            </div>

            <!-- Camera 2 - Thermal -->
            <div class="panel camera-panel">
                <div class="panel-header">
                    <span class="panel-title">🌡️ Camera 2 - Thermal</span>
                    <span style="color: #2ed573; font-size: 12px;">● LIVE</span>
                </div>
                <div class="camera-feed" id="camera2" style="background: linear-gradient(135deg, #001a33 0%, #003366 100%);">
                    <div class="camera-overlay">
                        <div>Building A - Warehouse</div>
                        <div style="margin-top: 3px; color: #2ed573;">Thermal: Active</div>
                    </div>
                    <svg width="100%" height="100%">
                        <defs>
                            <radialGradient id="heatGradient1" cx="30%" cy="40%">
                                <stop offset="0%" style="stop-color:#ffff00;stop-opacity:0.8" />
                                <stop offset="50%" style="stop-color:#ff6600;stop-opacity:0.4" />
                                <stop offset="100%" style="stop-color:#003366;stop-opacity:0" />
                            </radialGradient>
                            <radialGradient id="heatGradient2" cx="70%" cy="60%">
                                <stop offset="0%" style="stop-color:#ffff00;stop-opacity:0.6" />
                                <stop offset="50%" style="stop-color:#ff6600;stop-opacity:0.3" />
                                <stop offset="100%" style="stop-color:#003366;stop-opacity:0" />
                            </radialGradient>
                        </defs>
                        <ellipse cx="30%" cy="40%" rx="60" ry="40" fill="url(#heatGradient1)"/>
                        <ellipse cx="70%" cy="60%" rx="40" ry="30" fill="url(#heatGradient2)"/>
                    </svg>
                </div>
                <div class="thermal-gradient"></div>
                <div class="thermal-scale">
                    <span>0°C</span>
                    <span>50°C</span>
                    <span>100°C</span>
                    <span>150°C</span>
                    <span>200°C+</span>
                </div>
            </div>
        </div>

        <!-- Map and Personnel Row -->
        <div class="dashboard-grid">
            <!-- Map Panel -->
            <div class="panel map-panel">
                <div class="panel-header">
                    <span class="panel-title">🗺️ Location Map</span>
                    <span class="panel-icon">📍</span>
                </div>
                <div id="map"></div>
            </div>

            <!-- Personnel Panel -->
            <div class="panel personnel-panel">
                <div class="panel-header">
                    <span class="panel-title">👥 Personnel Tracking</span>
                    <span class="panel-icon">12 Online</span>
                </div>
                <div class="personnel-list" id="personnelList">
                    <!-- Personnel items will be dynamically added here -->
                </div>
            </div>
        </div>

        <!-- Charts and Activity Row -->
        <div class="dashboard-grid">
            <!-- Detection History Chart -->
            <div class="panel chart-panel">
                <div class="panel-header">
                    <span class="panel-title">📊 Detection History (Last 7 Days)</span>
                </div>
                <div class="chart-container">
                    <canvas id="detectionChart"></canvas>
                </div>
            </div>

            <!-- Temperature Chart -->
            <div class="panel chart-panel">
                <div class="panel-header">
                    <span class="panel-title">🌡️ Temperature Monitoring</span>
                </div>
                <div class="chart-container">
                    <canvas id="temperatureChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="dashboard-grid">
            <div class="panel activity-panel">
                <div class="panel-header">
                    <span class="panel-title">📋 Activity Log</span>
                    <span class="panel-icon">Real-time</span>
                </div>
                <div class="activity-list" id="activityLog">
                    <!-- Activity items will be added here -->
                </div>
            </div>

            <div class="panel activity-panel">
                <div class="panel-header">
                    <span class="panel-title">⏱️ Response History</span>
                </div>
                <div class="activity-list" id="responseHistory">
                    <div class="activity-item">
                        <div class="activity-timestamp">Nov 18, 2:30 PM</div>
                        <div class="activity-message">
                            <strong>Fire Drill</strong> - Response time: 2.8 min<br>
                            <small style="color: #888;">Station 1, Team A - 4 firefighters</small>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-timestamp">Nov 15, 10:15 AM</div>
                        <div class="activity-message">
                            <strong>False Alarm</strong> - Steam misidentified<br>
                            <small style="color: #888;">Camera 1 - Confidence: 65%</small>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-timestamp">Nov 12, 4:45 PM</div>
                        <div class="activity-message">
                            <strong>Fire Incident</strong> - Successfully extinguished<br>
                            <small style="color: #888;">Response time: 3.5 min - Minor damage</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize datetime
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('datetime').textContent = now.toLocaleDateString('en-US', options);
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Initialize Map
        const map = L.map('map').setView([14.5995, 120.9842], 13); // Manila coordinates
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Camera locations
        const cameraIcon = L.divIcon({
            html: '<div style="background: #e94560; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 0 10px rgba(233, 69, 96, 0.6);">📹</div>',
            iconSize: [30, 30],
            className: 'custom-icon'
        });

        // Add camera markers
        const camera1Marker = L.marker([14.6005, 120.9850], {icon: cameraIcon})
            .addTo(map)
            .bindPopup('<strong>Camera 1 - Visual ML</strong><br>Building A - Warehouse<br>Status: <span style="color: #2ed573;">Active</span>');

        const camera2Marker = L.marker([14.6010, 120.9855], {icon: cameraIcon})
            .addTo(map)
            .bindPopup('<strong>Camera 2 - Thermal</strong><br>Building A - Warehouse<br>Status: <span style="color: #2ed573;">Active</span>');

        // Firefighter station locations
        const stationIcon = L.divIcon({
            html: '<div style="background: #5352ed; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 0 10px rgba(83, 82, 237, 0.6);">🚒</div>',
            iconSize: [35, 35],
            className: 'custom-icon'
        });

        // Add firefighter stations
        const station1 = L.marker([14.5950, 120.9800], {icon: stationIcon})
            .addTo(map)
            .bindPopup('<strong>Fire Station 1</strong><br>Personnel: 6 firefighters<br>Status: <span style="color: #2ed573;">Ready</span>');

        const station2 = L.marker([14.6040, 120.9900], {icon: stationIcon})
            .addTo(map)
            .bindPopup('<strong>Fire Station 2</strong><br>Personnel: 6 firefighters<br>Status: <span style="color: #2ed573;">Ready</span>');

        // Personnel data
        const personnel = [
            {name: 'Admin Johnson', role: 'System Administrator', status: 'online', type: 'admin'},
            {name: 'Admin Chen', role: 'Operations Manager', status: 'online', type: 'admin'},
            {name: 'FF Rodriguez', role: 'Fire Chief - Station 1', status: 'online', type: 'firefighter'},
            {name: 'FF Martinez', role: 'Firefighter - Station 1', status: 'online', type: 'firefighter'},
            {name: 'FF Santos', role: 'Firefighter - Station 1', status: 'online', type: 'firefighter'},
            {name: 'FF Reyes', role: 'Firefighter - Station 1', status: 'online', type: 'firefighter'},
            {name: 'FF Cruz', role: 'Firefighter - Station 1', status: 'online', type: 'firefighter'},
            {name: 'FF Bautista', role: 'Firefighter - Station 1', status: 'online', type: 'firefighter'},
            {name: 'FF Garcia', role: 'Fire Chief - Station 2', status: 'online', type: 'firefighter'},
            {name: 'FF Lopez', role: 'Firefighter - Station 2', status: 'online', type: 'firefighter'},
            {name: 'FF Hernandez', role: 'Firefighter - Station 2', status: 'online', type: 'firefighter'},
            {name: 'FF Dela Cruz', role: 'Firefighter - Station 2', status: 'online', type: 'firefighter'},
        ];

        // Populate personnel list
        const personnelList = document.getElementById('personnelList');
        personnel.forEach(person => {
            const item = document.createElement('div');
            item.className = `personnel-item ${person.type}`;
            item.innerHTML = `
                <div class="personnel-info">
                    <h4>${person.name}</h4>
                    <p>${person.role}</p>
                </div>
                <div class="status-indicator ${person.status}"></div>
            `;
            personnelList.appendChild(item);
        });

        // Detection History Chart
        const detectionCtx = document.getElementById('detectionChart').getContext('2d');
        const detectionChart = new Chart(detectionCtx, {
            type: 'bar',
            data: {
                labels: ['Nov 14', 'Nov 15', 'Nov 16', 'Nov 17', 'Nov 18', 'Nov 19', 'Nov 20'],
                datasets: [{
                    label: 'Fire Detections',
                    data: [0, 1, 0, 0, 1, 0, 0],
                    backgroundColor: 'rgba(233, 69, 96, 0.7)',
                    borderColor: '#e94560',
                    borderWidth: 2
                }, {
                    label: 'Smoke Detections',
                    data: [1, 2, 0, 1, 0, 0, 0],
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
                        labels: {
                            color: '#e0e0e0'
                        }
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

        // Temperature Chart
        const tempCtx = document.getElementById('temperatureChart').getContext('2d');
        const temperatureChart = new Chart(tempCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => `${i}:00`),
                datasets: [{
                    label: 'Camera 1 Area Temp',
                    data: Array.from({length: 24}, () => 20 + Math.random() * 5),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Camera 2 Area Temp',
                    data: Array.from({length: 24}, () => 21 + Math.random() * 4),
                    borderColor: '#764ba2',
                    backgroundColor: 'rgba(118, 75, 162, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#e0e0e0'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 15,
                        max: 30,
                        ticks: { 
                            color: '#888',
                            callback: function(value) {
                                return value + '°C';
                            }
                        },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    x: {
                        ticks: { color: '#888' },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    }
                }
            }
        });

        // Activity Log
        function addActivityLog(message) {
            const activityLog = document.getElementById('activityLog');
            const now = new Date();
            const timestamp = now.toLocaleTimeString();
            
            const item = document.createElement('div');
            item.className = 'activity-item';
            item.innerHTML = `
                <div class="activity-timestamp">${timestamp}</div>
                <div class="activity-message">${message}</div>
            `;
            
            activityLog.insertBefore(item, activityLog.firstChild);
            
            // Keep only last 20 items
            while (activityLog.children.length > 20) {
                activityLog.removeChild(activityLog.lastChild);
            }
        }

        // Initial activity logs
        addActivityLog('System initialized successfully');
        addActivityLog('All cameras online and operational');
        addActivityLog('12 personnel checked in for duty');

        // Simulate real-time updates
        setInterval(() => {
            const temp = (20 + Math.random() * 5).toFixed(1);
            addActivityLog(`Temperature reading: ${temp}°C - Normal`);
        }, 30000);

        // Emergency functions
        let emergencyActive = false;

        function triggerEmergency(location, camera, confidence) {
            emergencyActive = true;
            const modal = document.getElementById('emergencyModal');
            document.getElementById('emergencyLocation').textContent = location;
            document.getElementById('emergencyCamera').textContent = camera;
            document.getElementById('emergencyConfidence').textContent = confidence;
            modal.classList.add('active');
            
            // Play alert sound (would need audio file)
            // new Audio('alert.mp3').play();
            
            // Update system status
            document.getElementById('systemStatus').textContent = 'EMERGENCY ALERT';
            document.getElementById('systemStatus').className = 'status-badge status-alert';
            
            // Add alert to list
            const alertsList = document.getElementById('alertsList');
            const now = new Date();
            const alert = document.createElement('div');
            alert.className = 'alert-item';
            alert.innerHTML = `
                <div class="alert-time">${now.toLocaleTimeString()}</div>
                <div class="alert-message">🔥 FIRE DETECTED - ${camera}</div>
                <div class="alert-location">${location} - Confidence: ${confidence}</div>
            `;
            alertsList.insertBefore(alert, alertsList.firstChild);
            
            addActivityLog(`🚨 EMERGENCY: Fire detected at ${location}`);
        }

        function dispatchFirefighters() {
            if (!emergencyActive) return;
            
            addActivityLog('🚒 Firefighters dispatched to emergency location');
            addActivityLog('📡 Sharing real-time location with response team');
            
            // Update personnel status
            const firefighters = document.querySelectorAll('.personnel-item.firefighter');
            firefighters.forEach((ff, index) => {
                if (index < 6) { // First station responds
                    const indicator = ff.querySelector('.status-indicator');
                    indicator.className = 'status-indicator responding';
                }
            });
            
            // Show route on map
            const emergencyLocation = [14.6005, 120.9850];
            const station1Location = [14.5950, 120.9800];
            
            L.polyline([station1Location, emergencyLocation], {
                color: '#e94560',
                weight: 4,
                opacity: 0.7,
                dashArray: '10, 10'
            }).addTo(map);
            
            closeEmergency();
        }

        function closeEmergency() {
            emergencyActive = false;
            document.getElementById('emergencyModal').classList.remove('active');
            document.getElementById('systemStatus').textContent = 'SYSTEM OPERATIONAL';
            document.getElementById('systemStatus').className = 'status-badge status-operational';
        }

        // Demo: Trigger emergency after 5 seconds (remove in production)
        // setTimeout(() => {
        //     triggerEmergency('Building A - Warehouse, Floor 3', 'Camera 1 - Visual ML', '94%');
        // }, 5000);

        // Simulate detection
        function simulateDetection() {
            const detectionIndicator = document.getElementById('detectionIndicator');
            detectionIndicator.classList.add('active');
            
            setTimeout(() => {
                detectionIndicator.classList.remove('active');
                triggerEmergency('Building A - Warehouse, Floor 3', 'Camera 1 - Visual ML', '94%');
            }, 3000);
        }

        // Add test button for emergency simulation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'T' || e.key === 't') {
                simulateDetection();
            }
        });

        console.log('Fire Detection Dashboard loaded successfully');
        console.log('Press "T" to simulate fire detection for testing');
    </script>
</body>
</html>