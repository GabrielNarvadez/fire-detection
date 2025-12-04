
        let soundEnabled = true;
        let currentAlert = null;
        let alertHistory = JSON.parse(localStorage.getItem('ffAlertHistory')) || [];

        const sampleAlerts = [
            { type: 'fire', location: 'Building A - Warehouse', area: 'Industrial Zone', confidence: '94%' },
            { type: 'smoke', location: 'Floor 3 - Office Building', area: 'Commercial District', confidence: '87%' },
            { type: 'fire', location: 'Residential Block 7', area: 'Housing Complex', confidence: '91%' }
        ];

        function updateTime() {
            const now = new Date();
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }
        setInterval(updateTime, 1000);
        updateTime();

        function updateStats() {
            const responded = alertHistory.filter(a => a.status === 'responded').length;
            const today = alertHistory.filter(a => new Date(a.time).toDateString() === new Date().toDateString()).length;
            document.getElementById('statResponded').textContent = responded;
            document.getElementById('statToday').textContent = today;
            document.getElementById('statAvgTime').textContent = responded > 0 ? '2.3m' : '-';
        }

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

        function showAlert(alert) {
            currentAlert = { ...alert, time: new Date().toISOString() };
            document.getElementById('noAlert').style.display = 'none';
            document.getElementById('alertActive').classList.add('show');
            document.getElementById('alertIcon').textContent = alert.type === 'fire' ? '🔥' : '💨';
            document.getElementById('alertTitle').textContent = alert.type === 'fire' ? 'FIRE ALERT' : 'SMOKE DETECTED';
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

        function respondToAlert() {
            if (!currentAlert) return;
            currentAlert.status = 'responded';
            alertHistory.unshift(currentAlert);
            localStorage.setItem('ffAlertHistory', JSON.stringify(alertHistory.slice(0, 20)));
            alert('🚒 Response confirmed!\n\nDispatch notified.');
            clearAlert();
            renderHistory();
            updateStats();
        }

        function acknowledgeAlert() {
            if (!currentAlert) return;
            currentAlert.status = 'acknowledged';
            alertHistory.unshift(currentAlert);
            localStorage.setItem('ffAlertHistory', JSON.stringify(alertHistory.slice(0, 20)));
            clearAlert();
            renderHistory();
            updateStats();
        }

        function clearAlert() {
            currentAlert = null;
            document.getElementById('noAlert').style.display = 'block';
            document.getElementById('alertActive').classList.remove('show');
            const badge = document.getElementById('statusBadge');
            badge.textContent = '● STANDBY';
            badge.classList.remove('alert');
        }

        function renderHistory() {
            const list = document.getElementById('historyList');
            if (alertHistory.length === 0) {
                list.innerHTML = '<div class="empty-history"><div class="icon">📭</div><p>No recent alerts</p></div>';
                return;
            }
            list.innerHTML = alertHistory.slice(0, 5).map(item => `
                <div class="history-item ${item.status === 'responded' ? 'responded' : ''}">
                    <div class="history-header">
                        <span class="time">${new Date(item.time).toLocaleString()}</span>
                        <span class="type-badge">${item.status === 'responded' ? '✓ Responded' : '✓ Acknowledged'}</span>
                    </div>
                    <div class="location">${item.type === 'fire' ? '🔥' : '💨'} ${item.location}</div>
                </div>
            `).join('');
        }

        function toggleSound() {
            soundEnabled = !soundEnabled;
            document.getElementById('soundToggle').textContent = soundEnabled ? '🔔' : '🔕';
        }

        function simulateAlert() {
            showAlert(sampleAlerts[Math.floor(Math.random() * sampleAlerts.length)]);
        }

        renderHistory();
        updateStats();
