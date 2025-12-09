
    let dashboardData = null;
    let detectionChart = null;
    let map = null;
    let emergencyActive = false;
    let editingFirefighterId = null;
    let editingPersonnelId = null;
    let currentAlert = null;


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

            // Update alerts
            updateAlerts(data.alerts || []);

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

        // ========================================
        // CHART UPDATE (30-min intervals)
        // ========================================
        function updateChart(historyData) {
            if (!detectionChart) return;
 const BUCKET_MS = 30 * 60 * 1000;  // 30 minutes in milliseconds

 // Map from time-bucket (ms since epoch) to aggregated counts
 const dataMap = {};

 historyData.forEach(item => {
     const d = new Date(item.interval_start);  // e.g. "2025-12-08T05:00:00"
     const time = d.getTime();

     if (isNaN(time)) {
         console.warn("Cannot parse interval_start:", item.interval_start, item);
         return;
     }

     // Floor to the previous 30-minute bucket
     const bucketTime = Math.floor(time / BUCKET_MS) * BUCKET_MS;

     if (!dataMap[bucketTime]) {
         dataMap[bucketTime] = { fire_count: 0, smoke_count: 0 };
     }

     dataMap[bucketTime].fire_count += Number(item.fire_count) || 0;
     dataMap[bucketTime].smoke_count += Number(item.smoke_count) || 0;
 });

 const labels = [];
 const fireData = [];
 const smokeData = [];
 const now = new Date();

 // Build the last 24 hours in 30-minute buckets
 for (let i = 47; i >= 0; i--) {
     const intervalTime = new Date(now.getTime() - i * BUCKET_MS);

     // Snap the minutes to 0 or 30 for display label
     const minute = Math.floor(intervalTime.getMinutes() / 30) * 30;
     intervalTime.setMinutes(minute, 0, 0);

     const bucketTime = Math.floor(intervalTime.getTime() / BUCKET_MS) * BUCKET_MS;

     labels.push(
         intervalTime.toLocaleTimeString("en-US", {
             hour: "2-digit",
             minute: "2-digit"
         })
     );

     const bucketData = dataMap[bucketTime] || { fire_count: 0, smoke_count: 0 };

     fireData.push(bucketData.fire_count);
     smokeData.push(bucketData.smoke_count);
 }

 detectionChart.data.labels = labels;
 detectionChart.data.datasets[0].data = fireData;
 detectionChart.data.datasets[1].data = smokeData;
 detectionChart.update("none");

        }

        // ========================================
        // EMERGENCY MODAL
        // ========================================
function showEmergency(alert) {
    emergencyActive = true;

    // Find the corresponding detection object from the dashboard data
    const detection = dashboardData?.detections?.find(d => d.id === alert.detection_id);

    // Use detection data if available, otherwise fall back to parsing from message or defaults
    const location = detection?.location || alert.message.split('at ')[1]?.split(' -')[0] || 'Unknown';
    const cameraName = detection?.camera_name || 'Camera Detection';
    const rawConfidence = detection?.confidence; // This will be the float, e.g., 0.753
    const formattedConfidence = rawConfidence != null ? `${(rawConfidence * 100).toFixed(1)}%` : 'High'; // Format as "75.3%"

    // Keep ids and add parsed fields
    currentAlert = {
        id: alert.id,
        detection_id: alert.detection_id,
        alert_level: alert.alert_level,
        status: alert.status,
        message: alert.message, // Keep original message for context
        location,
        camera: cameraName,
        confidence: rawConfidence // Store the numeric confidence for consistency
    };

    document.getElementById('emergencyLocation').textContent = location;
    document.getElementById('emergencyCamera').textContent = cameraName;
    document.getElementById('emergencyConfidence').textContent = formattedConfidence;
    document.getElementById('emergencyModal').classList.add('active');
}


function closeEmergency() {
    document.getElementById('emergencyModal').classList.remove('active');
}

        // ========================================
        // NOTIFICATION MODAL
        // ========================================
