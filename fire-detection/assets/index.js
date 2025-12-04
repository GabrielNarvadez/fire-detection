
        let dashboardData = null;
        let detectionChart = null;
        let map = null;
        let emergencyActive = false;
        let editingFirefighterId = null;
        let editingPersonnelId = null;

        // Helper to format ISO timestamps from backend into readable strings
        function formatTimestamp(timestamp) {
            try {
                const d = new Date(timestamp);
                if (Number.isNaN(d.getTime())) return timestamp;
                return d.toLocaleString('en-PH', {
                    year: 'numeric',
                    month: 'short',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            } catch (e) {
                console.error('Failed to format timestamp', timestamp, e);
                return timestamp;
            }
        }

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

        // ========================================
        // TAB SWITCHING
        // ========================================
        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(`tab-${tabName}`).classList.add('active');
        }

        // ========================================
        // DATA FETCHING
        // ========================================
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

            // Update detection summary under the history chart
            const summaryEl = document.getElementById('detectionSummary');
            if (summaryEl && data.stats) {
                const fireToday = data.stats.fire_today || 0;
                const smokeToday = data.stats.smoke_today || 0;
                const totalToday = data.stats.detections_today || (fireToday + smokeToday);
                summaryEl.textContent = `Overall detections today: ${totalToday} (Fire: ${fireToday}, Smoke: ${smokeToday})`;
            }

            // Update camera status
            if (data.cameras) {
                Object.values(data.cameras).forEach(camera => {
                    const camNum = camera.name.includes('1') ? '1' : '2';
                    const statusEl = document.getElementById(`cam${camNum}Status`);
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

            // Update alerts (use active alerts, falling back to recent detections)
            updateAlerts(data.alerts || [], data.detections || []);

            // Update personnel display
            updatePersonnelDisplay(data.personnel || []);

            // Update management lists
            updateFirefighterList(data.firefighters || []);
            updatePersonnelManagementList(data.personnel || []);

            // Update activity log
            updateActivity(data.activity || []);

            // Update chart
            updateChart(data.detection_history || []);

            // Check for critical alerts
            if (data.alerts && data.alerts.length > 0) {
                const criticalAlert = data.alerts.find(a => a.alert_level === 'critical' && a.status === 'active');
                if (criticalAlert && !emergencyActive) {
                    showEmergency(criticalAlert);
                }
            }
        }

        // Admin-only helper: update alert status to accepted/declined
        async function handleAdminAlertAction(alertId, action) {
            if (!alertId) return;
            const adminStatus = action === 'accept' ? 'accepted' : 'declined';

            try {
                const response = await fetch('assets/functions.php?update_alert=1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: alertId, admin_status: adminStatus })
                });

                const result = await response.json();
                if (!result.success) {
                    console.error('Failed to update alert status', result.error);
                    alert('Failed to update alert status: ' + (result.error || 'Unknown error'));
                    return;
                }

                // Refresh dashboard so Active Alerts and stats stay in sync
                fetchData();
            } catch (error) {
                console.error('Error updating alert status', error);
                alert('Network error while updating alert status. Please try again.');
            }
        }

        function updateAlerts(alerts, detections) {
            const alertsList = document.getElementById('alertsList');

            const items = [];

            // Prefer explicit alerts from the alerts table if present
            if (Array.isArray(alerts)) {
                alerts.forEach(row => {
                    const message = row.message || '';
                    const lower = message.toLowerCase();
                    const type = lower.includes('smoke') ? 'smoke' : 'fire';
                    const time = row.timestamp ? new Date(row.timestamp) : new Date();
                    let location = '-';
                    const parts = message.split('at ');
                    if (parts.length > 1) {
                        location = parts[1].split(' -')[0];
                    }

                    items.push({
                        source: 'alert',
                        id: row.id,
                        type,
                        level: row.alert_level || 'info',
                        location,
                        time,
                        rawMessage: message,
                        admin_status: row.admin_status || 'pending',
                        firefighter_status: row.firefighter_status || 'pending'
                    });
                });
            }

            // If there are no explicit alerts, fall back to recent detections (real-time from cameras)
            if (items.length === 0 && Array.isArray(detections)) {
                detections.slice(0, 5).forEach(row => {
                    const time = row.timestamp ? new Date(row.timestamp) : new Date();
                    items.push({
                        source: 'detection',
                        type: row.detection_type === 'smoke' ? 'smoke' : 'fire',
                        level: 'info',
                        location: row.location || row.camera_name || 'Unknown location',
                        time,
                        rawMessage: ''
                    });
                });
            }

            if (items.length === 0) {
                alertsList.innerHTML = `
                    <div class="alert-item alert-info">
                        <div class="alert-time">${new Date().toLocaleTimeString()}</div>
                        <div class="alert-message">No active alerts - All systems normal</div>
                    </div>
                `;
                // Ensure system status reflects no current alerts
                document.getElementById('systemStatus').textContent = 'SYSTEM OPERATIONAL';
                document.getElementById('systemStatus').className = 'status-badge status-operational';
                return;
            }

            alertsList.innerHTML = '';
            let hasCritical = false;
            let hasFire = false;

            items.slice(0, 5).forEach(item => {
                if (item.level === 'critical') {
                    hasCritical = true;
                }
                if (item.type === 'fire') {
                    hasFire = true;
                }

                const alertClass = item.source === 'alert'
                    ? (item.level === 'critical' ? '' : item.level === 'warning' ? 'alert-warning' : 'alert-info')
                    : (item.type === 'fire' ? '' : 'alert-info');

                const messageText =
                    item.source === 'alert'
                        ? (item.rawMessage || `${item.type === 'fire' ? '🔥 Fire alert' : '💨 Smoke alert'} at ${item.location}`)
                        : `${item.type === 'fire' ? '🔥 Fire detection' : '💨 Smoke detection'} at ${item.location}`;

                const adminStatusLabel = item.source === 'alert'
                    ? (item.admin_status === 'accepted'
                        ? 'Accepted'
                        : item.admin_status === 'declined'
                            ? 'Declined'
                            : 'Pending review')
                    : '';

                const showAdminActions =
                    item.source === 'alert' && item.id && (!item.admin_status || item.admin_status === 'pending');

                const statusHtml = item.source === 'alert'
                    ? `<div class="alert-status">${adminStatusLabel}</div>`
                    : '';

                const actionsHtml = showAdminActions
                    ? `<div class="alert-actions-row">
                            <button class="btn btn-small btn-accept" onclick="handleAdminAlertAction(${item.id}, 'accept')">Accept</button>
                            <button class="btn btn-small btn-decline" onclick="handleAdminAlertAction(${item.id}, 'decline')">Decline</button>
                       </div>`
                    : '';

                const div = document.createElement('div');
                div.className = `alert-item ${alertClass}`;
                div.innerHTML = `
                    <div class="alert-header-row">
                        <div class="alert-time">${item.time.toLocaleTimeString()}</div>
                        ${statusHtml}
                    </div>
                    <div class="alert-message">${messageText}</div>
                    ${actionsHtml}
                `;
                alertsList.appendChild(div);
            });

            // Update system status banner based on current alerts/detections
            const statusEl = document.getElementById('systemStatus');
            if (statusEl) {
                if (hasCritical || hasFire) {
                    statusEl.textContent = 'EMERGENCY ALERT';
                    statusEl.className = 'status-badge status-alert';
                } else {
                    statusEl.textContent = 'SYSTEM OPERATIONAL';
                    statusEl.className = 'status-badge status-operational';
                }
            }
        }

        function updatePersonnelDisplay(personnel) {
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
                    <div class="status-indicator ${person.status === 'online' ? '' : 'offline'}"></div>
                `;
                list.appendChild(div);
            });
        }


        /// ========================================modified=========================//

        function updateActivity(activities) {
    const log = document.getElementById('activityLog');
    log.innerHTML = '';
    
    activities.slice(0, 15).forEach(activity => {
        const time = formatTimestamp(activity.timestamp);
        const div = document.createElement('div');
        div.className = 'activity-item';
        div.innerHTML = `
            <div class="activity-time">${time}</div>
            <div>${activity.message}</div>
        `;
        log.appendChild(div);
    });
}

        // ========================================
        // CHART UPDATE (30-min intervals)
        // ========================================
        function updateChart(historyData) {
            if (!detectionChart) return;

            // Generate labels for last 24 hours in 30-min intervals
            const labels = [];
            const fireData = [];
            const smokeData = [];
            
            // Create a map of existing data keyed by normalized interval timestamp
            const dataMap = {};
            historyData.forEach(item => {
                try {
                    const d = new Date(item.interval_start);
                    if (Number.isNaN(d.getTime())) return;
                    const key = d.toISOString().slice(0, 19).replace('T', ' ');
                    dataMap[key] = item;
                } catch (e) {
                    console.error('Invalid detection_history interval_start', item.interval_start, e);
                }
            });

            // Generate 48 intervals (24 hours * 2)
            const now = new Date();
            for (let i = 47; i >= 0; i--) {
                const intervalTime = new Date(now.getTime() - (i * 30 * 60 * 1000));
                const minute = Math.floor(intervalTime.getMinutes() / 30) * 30;
                intervalTime.setMinutes(minute, 0, 0);
                
                const label = intervalTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                labels.push(label);

                // Find matching data using the same normalized key format as above
                const key = intervalTime.toISOString().slice(0, 19).replace('T', ' ');
                const data = dataMap[key] || { fire_count: 0, smoke_count: 0 };
                fireData.push(data.fire_count);
                smokeData.push(data.smoke_count);
            }

            detectionChart.data.labels = labels;
            detectionChart.data.datasets[0].data = fireData;
            detectionChart.data.datasets[1].data = smokeData;
            detectionChart.update('none');
        }

        // ========================================
        // EMERGENCY MODAL
        // ========================================
        function showEmergency(alert) {
            emergencyActive = true;
            document.getElementById('emergencyLocation').textContent = alert.message.split('at ')[1]?.split(' -')[0] || 'Unknown';
            document.getElementById('emergencyCamera').textContent = 'Camera Detection';
            document.getElementById('emergencyConfidence').textContent = alert.message.match(/\d+%/)?.[0] || 'High';
            document.getElementById('emergencyModal').classList.add('active');
        }

        function closeEmergency() {
            emergencyActive = false;
            document.getElementById('emergencyModal').classList.remove('active');
        }

        // ========================================
        // NOTIFICATION MODAL
        // ========================================
        let currentAlert = null;

        function showNotificationSelection() {
            currentAlert = {
                location: document.getElementById('emergencyLocation').textContent,
                camera: document.getElementById('emergencyCamera').textContent,
                confidence: document.getElementById('emergencyConfidence').textContent
            };

            closeEmergency();
            
            const container = document.getElementById('firefighterCheckboxes');
            container.innerHTML = '';
            
            const firefighters = dashboardData?.firefighters || [];
            firefighters.forEach((ff) => {
                const label = document.createElement('label');
                label.style.cssText = 'display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 8px; margin-bottom: 8px; cursor: pointer;';
                label.innerHTML = `
                    <input type="checkbox" class="ff-notify-checkbox" data-id="${ff.id}" style="width: 18px; height: 18px;">
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
            const firefighters = dashboardData?.firefighters || [];
            
            document.querySelectorAll('.ff-notify-checkbox:checked').forEach(cb => {
                const id = parseInt(cb.dataset.id);
                const ff = firefighters.find(f => f.id === id);
                if (ff) selectedFirefighters.push(ff);
            });

            if (selectedFirefighters.length === 0) {
                alert('Please select at least one firefighter to notify');
                return;
            }

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

        // ========================================
        // FIREFIGHTER MANAGEMENT
        // ========================================
        function updateFirefighterList(firefighters) {
            const list = document.getElementById('firefighterList');
            
            if (firefighters.length === 0) {
                list.innerHTML = '<div class="empty-state">No firefighters added yet</div>';
                return;
            }

            list.innerHTML = '';
            firefighters.forEach(ff => {
                const card = document.createElement('div');
                card.className = 'management-card';
                card.innerHTML = `
                    <div class="management-card-info">
                        <h4>👨‍🚒 ${ff.name}</h4>
                        <p>📱 ${ff.phone}</p>
                        <p>🏢 Station ${ff.station}</p>
                    </div>
                    <div class="management-card-actions">
                        <button class="btn-small" onclick="editFirefighter(${ff.id})">✏️ Edit</button>
                        <button class="btn-small danger" onclick="deleteFirefighter(${ff.id})">🗑️ Delete</button>
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

        function editFirefighter(id) {
            const firefighters = dashboardData?.firefighters || [];
            const ff = firefighters.find(f => f.id === id);
            if (!ff) return;

            editingFirefighterId = id;
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

            const data = { name, phone, station: parseInt(station) };
            
            if (editingFirefighterId) {
                data.id = editingFirefighterId;
                await fetch('?firefighter=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
            } else {
                await fetch('?firefighter=add', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
            }

            closeFirefighterModal();
            fetchData();
        }

        async function deleteFirefighter(id) {
            if (!confirm('Are you sure you want to delete this firefighter?')) return;

            await fetch('?firefighter=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            fetchData();
        }

        function closeFirefighterModal() {
            document.getElementById('firefighterModal').classList.remove('active');
        }

        // ========================================
        // PERSONNEL MANAGEMENT
        // ========================================
        function updatePersonnelManagementList(personnel) {
            const list = document.getElementById('personnelManagementList');
            
            if (personnel.length === 0) {
                list.innerHTML = '<div class="empty-state">No personnel added yet</div>';
                return;
            }

            list.innerHTML = '';
            personnel.forEach(p => {
                const typeIcon = p.type === 'admin' ? '👔' : p.type === 'firefighter' ? '👨‍🚒' : '👤';
                const card = document.createElement('div');
                card.className = 'management-card personnel';
                card.innerHTML = `
                    <div class="management-card-info">
                        <h4>${typeIcon} ${p.name}</h4>
                        <p>📋 ${p.role}</p>
                        <p>🏷️ ${p.type.charAt(0).toUpperCase() + p.type.slice(1)} ${p.phone ? '| 📱 ' + p.phone : ''} ${p.station ? '| 🏢 Station ' + p.station : ''}</p>
                        <p>Status: <span style="color: ${p.status === 'online' ? '#2ed573' : '#888'}">${p.status}</span></p>
                    </div>
                    <div class="management-card-actions">
                        <button class="btn-small" onclick="editPersonnel(${p.id})">✏️ Edit</button>
                        <button class="btn-small danger" onclick="deletePersonnel(${p.id})">🗑️ Delete</button>
                    </div>
                `;
                list.appendChild(card);
            });
        }

        function showAddPersonnel() {
            editingPersonnelId = null;
            document.getElementById('personnelModalTitle').textContent = 'Add Personnel';
            document.getElementById('personnelName').value = '';
            document.getElementById('personnelRole').value = '';
            document.getElementById('personnelType').value = 'admin';
            document.getElementById('personnelPhone').value = '';
            document.getElementById('personnelStation').value = '';
            document.getElementById('personnelStatus').value = 'online';
            document.getElementById('personnelModal').classList.add('active');
        }

        function editPersonnel(id) {
            const personnel = dashboardData?.personnel || [];
            const p = personnel.find(x => x.id === id);
            if (!p) return;

            editingPersonnelId = id;
            document.getElementById('personnelModalTitle').textContent = 'Edit Personnel';
            document.getElementById('personnelName').value = p.name;
            document.getElementById('personnelRole').value = p.role;
            document.getElementById('personnelType').value = p.type;
            document.getElementById('personnelPhone').value = p.phone || '';
            document.getElementById('personnelStation').value = p.station || '';
            document.getElementById('personnelStatus').value = p.status || 'online';
            document.getElementById('personnelModal').classList.add('active');
        }

        async function savePersonnel() {
            const name = document.getElementById('personnelName').value.trim();
            const role = document.getElementById('personnelRole').value.trim();
            const type = document.getElementById('personnelType').value;
            const phone = document.getElementById('personnelPhone').value.trim();
            const station = document.getElementById('personnelStation').value;
            const status = document.getElementById('personnelStatus').value;

            if (!name || !role) {
                alert('Please fill in name and role');
                return;
            }

            const data = { 
                name, 
                role, 
                type,
                phone: phone || null,
                station: station || null,
                status
            };
            
            if (editingPersonnelId) {
                data.id = editingPersonnelId;
                await fetch('?personnel=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
            } else {
                await fetch('?personnel=add', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
            }

            closePersonnelModal();
            fetchData();
        }

        async function deletePersonnel(id) {
            if (!confirm('Are you sure you want to delete this personnel?')) return;

            await fetch('?personnel=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            fetchData();
        }

        function closePersonnelModal() {
            document.getElementById('personnelModal').classList.remove('active');
        }

        // ========================================
        // MAP INITIALIZATION
        // ========================================
        function initMap() {
            map = L.map('map').setView([14.5995, 120.9842], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

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

        // ========================================
        // CHART INITIALIZATION
        // ========================================
        function initChart() {
            const ctx = document.getElementById('detectionChart').getContext('2d');
            detectionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Fire',
                        data: [],
                        backgroundColor: 'rgba(233, 69, 96, 0.7)',
                        borderColor: '#e94560',
                        borderWidth: 2
                    }, {
                        label: 'Smoke',
                        data: [],
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
                        },
                        title: {
                            display: true,
                            text: 'Detections per 30-minute interval (Last 24 hours)',
                            color: '#e0e0e0'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#888', stepSize: 1 },
                            grid: { color: 'rgba(255, 255, 255, 0.05)' }
                        },
                        x: {
                            ticks: { 
                                color: '#888',
                                maxRotation: 45,
                                minRotation: 45,
                                autoSkip: true,
                                maxTicksLimit: 12
                            },
                            grid: { color: 'rgba(255, 255, 255, 0.05)' }
                        }
                    }
                }
            });
        }

        // ========================================
        // CAMERA FEED REFRESH
        // ========================================
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
        // INITIALIZATION
        // ========================================
        async function init() {
            initMap();
            initChart();
            await fetchData();
            
            // Auto-refresh data every 3 seconds
            setInterval(fetchData, 3000);
            
            // Auto-refresh camera feeds every 500ms
            setInterval(refreshCameraFeeds, 500);
            
            console.log('Dashboard initialized');
            console.log('Data refreshes every 3 seconds');
        }

        document.addEventListener('DOMContentLoaded', init);


        // Image loading with retry
function loadImageWithRetry(imgElement, src, retries = 3) {
    return new Promise((resolve, reject) => {
        imgElement.onerror = () => {
            if (retries > 0) {
                setTimeout(() => {
                    imgElement.src = src + '?t=' + Date.now();
                    loadImageWithRetry(imgElement, src, retries - 1).then(resolve).catch(reject);
                }, 500);
            } else {
                imgElement.style.display = 'none';
                imgElement.nextElementSibling.style.display = 'flex';
                reject();
            }
        };
        imgElement.onload = () => {
            imgElement.style.display = 'block';
            imgElement.nextElementSibling.style.display = 'none';
            resolve();
        };
    });
}

// (legacy local formatTimestamp removed; we now use the shared helper declared at the top of this file.)
