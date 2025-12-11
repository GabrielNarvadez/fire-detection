const STATION_ID = 1;   // or read from query string if you want dynamic stations

let soundEnabled = true;
let currentAlert = null;

// Optional demo alerts (you can keep for testing)
const sampleAlerts = [
    { type: 'fire',  location: 'EVSU Campus',      area: 'Industrial Zone',      confidence: '94%' },
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

    const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const localTime = new Date().toLocaleString();

    try {
        await fetch('firefighter.php?firefighter_station_update=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                firefighter_alert_id: currentAlert.firefighter_alert_id,
                response_type: 'responded'
            })
        });

        alert(
            '🚒 Response confirmed!\n\n' +
            `📍 Your timezone: ${userTimezone}\n` +
            `🕒 Local time: ${localTime}`
        );

    } catch (e) {
        console.error('Failed to send response', e);
    }

    clearAlert();
    fetchStationData();
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
        const time = timeStr
    ? new Date(timeStr + 'Z').toLocaleString('en-US', {
          year: 'numeric',
          month: '2-digit',
          day: '2-digit',
          hour: '2-digit',
          minute: '2-digit',
          hour12: true
      })
    : '';

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

// ============================================
// WEATHER API for Dulag, Leyte
// ============================================
async function fetchWeather() {
    try {
        // Dulag, Leyte coordinates
        const lat = 11.0550;
        const lon = 125.0331;
        
        // Using OpenWeatherMap API (free tier)
        // You can get your own API key at: https://openweathermap.org/api
        const apiKey = 'd8b8b8d8b8b8b8b8b8b8b8b8b8b8b8b8'; // Replace with real API key
        
        const url = `https://api.openweathermap.org/data/2.5/weather?lat=${lat}&lon=${lon}&units=metric&appid=${apiKey}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.main && data.weather) {
            // Update temperature
            document.getElementById('weatherTemp').textContent = `${Math.round(data.main.temp)}°C`;
            
            // Update humidity
            document.getElementById('weatherHumidity').textContent = `${data.main.humidity}%`;
            
            // Update weather description
            const desc = data.weather[0].main;
            document.getElementById('weatherDesc').textContent = desc;
            
            // Update weather icon based on condition
            const iconMap = {
                'Clear': '☀️',
                'Clouds': '☁️',
                'Rain': '🌧️',
                'Drizzle': '🌦️',
                'Thunderstorm': '⛈️',
                'Snow': '❄️',
                'Mist': '🌫️',
                'Fog': '🌫️',
                'Haze': '🌫️'
            };
            document.getElementById('weatherIcon').textContent = iconMap[desc] || '🌤️';
            
            // Update wind speed (convert m/s to km/h)
            const windKmh = (data.wind.speed * 3.6).toFixed(1);
            document.getElementById('weatherWind').textContent = `${windKmh} km/h`;
            
        } else {
            console.error('Weather data format unexpected:', data);
        }
    } catch (error) {
        console.error('Error fetching weather:', error);
        // Fallback to default values if API fails
        document.getElementById('weatherTemp').textContent = '--°C';
        document.getElementById('weatherHumidity').textContent = '--%';
        document.getElementById('weatherDesc').textContent = 'Unavailable';
        document.getElementById('weatherWind').textContent = '-- km/h';
    }
}

// Alternative: Use wttr.in (no API key needed)
async function fetchWeatherWttr() {
    try {
        const response = await fetch('https://wttr.in/Dulag,Leyte?format=j1');
        const data = await response.json();
        
        const current = data.current_condition[0];
        
        document.getElementById('weatherTemp').textContent = `${current.temp_C}°C`;
        document.getElementById('weatherHumidity').textContent = `${current.humidity}%`;
        document.getElementById('weatherDesc').textContent = current.weatherDesc[0].value;
        document.getElementById('weatherWind').textContent = `${current.windspeedKmph} km/h`;
        
        // Update icon based on weather code
        const code = parseInt(current.weatherCode);
        let icon = '🌤️';
        if (code === 113) icon = '☀️';  // Clear
        else if (code >= 116 && code <= 119) icon = '☁️';  // Cloudy
        else if (code >= 200 && code <= 232) icon = '⛈️';  // Thunderstorm
        else if (code >= 296 && code <= 399) icon = '🌧️';  // Rain
        
        document.getElementById('weatherIcon').textContent = icon;
        
    } catch (error) {
        console.error('Error fetching weather from wttr.in:', error);
    }
}

// Initial load + polling
fetchStationData();
setInterval(fetchStationData, 3000);

// Fetch weather on load and every 10 minutes
fetchWeatherWttr();  // Using wttr.in (no API key needed)
setInterval(fetchWeatherWttr, 600000);  // Update every 10 minutes

// Note: If you want to use OpenWeatherMap instead:
// 1. Get API key from https://openweathermap.org/api
// 2. Replace the apiKey in fetchWeather() function
// 3. Use fetchWeather() instead of fetchWeatherWttr()
// 4. Update the intervals accordingly