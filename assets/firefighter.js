const STATION_ID = 1;   // or read from query string if you want dynamic stations

let soundEnabled = true;
let currentAlert = null;

// Optional demo alerts (you can keep for testing)
const sampleAlerts = [
    { type: 'fire',  location: 'Building A - Warehouse',      area: 'Industrial Zone',      confidence: '94%' },
    { type: 'smoke', location: 'Floor 3 - Office Building',   area: 'Commercial District',  confidence: '87%' },
    { type: 'fire',  location: 'Residential Block 7',         area: 'Housing Complex',      confidence: '91%' }
];

// Time display
function updateTime() {
    const now = new Date();
    document.getElementById('currentDate').textContent =
        now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
    document.getElementById('currentTime').textContent =
        now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}
setInterval(updateTime, 1000);
updateTime();

// Stats coming from server
function updateStatsFromServer(stats) {
    if (!stats) return;
    document.getElementById('statResponded').textContent = stats.responded ?? 0;
    document.getElementById('statToday').textContent = stats.today ?? 0;

    if (stats.avg_response_time != null && stats.avg_response_time > 0) {
        // avg_response_time is seconds → convert to minutes
        const minutes = stats.avg_response_time / 60;
        document.getElementById('statAvgTime').textContent = minutes.toFixed(1) + 'm';
    } else {
        document.getElementById('statAvgTime').textContent = '-';
    }
}

// Beep sound
function playAlertSound() {
    if (!soundEnabled) return;
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioCtx.createOscillator();
    const gainNode = audioCtx.createGain();
    oscillator.connect(gainNode);
    gainNode.connect(audioCtx.destination);
    oscillator.frequency.value = 800;
    oscillator.type = 'square';
    gainNode.gain.value = 0.3;
    oscillator.start();
    setTimeout(() => gainNode.gain.value = 0, 200);
    setTimeout(() => gainNode.gain.value = 0.3, 400);
    setTimeout(() => gainNode.gain.value = 0, 600);
    setTimeout(() => gainNode.gain.value = 0.3, 800);
    setTimeout(() => oscillator.stop(), 1000);
}

// Show active alert
function showAlert(alert) {
    currentAlert = { ...alert, time: alert.time || new Date().toISOString() };

    document.getElementById('noAlert').style.display = 'none';
    document.getElementById('alertActive').classList.add('show');

    document.getElementById('alertIcon').textContent =
        alert.type === 'smoke' ? '💨' : '🔥';
    document.getElementById('alertTitle').textContent =
        alert.type === 'smoke' ? 'SMOKE DETECTED' : 'FIRE ALERT';

    document.getElementById('alertLocation').textContent = alert.location;
    document.getElementById('alertArea').textContent = alert.area;
    document.getElementById('alertConfidence').textContent = alert.confidence;
    document.getElementById('alertTime').textContent = '⏱️ Received just now';

    const badge = document.getElementById('statusBadge');
    badge.textContent = '🚨 ALERT';
    badge.classList.add('alert');

    playAlertSound();
    if (navigator.vibrate) navigator.vibrate([200, 100, 200, 100, 200]);
}

// Respond / acknowledge → backend
async function respondToAlert() {
    if (!currentAlert) return;
    try {
        await fetch('firefighter.php?firefighter_station_update=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                firefighter_alert_id: currentAlert.firefighter_alert_id,
                response_type: 'responded'
            })
        });
        alert('🚒 Response confirmed!\n\nDispatch notified.');
    } catch (e) {
        console.error('Failed to send response', e);
    }
    clearAlert();
    fetchStationData();  // refresh history and stats
}

async function acknowledgeAlert() {
    if (!currentAlert) return;
    try {
        await fetch('firefighter.php?firefighter_station_update=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                firefighter_alert_id: currentAlert.firefighter_alert_id,
                response_type: 'acknowledged'
            })
        });
    } catch (e) {
        console.error('Failed to send acknowledgement', e);
    }
    clearAlert();
    fetchStationData();
}

// Clear UI
function clearAlert() {
    currentAlert = null;
    document.getElementById('noAlert').style.display = 'block';
    document.getElementById('alertActive').classList.remove('show');
    const badge = document.getElementById('statusBadge');
    badge.textContent = '● STANDBY';
    badge.classList.remove('alert');
}

// History list from server
function updateHistoryFromServer(historyRows) {
    const list = document.getElementById('historyList');
    if (!historyRows || historyRows.length === 0) {
        list.innerHTML = '<div class="empty-history"><div class="icon">📭</div><p>No recent alerts</p></div>';
        return;
    }
    list.innerHTML = historyRows.slice(0, 5).map(row => {
        const timeStr = row.responded_at || row.received_at;
        const time = timeStr ? new Date(timeStr).toLocaleString() : '';
        const badge = row.status === 'responded' ? '✓ Responded' : '✓ Acknowledged';
        const icon = row.alert_type === 'smoke' ? '💨' : '🔥';
        return `
            <div class="history-item ${row.status === 'responded' ? 'responded' : ''}">
                <div class="history-header">
                    <span class="time">${time}</span>
                    <span class="type-badge">${badge}</span>
                </div>
                <div class="location">${icon} ${row.location || ''}</div>
            </div>
        `;
    }).join('');
}

// Sound toggle + demo
function toggleSound() {
    soundEnabled = !soundEnabled;
    document.getElementById('soundToggle').textContent = soundEnabled ? '🔔' : '🔕';
}

function simulateAlert() {
    showAlert(sampleAlerts[Math.floor(Math.random() * sampleAlerts.length)]);
}

// Poll backend
async function fetchStationData() {
    try {
        const res = await fetch(`firefighter.php?firefighter_station_api=1&station_id=${STATION_ID}`);
        const data = await res.json();
        if (!data.success) return;

        // Stats
        updateStatsFromServer(data.stats);

        // History
        updateHistoryFromServer(data.history || []);

        // Pending alert
        const pending = data.pending_alert;
        if (pending) {
            if (!currentAlert || currentAlert.firefighter_alert_id !== pending.id) {
                const alertObj = {
                    firefighter_alert_id: pending.id,
                    type: pending.alert_type || 'fire',
                    location: pending.location || 'Unknown location',
                    area: pending.area || 'Unknown area',
                    confidence: pending.confidence != null
                        ? `${Math.round(pending.confidence * 100)}%`
                        : '-',
                    time: pending.received_at
                };
                showAlert(alertObj);
            }
        } else {
            if (currentAlert) clearAlert();
        }
    } catch (e) {
        console.error('Error fetching station data', e);
    }
}

// Initial load + polling
fetchStationData();
setInterval(fetchStationData, 3000);