function showNotificationSelection() {
    // Update fields but keep id and detection_id
currentAlert = {
    ...currentAlert,
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


        async function notifyNearestStations() {
    if (!dashboardData || !currentAlert) {
        alert('No alert data available');
        return;
    }

    const detections = dashboardData.detections || [];
    const stations = dashboardData.stations || [];
    const firefighters = dashboardData.firefighters || [];

    const detection = detections.find(d => d.id === currentAlert.detection_id);
    if (!detection) {
        alert('Cannot find detection for this alert');
        return;
    }

    if (detection.latitude == null || detection.longitude == null) {
        alert('Detection has no coordinates, cannot find nearest stations');
        return;
    }

    const originLat = parseFloat(detection.latitude);
    const originLng = parseFloat(detection.longitude);

    function toRad(deg) {
        return deg * Math.PI / 180;
    }

    function distanceKm(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    const stationsWithDistance = stations
        .filter(s => s.latitude != null && s.longitude != null)
        .map(s => ({
            ...s,
            distance: distanceKm(
                originLat,
                originLng,
                parseFloat(s.latitude),
                parseFloat(s.longitude)
            )
        }))
        .sort((a, b) => a.distance - b.distance);

    if (stationsWithDistance.length === 0) {
        alert('No stations with coordinates configured');
        return;
    }

    const targets = stationsWithDistance.slice(0, 2);
    const targetIds = targets.map(s => s.id);

    const firefightersToNotify = firefighters.filter(ff =>
        targetIds.includes(ff.station)
    );

    // Show a summary message instead of checkboxes
    let msg = 'Sending signals to fire stations:\n\n';
    targets.forEach(s => {
        const count = firefightersToNotify.filter(ff => ff.station === s.id).length;
        msg += `• ${s.name} (${count} firefighters)\n`;
    });

    msg += '\nTexting:\n';
    firefightersToNotify.forEach(ff => {
        msg += `  - ${ff.name} (${ff.phone})\n`;
    });

    alert(msg);

    // Tell backend to create station alerts in firefighter_alerts
try {
    await fetch('?station_alert=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            alert_id: currentAlert.id,
            detection_id: detection.id,
            alert_type: detection.detection_type,  // 'fire' or 'smoke'
            location: detection.location,
            area: detection.location,             // or a more detailed area field if you have one
            confidence: detection.confidence,
            stations: targetIds                   // [stationId1, stationId2]
        })
    });
} catch (e) {
    console.error('Failed to create firefighter alerts', e);
}


    // Mark the alert as acknowledged so it stops reappearing
    try {
        await fetch('?update_alert', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: currentAlert.id,
                status: 'acknowledged'
            })
        });
    } catch (e) {
        console.error('Failed to update alert status', e);
    }

    emergencyActive = false;
    closeEmergency();
    fetchData();  // refresh so this alert disappears from "active"
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

            // Format confidence for the message
            const formattedConfidence = currentAlert.confidence != null ? `${(currentAlert.confidence * 100).toFixed(1)}%` : 'High';

            if (selectedFirefighters.length === 0) {
                alert('Please select at least one firefighter to notify');
                return;
            }

            let message = `📱 SMS SENT TO:\n\n`;
            selectedFirefighters.forEach(ff => {
                message += `✓ ${ff.name} (${ff.phone})\n`;
            });
            message += `\nMessage: "FIRE ALERT at ${currentAlert.location}. Confidence: ${formattedConfidence}. Respond immediately."`;

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
            map = L.map('map').setView([10.9543, 125.0196], 17);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            const cameraIcon = L.divIcon({
                html: '<div style="background: #e94560; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; font-size: 16px;">📹</div>',
                iconSize: [30, 30]
            });

            L.marker([10.9543, 125.0196], {icon: cameraIcon})
                .addTo(map)
                .bindPopup('<strong>Camera 1 & 2</strong><br>EVSU - Dulag Campus');

            const stationIcon = L.divIcon({
                html: '<div style="background: #5352ed; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; font-size: 20px;">🚒</div>',
                iconSize: [35, 35]
            });

            // Use the accurate coordinates for the real Dulag Fire Station
            L.marker([10.9548, 125.0233], {icon: stationIcon})
                .addTo(map)
                .bindPopup('<strong>Dulag Fire Station</strong><br>Station 1');

            // Add two more placeholder stations
            L.marker([11.0380, 125.0350], {icon: stationIcon})
                .addTo(map)
                .bindPopup('<strong>Tolosa Fire Station</strong><br>Station 2');

            L.marker([10.8880, 125.0070], {icon: stationIcon})
                .addTo(map)
                .bindPopup('<strong>Mayorga Fire Station</strong><br>Station 3');
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
