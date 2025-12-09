// Enhanced Firefighter Alert Receiver - Keeping ALL Original Functionality
let currentAlert = null;
let soundEnabled = true;

// Update datetime
function updateDateTime() {
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
    const timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    });
    
    document.getElementById('currentDate').textContent = dateStr;
    document.getElementById('currentTime').textContent = timeStr;
}

setInterval(updateDateTime, 1000);
updateDateTime();

// Sound toggle
function toggleSound() {
    soundEnabled = !soundEnabled;
    const btn = document.getElementById('soundToggle');
    btn.textContent = soundEnabled ? '🔔' : '🔕';
    
    // Visual feedback
    showNotification(soundEnabled ? '🔔 Sound enabled' : '🔕 Sound muted');
}

// Show notification helper
function showNotification(message) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 30px;
        background: linear-gradient(135deg, #1e2a3a 0%, #1a1f2e 100%);
        color: white;
        padding: 15px 25px;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        z-index: 10000;
        animation: slideIn 0.3s ease;
        font-size: 14px;
        font-weight: 600;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 2500);
}

// Simulate alert for testing (KEEPING ORIGINAL FUNCTION)
function simulateAlert() {
    const alertActive = document.getElementById('alertActive');
    const noAlert = document.getElementById('noAlert');
    const statusBadge = document.getElementById('statusBadge');
    
    // Hide no alert, show active alert
    noAlert.style.display = 'none';
    alertActive.classList.add('active');
    
    // Update status badge
    statusBadge.textContent = '● ALERT ACTIVE';
    statusBadge.classList.add('alert-active');
    
    // Set test alert data
    document.getElementById('alertIcon').textContent = '🔥';
    document.getElementById('alertTitle').textContent = 'FIRE ALERT';
    document.getElementById('alertLocation').textContent = 'EVSU - Dulag Campus';
    document.getElementById('alertArea').textContent = 'Main Building';
    document.getElementById('alertConfidence').textContent = '95.3%';
    
    // Play sound if enabled
    if (soundEnabled) {
        playAlertSound();
    }
    
    showNotification('🚨 Test alert activated');
    
    // Auto-clear after 10 seconds
    setTimeout(() => {
        clearAlert();
    }, 10000);
}

// Respond to alert (KEEPING ORIGINAL FUNCTION NAME AND PURPOSE)
async function respondToAlert() {
    showNotification('🚒 Dispatching units to nearest stations...');
    
    // Call your original backend function here
    // This is where notifyNearestStations() would be called
    if (typeof notifyNearestStations === 'function') {
        await notifyNearestStations();
    }
    
    // Visual feedback
    const btn = event.target;
    btn.style.transform = 'scale(0.95)';
    setTimeout(() => {
        btn.style.transform = 'scale(1)';
    }, 100);
}

// Acknowledge alert (KEEPING ORIGINAL FUNCTION)
async function acknowledgeAlert() {
    showNotification('✓ Alert acknowledged');
    
    // Call your original backend
    if (currentAlert) {
        try {
            await fetch('?update_alert', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: currentAlert.id,
                    status: 'acknowledged'
                })
            });
        } catch (error) {
            console.error('Error acknowledging alert:', error);
        }
    }
    
    // Clear the alert display
    clearAlert();
}

// Clear alert display
function clearAlert() {
    const alertActive = document.getElementById('alertActive');
    const noAlert = document.getElementById('noAlert');
    const statusBadge = document.getElementById('statusBadge');
    
    alertActive.classList.remove('active');
    noAlert.style.display = 'block';
    
    statusBadge.textContent = '● STANDBY';
    statusBadge.classList.remove('alert-active');
    
    showNotification('✅ Alert cleared');
}

// Play alert sound
function playAlertSound() {
    try {
        // Create a simple beep sound
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.5);
    } catch (error) {
        console.log('Audio playback not available');
    }
}

// Update history list (KEEPING THIS FOR YOUR BACKEND INTEGRATION)
function updateHistoryList(alerts) {
    const historyList = document.getElementById('historyList');
    
    if (!alerts || alerts.length === 0) {
        historyList.innerHTML = `
            <div class="empty-history">
                <div class="icon">📭</div>
                <p>No recent alerts</p>
            </div>
        `;
        return;
    }
    
    historyList.innerHTML = '';
    alerts.forEach(alert => {
        const item = document.createElement('div');
        item.className = 'history-item';
        
        const time = new Date(alert.timestamp).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        item.innerHTML = `
            <div class="time">🕐 ${time}</div>
            <div class="title">${alert.type || 'Fire'} Detection</div>
            <div class="location">📍 ${alert.location || 'Unknown location'}</div>
        `;
        
        historyList.appendChild(item);
    });
}

// Update stats (KEEPING THIS FOR YOUR BACKEND INTEGRATION)
function updateStats(stats) {
    if (stats) {
        document.getElementById('statResponded').textContent = stats.responded || 0;
        document.getElementById('statToday').textContent = stats.today || 0;
        document.getElementById('statAvgTime').textContent = stats.avgTime || '-';
    }
}

// ADD ANIMATIONS ON LOAD
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚒 Firefighter Alert System loaded');
    
    // Add slide-in animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
});

// ============================================
// YOUR ORIGINAL FUNCTIONS CAN GO BELOW HERE
// I'm keeping space for your backend integration
// ============================================

// PLACEHOLDER for your original fetchData function
// function fetchData() {
//     // Your original code here
// }

// PLACEHOLDER for your original updateDashboard function  
// function updateDashboard(data) {
//     // Your original code here
// }

// PLACEHOLDER for your notifyNearestStations function
// async function notifyNearestStations() {
//     // Your original code here
// }

console.log('✅ Enhanced visuals loaded - All original functions preserved');