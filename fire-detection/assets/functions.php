<?php
/**
 * Fire Detection Dashboard - SQLite Version
 * Serves HTML dashboard and provides JSON data API
 */

// Configuration
define('DATABASE_PATH', __DIR__ . '/../fire_detection.db');
define('UPLOAD_DIR', 'uploads');
define('ANNOTATED_DIR', 'annotated');

// Create directories if they don't exist
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
if (!is_dir(ANNOTATED_DIR)) mkdir(ANNOTATED_DIR, 0777, true);

// Database connection
function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new SQLite3(DATABASE_PATH);
            $db->busyTimeout(5000);
            $db->exec('PRAGMA journal_mode = WAL');  // Better concurrency
            $db->exec('PRAGMA synchronous = NORMAL'); // Faster writes
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $db;
}

function verifyImagePath($path) {
    if ($path && file_exists($path)) {
        return $path;
    }
    return null;
}

/**
 * Format timestamp consistently
 */
function formatTimestamp($timestamp) {
    try {
        $dt = new DateTime($timestamp);
        return $dt->format('c'); // ISO 8601 format
    } catch (Exception $e) {
        return $timestamp;
    }
}

function recordNotification($db, $alertId, $decision = 'accepted') {
    $check = $db->prepare('SELECT id FROM notifications WHERE alert_id = :alert_id AND decision = :decision ORDER BY id DESC LIMIT 1');
    if ($check) {
        $check->bindValue(':alert_id', $alertId, SQLITE3_INTEGER);
        $check->bindValue(':decision', $decision, SQLITE3_TEXT);
        $existing = $check->execute();
        if ($existing && ($row = $existing->fetchArray(SQLITE3_ASSOC))) {
            return (int)$row['id'];
        }
    }

    $stmt = $db->prepare('INSERT INTO notifications (alert_id, type, status, decision) VALUES (:alert_id, :type, :status, :decision)');
    if ($stmt) {
        $stmt->bindValue(':alert_id', $alertId, SQLITE3_INTEGER);
        $stmt->bindValue(':type', 'firefighter', SQLITE3_TEXT);
        $stmt->bindValue(':status', 'sent', SQLITE3_TEXT);
        $stmt->bindValue(':decision', $decision, SQLITE3_TEXT);
        $stmt->execute();
        return (int)$db->lastInsertRowID();
    }

    return null;
}

function deleteNotificationsForAlert($db, $alertId) {
    $stmt = $db->prepare('DELETE FROM notifications WHERE alert_id = :alert_id');
    if ($stmt) {
        $stmt->bindValue(':alert_id', $alertId, SQLITE3_INTEGER);
        $stmt->execute();
    }
}

function ensureFirefighterAlertRow($db, $alertId, $status = 'pending') {
    $stmt = $db->prepare('SELECT id FROM firefighter_alerts WHERE alert_id = :alert_id LIMIT 1');
    if ($stmt) {
        $stmt->bindValue(':alert_id', $alertId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            return (int)$row['id'];
        }
    }

    $insert = $db->prepare('INSERT INTO firefighter_alerts (alert_id, firefighter_status) VALUES (:alert_id, :status)');
    if ($insert) {
        $insert->bindValue(':alert_id', $alertId, SQLITE3_INTEGER);
        $insert->bindValue(':status', $status, SQLITE3_TEXT);
        $insert->execute();
        return (int)$db->lastInsertRowID();
    }

    return null;
}

function updateFirefighterAlertRow($db, $alertId, $newStatus) {
    $stmt = $db->prepare('SELECT firefighter_status, responded_at, acknowledged_at FROM firefighter_alerts WHERE alert_id = :alert_id LIMIT 1');
    $current = null;
    if ($stmt) {
        $stmt->bindValue(':alert_id', $alertId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result) {
            $current = $result->fetchArray(SQLITE3_ASSOC) ?: null;
        }
    }

    if (!$current) {
        ensureFirefighterAlertRow($db, $alertId, $newStatus);
        $current = [
            'firefighter_status' => 'pending',
            'responded_at' => null,
            'acknowledged_at' => null
        ];
    }

    $fields = [];
    $respondedJustSet = false;
    $ackJustSet = false;

    if ($current['firefighter_status'] !== $newStatus) {
        $fields[] = 'firefighter_status = :status';
    }

    if ($newStatus === 'responding' && empty($current['responded_at'])) {
        $fields[] = 'responded_at = CURRENT_TIMESTAMP';
        $respondedJustSet = true;
    }

    if ($newStatus === 'acknowledged' && empty($current['acknowledged_at'])) {
        $fields[] = 'acknowledged_at = CURRENT_TIMESTAMP';
        $ackJustSet = true;
    }

    if (empty($fields)) {
        return ['respondedJustSet' => $respondedJustSet, 'ackJustSet' => $ackJustSet];
    }

    $sql = 'UPDATE firefighter_alerts SET ' . implode(', ', $fields) . ' WHERE alert_id = :alert_id';
    $update = $db->prepare($sql);
    if ($update) {
        $update->bindValue(':alert_id', $alertId, SQLITE3_INTEGER);
        if (strpos($sql, ':status') !== false) {
            $update->bindValue(':status', $newStatus, SQLITE3_TEXT);
        }
        $update->execute();
    }

    return ['respondedJustSet' => $respondedJustSet, 'ackJustSet' => $ackJustSet];
}

function updateFirefighterStats($db, $status, $alertId, array $transitions = []) {
    if (!in_array($status, ['responding', 'acknowledged'], true)) {
        return;
    }

    if ($status === 'responding' && empty($transitions['respondedJustSet'])) {
        return;
    }

    if ($status === 'acknowledged' && empty($transitions['ackJustSet'])) {
        return;
    }

    $today = date('Y-m-d');
    $db->exec("INSERT OR IGNORE INTO firefighter_stats (date, responded_count, acknowledged_count, avg_response_time) VALUES ('$today', 0, 0, 0)");

    $stats = $db->querySingle("SELECT responded_count, acknowledged_count, avg_response_time FROM firefighter_stats WHERE date='$today'", true);
    if (!$stats) {
        return;
    }

    if ($status === 'responding') {
        $sentAt = null;
        $stmt = $db->prepare('SELECT sent_at FROM notifications WHERE alert_id = :alert_id ORDER BY id DESC LIMIT 1');
        if ($stmt) {
            $stmt->bindValue(':alert_id', $alertId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if ($result && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
                $sentAt = $row['sent_at'] ?? null;
            }
        }

        $diffMinutes = 0;
        if ($sentAt) {
            $diff = (strtotime('now') - strtotime($sentAt));
            $diffMinutes = max(0, $diff / 60);
        }

        $responded = (int)$stats['responded_count'];
        $avg = isset($stats['avg_response_time']) ? (float)$stats['avg_response_time'] : 0.0;
        $newResponded = $responded + 1;
        $newAvg = $newResponded > 0 ? (($avg * $responded) + $diffMinutes) / $newResponded : $diffMinutes;

        $update = $db->prepare('UPDATE firefighter_stats SET responded_count = :responded, avg_response_time = :avg WHERE date = :date');
        if ($update) {
            $update->bindValue(':responded', $newResponded, SQLITE3_INTEGER);
            $update->bindValue(':avg', $newAvg, SQLITE3_FLOAT);
            $update->bindValue(':date', $today, SQLITE3_TEXT);
            $update->execute();
        }
    }

    if ($status === 'acknowledged') {
        $acknowledged = (int)$stats['acknowledged_count'] + 1;
        $update = $db->prepare('UPDATE firefighter_stats SET acknowledged_count = :ack WHERE date = :date');
        if ($update) {
            $update->bindValue(':ack', $acknowledged, SQLITE3_INTEGER);
            $update->bindValue(':date', $today, SQLITE3_TEXT);
            $update->execute();
        }
    }
}



// Debug endpoint to check database
if (isset($_GET['debug'])) {
    header('Content-Type: application/json');
    $db = getDB();
    $dbPath = DATABASE_PATH;
    $exists = file_exists($dbPath);
    $writable = is_writable($dbPath);
    $dirWritable = is_writable(dirname($dbPath));
    
    // Test insert
    $testResult = $db->exec("CREATE TABLE IF NOT EXISTS _test (id INTEGER PRIMARY KEY)");
    $testInsert = $db->exec("INSERT INTO _test (id) VALUES (1)");
    $db->exec("DELETE FROM _test WHERE id = 1");
    
    // Count firefighters
    $ffCount = $db->querySingle("SELECT COUNT(*) FROM firefighters");
    
    echo json_encode([
        'database_path' => $dbPath,
        'exists' => $exists,
        'writable' => $writable,
        'dir_writable' => $dirWritable,
        'test_create' => $testResult,
        'test_insert' => $testInsert,
        'firefighter_count' => $ffCount,
        'last_error' => $db->lastErrorMsg()
    ], JSON_PRETTY_PRINT);
    exit;
}

// Initialize database tables if needed
function initDatabase() {
    $db = getDB();
    
    // Cameras table
    $db->exec('
        CREATE TABLE IF NOT EXISTS cameras (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            type TEXT NOT NULL,
            location TEXT NOT NULL,
            latitude REAL,
            longitude REAL,
            status TEXT DEFAULT "offline",
            temperature REAL DEFAULT 22.0,
            frame_path TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
    
    // Detections table
    $db->exec('
        CREATE TABLE IF NOT EXISTS detections (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            camera_id INTEGER NOT NULL,
            camera_name TEXT NOT NULL,
            detection_type TEXT NOT NULL,
            confidence REAL NOT NULL,
            image_path TEXT,
            clip_path TEXT,
            location TEXT,
            latitude REAL,
            longitude REAL,
            status TEXT DEFAULT "pending",
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
    
    // Alerts table
    $db->exec('
        CREATE TABLE IF NOT EXISTS alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            detection_id INTEGER,
            alert_level TEXT NOT NULL,
            message TEXT NOT NULL,
            status TEXT DEFAULT "active",
            admin_status TEXT DEFAULT "pending",
            firefighter_status TEXT DEFAULT "pending",
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');

    // Ensure new alert workflow columns exist for older databases
    $result = $db->query("PRAGMA table_info(alerts)");
    $existingColumns = [];
    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if (isset($row['name'])) {
                $existingColumns[] = $row['name'];
            }
        }
    }
    if (!in_array('admin_status', $existingColumns, true)) {
        $db->exec("ALTER TABLE alerts ADD COLUMN admin_status TEXT DEFAULT 'pending'");
    }
    if (!in_array('firefighter_status', $existingColumns, true)) {
        $db->exec("ALTER TABLE alerts ADD COLUMN firefighter_status TEXT DEFAULT 'pending'");
    }

    // Activity log table
    $db->exec('
        CREATE TABLE IF NOT EXISTS activity (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message TEXT NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
    
    // Firefighters table
    $db->exec('
        CREATE TABLE IF NOT EXISTS firefighters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            phone TEXT NOT NULL,
            station INTEGER DEFAULT 1,
            status TEXT DEFAULT "online",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
    
    // Personnel table
    $db->exec('
        CREATE TABLE IF NOT EXISTS personnel (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            role TEXT NOT NULL,
            type TEXT NOT NULL,
            phone TEXT,
            station INTEGER,
            status TEXT DEFAULT "online",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
    
    // Stations table
    $db->exec('
        CREATE TABLE IF NOT EXISTS stations (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            latitude REAL,
            longitude REAL,
            personnel_count INTEGER DEFAULT 0
        )
    ');
    
    // Stats table
    $db->exec('
        CREATE TABLE IF NOT EXISTS stats (
            id INTEGER PRIMARY KEY,
            date DATE UNIQUE,
            detections_today INTEGER DEFAULT 0,
            fire_today INTEGER DEFAULT 0,
            smoke_today INTEGER DEFAULT 0,
            avg_response_time REAL DEFAULT 3.2
        )
    ');
    
    // Detection history for charts
    $db->exec('
        CREATE TABLE IF NOT EXISTS detection_history (
        	id INTEGER PRIMARY KEY AUTOINCREMENT,
        	interval_start TIMESTAMP NOT NULL,
        	fire_count INTEGER DEFAULT 0,
        	smoke_count INTEGER DEFAULT 0,
        	UNIQUE(interval_start)
        )
    ');

    // Firefighter-facing alerts table (for aggregating firefighter history/stats)
    $db->exec('
        CREATE TABLE IF NOT EXISTS firefighter_alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            alert_id INTEGER NOT NULL,
            firefighter_status TEXT DEFAULT "pending",
            responded_at TIMESTAMP,
            acknowledged_at TIMESTAMP
        )
    ');

    // High-level firefighter statistics
    $db->exec('
        CREATE TABLE IF NOT EXISTS firefighter_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date DATE NOT NULL,
            responded_count INTEGER DEFAULT 0,
            acknowledged_count INTEGER DEFAULT 0,
            avg_response_time REAL DEFAULT 0,
            UNIQUE(date)
        )
    ');

    // Notifications table for accepted/declined alerts pushed to firefighters
    $db->exec('
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            alert_id INTEGER NOT NULL,
            type TEXT NOT NULL DEFAULT "firefighter",
            status TEXT NOT NULL DEFAULT "pending",
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            decision TEXT NOT NULL DEFAULT "pending"
        )
    ');
    
    // Insert default data
    insertDefaultData($db);
}

function insertDefaultData($db) {
    // Check if cameras exist
    $result = $db->querySingle("SELECT COUNT(*) FROM cameras");
    if ($result == 0) {
        $db->exec("
            INSERT INTO cameras (id, name, type, location, latitude, longitude, status, temperature, frame_path) VALUES
            (1, 'Camera 1 - Visual ML', 'visual', 'Building A - Warehouse', 14.6005, 120.9850, 'offline', 22.0, 'camera_frames/camera1_live.jpg'),
            (2, 'Camera 2 - Thermal', 'thermal', 'Building A - Warehouse', 14.6010, 120.9855, 'offline', 22.5, 'camera_frames/camera2_live.jpg')
        ");
    }
    
    // Check if stations exist
    $result = $db->querySingle("SELECT COUNT(*) FROM stations");
    if ($result == 0) {
        $db->exec("
            INSERT INTO stations (id, name, latitude, longitude, personnel_count) VALUES
            (1, 'Fire Station 1', 14.5950, 120.9800, 6),
            (2, 'Fire Station 2', 14.6040, 120.9900, 6)
        ");
    }
    
    // Check if personnel exist
    $result = $db->querySingle("SELECT COUNT(*) FROM personnel");
    if ($result == 0) {
        $db->exec("
            INSERT INTO personnel (name, role, type, phone, station) VALUES
            ('Admin Johnson', 'System Administrator', 'admin', NULL, NULL),
            ('Admin Chen', 'Operations Manager', 'admin', NULL, NULL)
        ");
    }
    
    // Ensure today's stats exist
    $today = date('Y-m-d');
    $db->exec("INSERT OR IGNORE INTO stats (id, date) VALUES (1, '$today')");
}

// Initialize database
initDatabase();

// Handle alert status updates from frontend (admin and firefighter views)
if (isset($_GET['update_alert'])) {
    header('Content-Type: application/json');
    $db = getDB();

    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? intval($input['id']) : 0;
    $adminStatus = $input['admin_status'] ?? null;
    $firefighterStatus = $input['firefighter_status'] ?? null;

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Missing alert ID']);
        exit;
    }

    if ($adminStatus === null && $firefighterStatus === null) {
        echo json_encode(['success' => false, 'error' => 'No status fields provided']);
        exit;
    }

    $fields = [];
    if ($adminStatus !== null) {
        $fields[] = 'admin_status = :admin_status';
    }
    if ($firefighterStatus !== null) {
        $fields[] = 'firefighter_status = :firefighter_status';
    }

    $sql = 'UPDATE alerts SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    if ($adminStatus !== null) {
        $stmt->bindValue(':admin_status', $adminStatus, SQLITE3_TEXT);
    }
    if ($firefighterStatus !== null) {
        $stmt->bindValue(':firefighter_status', $firefighterStatus, SQLITE3_TEXT);
    }
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);

    $result = $stmt->execute();
    if ($result) {
        // If the admin made a decision (Accept or Disable), record it in the notifications table
        if ($adminStatus !== null) {
            $decision = $adminStatus; // expected values: "accepted" or "declined"
            $notifStmt = $db->prepare('INSERT INTO notifications (alert_id, type, status, decision) VALUES (:alert_id, :type, :status, :decision)');
            $notifStmt->bindValue(':alert_id', $id, SQLITE3_INTEGER);
            $notifStmt->bindValue(':type', 'firefighter', SQLITE3_TEXT);
            $notifStmt->bindValue(':status', 'pending', SQLITE3_TEXT);
            $notifStmt->bindValue(':decision', $decision, SQLITE3_TEXT);
            $notifStmt->execute();
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->lastErrorMsg()]);
    }
    exit;
}

// =============================================
// API Handlers
// =============================================

// Handle file upload
if (isset($_GET['upload'])) {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }
    
    $file = $_FILES['file'];
    $type = $_POST['type'] ?? 'image';
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $filepath = UPLOAD_DIR . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        exit;
    }
    
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

// Handle firefighter operations
if (isset($_GET['firefighter'])) {
    header('Content-Type: application/json');
    $db = getDB();
    $action = $_GET['firefighter'];
    
    if ($action === 'list') {
        $result = $db->query("SELECT * FROM firefighters ORDER BY station, name");
        $firefighters = [];
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $firefighters[] = $row;
            }
        }
        echo json_encode(['success' => true, 'firefighters' => $firefighters]);
    }
    elseif ($action === 'add') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        $phone = $input['phone'] ?? '';
        $station = intval($input['station'] ?? 1);
        
        if ($name && $phone) {
            $stmt = $db->prepare("INSERT INTO firefighters (name, phone, station) VALUES (:name, :phone, :station)");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
            $stmt->bindValue(':station', $station, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result) {
                echo json_encode(['success' => true, 'id' => $db->lastInsertRowID()]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->lastErrorMsg()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Name and phone required']);
        }
    }
    elseif ($action === 'update') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $name = $input['name'] ?? '';
        $phone = $input['phone'] ?? '';
        $station = intval($input['station'] ?? 1);
        
        if ($id && $name && $phone) {
            $stmt = $db->prepare("UPDATE firefighters SET name=:name, phone=:phone, station=:station WHERE id=:id");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
            $stmt->bindValue(':station', $station, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->lastErrorMsg()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
        }
    }
    elseif ($action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        if ($id) {
            $stmt = $db->prepare("DELETE FROM firefighters WHERE id=:id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->lastErrorMsg()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        }
    }
    exit;
}

// Handle personnel operations
if (isset($_GET['personnel'])) {
    header('Content-Type: application/json');
    $db = getDB();
    $action = $_GET['personnel'];
    
    if ($action === 'list') {
        $result = $db->query("SELECT * FROM personnel ORDER BY type, name");
        $personnel = [];
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $personnel[] = $row;
            }
        }
        echo json_encode(['success' => true, 'personnel' => $personnel]);
    }
    elseif ($action === 'add') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? '';
        $role = $input['role'] ?? '';
        $type = $input['type'] ?? 'admin';
        $phone = $input['phone'] ?? null;
        $station = isset($input['station']) && $input['station'] !== '' ? intval($input['station']) : null;
        $status = $input['status'] ?? 'online';
        
        if ($name && $role) {
            $stmt = $db->prepare("INSERT INTO personnel (name, role, type, phone, station, status) VALUES (:name, :role, :type, :phone, :station, :status)");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $stmt->bindValue(':type', $type, SQLITE3_TEXT);
            $stmt->bindValue(':phone', $phone, $phone ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':station', $station, $station !== null ? SQLITE3_INTEGER : SQLITE3_NULL);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $result = $stmt->execute();
            
            if ($result) {
                echo json_encode(['success' => true, 'id' => $db->lastInsertRowID()]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->lastErrorMsg()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Name and role required']);
        }
    }
    elseif ($action === 'update') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        $name = $input['name'] ?? '';
        $role = $input['role'] ?? '';
        $type = $input['type'] ?? 'admin';
        $phone = $input['phone'] ?? null;
        $station = isset($input['station']) && $input['station'] !== '' ? intval($input['station']) : null;
        $status = $input['status'] ?? 'online';
        
        if ($id && $name && $role) {
            $stmt = $db->prepare("UPDATE personnel SET name=:name, role=:role, type=:type, phone=:phone, station=:station, status=:status WHERE id=:id");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $stmt->bindValue(':type', $type, SQLITE3_TEXT);
            $stmt->bindValue(':phone', $phone, $phone ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':station', $station, $station !== null ? SQLITE3_INTEGER : SQLITE3_NULL);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->lastErrorMsg()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
        }
    }
    elseif ($action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = intval($input['id'] ?? 0);
        
        if ($id) {
            $stmt = $db->prepare("DELETE FROM personnel WHERE id=:id");
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->lastErrorMsg()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        }
    }
    exit;
}

// Handle creating a new alert from a detection (admin inline Accept/Decline)
if (isset($_GET['alerts']) && $_GET['alerts'] === 'create') {
    header('Content-Type: application/json');
    $db = getDB();

    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $detectionId = isset($input['detection_id']) ? intval($input['detection_id']) : null;
    $type = isset($input['type']) ? trim($input['type']) : null; // 'fire' | 'smoke'
    $location = isset($input['location']) ? trim($input['location']) : '';
    $message = isset($input['message']) ? trim($input['message']) : '';
    $alertLevel = isset($input['alert_level']) ? trim($input['alert_level']) : 'info';
    $adminStatus = isset($input['admin_status']) ? trim($input['admin_status']) : 'pending'; // 'accepted' | 'declined' | 'pending'

    if ($message === '' && $type) {
        $label = ($type === 'smoke') ? 'Smoke' : 'Fire';
        $message = ($label === 'Fire' ? '🔥 ' : '💨 ') . "$label alert at " . ($location ?: 'Unknown location');
    }

    if ($message === '') {
        echo json_encode(['success' => false, 'error' => 'Missing message for alert']);
        exit;
    }

    // Insert alert
    $stmt = $db->prepare('INSERT INTO alerts (detection_id, alert_level, message, status, admin_status, firefighter_status) VALUES (:detection_id, :alert_level, :message, :status, :admin_status, :firefighter_status)');
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database prepare failed: ' . $db->lastErrorMsg()]);
        exit;
    }

    $stmt->bindValue(':detection_id', $detectionId !== null ? $detectionId : null, $detectionId !== null ? SQLITE3_INTEGER : SQLITE3_NULL);
    $stmt->bindValue(':alert_level', $alertLevel, SQLITE3_TEXT);
    $stmt->bindValue(':message', $message, SQLITE3_TEXT);
    $stmt->bindValue(':status', 'active', SQLITE3_TEXT);
    $stmt->bindValue(':admin_status', $adminStatus, SQLITE3_TEXT);
    $stmt->bindValue(':firefighter_status', 'pending', SQLITE3_TEXT);

    $result = $stmt->execute();
    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Insert failed: ' . $db->lastErrorMsg()]);
        exit;
    }

    $alertId = (int)$db->lastInsertRowID();

    // Ensure firefighter aggregation row exists
    ensureFirefighterAlertRow($db, $alertId, 'pending');

    // Record notification if admin already decided
    if (in_array($adminStatus, ['accepted', 'declined'], true)) {
        recordNotification($db, $alertId, $adminStatus);

        // Log to activity feed
        $label = ($adminStatus === 'accepted') ? 'alert_accepted' : 'alert_declined';
        $logMessage = sprintf('[%s] Alert #%d %s by admin', $label, $alertId, $adminStatus);
        $logStmt = $db->prepare('INSERT INTO activity (message) VALUES (:message)');
        if ($logStmt) {
            $logStmt->bindValue(':message', $logMessage, SQLITE3_TEXT);
            $logStmt->execute();
        }
    }

    echo json_encode(['success' => true, 'id' => $alertId]);
    exit;
}

// Handle alert status update (admin accept/decline, firefighter acknowledge/respond)
if (isset($_GET['update_alert'])) {
    header('Content-Type: application/json');
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = intval($input['id'] ?? 0);
    $status = isset($input['status']) ? SQLite3::escapeString($input['status']) : null;
    $adminStatus = isset($input['admin_status']) ? SQLite3::escapeString($input['admin_status']) : null;
    $firefighterStatus = isset($input['firefighter_status']) ? SQLite3::escapeString($input['firefighter_status']) : null;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        exit;
    }

    $setParts = [];
    if ($status !== null && $status !== '') {
        $setParts[] = "status='$status'";
    }
    if ($adminStatus !== null && $adminStatus !== '') {
        $setParts[] = "admin_status='$adminStatus'";
        // If admin explicitly declines, close the alert so it no longer appears as active
        if ($adminStatus === 'declined') {
            $setParts[] = "status='closed'";
        }
    }
    if ($firefighterStatus !== null && $firefighterStatus !== '') {
        $setParts[] = "firefighter_status='$firefighterStatus'";
    }

    if (empty($setParts)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        exit;
    }

    $setSql = implode(', ', $setParts);
    $success = $db->exec("UPDATE alerts SET $setSql WHERE id=$id");

    if ($success) {
        // Log admin decisions in the activity table so they appear in the dashboard feed
        if ($adminStatus !== null && $adminStatus !== '') {
            $label = $adminStatus === 'accepted'
                ? 'alert_accepted'
                : ($adminStatus === 'declined' ? 'alert_declined' : 'alert_updated');
            $logMessage = sprintf('[%s] Alert #%d %s by admin', $label, $id, $adminStatus);

            $stmt = $db->prepare("INSERT INTO activity (message) VALUES (:message)");
            if ($stmt) {
                $stmt->bindValue(':message', $logMessage, SQLITE3_TEXT);
                $stmt->execute();
            }
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $db->lastErrorMsg()]);
    }
    exit;
}

// Main API endpoint - get all dashboard data
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    $db = getDB();
    
    // Get cameras with verified frame paths
    $cameras = [];
    $result = $db->query("SELECT * FROM cameras ORDER BY id");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['frame_path'] = verifyImagePath($row['frame_path']);
        $row['updated_at'] = formatTimestamp($row['updated_at']);
        $cameras[$row['id']] = $row;
    }
    
    // Get detections with verified image paths
    $detections = [];
    $result = $db->query("SELECT * FROM detections ORDER BY timestamp DESC LIMIT 100");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $imagePath = verifyImagePath($row['image_path']);
        if ($imagePath) { // Only include detections with valid images
            $row['image_path'] = $imagePath;
            $row['timestamp'] = formatTimestamp($row['timestamp']);
            $detections[] = $row;
        }
    }
    
    // Get active alerts with formatted timestamps (same source as firefighter view)
    $alerts = [];
    $result = $db->query("SELECT * FROM alerts WHERE status = 'active' ORDER BY timestamp DESC LIMIT 20");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['timestamp'] = formatTimestamp($row['timestamp']);
        $alerts[] = $row;
    }
    
    // Get activity with formatted timestamps
    $activity = [];
    $result = $db->query("SELECT * FROM activity ORDER BY timestamp DESC LIMIT 50");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['timestamp'] = formatTimestamp($row['timestamp']);
        $activity[] = $row;
    }
    
    // Get firefighters
    $firefighters = [];
    $result = $db->query("SELECT * FROM firefighters ORDER BY station, name");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $firefighters[] = $row;
    }
    
    // Get personnel
    $personnel = [];
    $result = $db->query("SELECT * FROM personnel ORDER BY type, name");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $personnel[] = $row;
    }
    
    // Get stations
    $stations = [];
    $result = $db->query("SELECT * FROM stations ORDER BY id");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $stations[] = $row;
    }
    
    // Get stats with computed values from detections table
    $today = date('Y-m-d');
    $stats = [
        'detections_today' => (int)$db->querySingle("SELECT COUNT(*) FROM detections WHERE date(timestamp) = '$today'"),
        'fire_today' => (int)$db->querySingle("SELECT COUNT(*) FROM detections WHERE date(timestamp) = '$today' AND detection_type = 'fire'"),
        'smoke_today' => (int)$db->querySingle("SELECT COUNT(*) FROM detections WHERE date(timestamp) = '$today' AND detection_type = 'smoke'"),
        'avg_response_time' => 3.2  // Default value, can be updated from firefighter_stats if needed
    ];
    
    // Add real-time computed stats
    $stats['active_cameras'] = $db->querySingle("SELECT COUNT(*) FROM cameras WHERE status='online'");
    $stats['personnel_online'] = $db->querySingle("SELECT COUNT(*) FROM personnel WHERE status='online'");
    
    // Get detection history (30-min intervals) aggregated from detections table (last 24 hours)
    $detection_history = [];
    $result = $db->query("
        SELECT
            strftime('%Y-%m-%d %H:', timestamp) ||
            CASE WHEN CAST(strftime('%M', timestamp) AS INTEGER) < 30 THEN '00:00' ELSE '30:00' END
            AS interval_start,
            SUM(CASE WHEN detection_type = 'fire' THEN 1 ELSE 0 END) AS fire_count,
            SUM(CASE WHEN detection_type = 'smoke' THEN 1 ELSE 0 END) AS smoke_count
        FROM detections
        WHERE timestamp >= datetime('now', '-24 hours')
        GROUP BY interval_start
        ORDER BY interval_start ASC
    ");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row['interval_start'] = formatTimestamp($row['interval_start']);
        $detection_history[] = $row;
    }
    
    // Construct response
    $response = [
        'cameras' => $cameras,
        'detections' => $detections,
        'alerts' => $alerts,
        'activity' => $activity,
        'firefighters' => $firefighters,
        'personnel' => $personnel,
        'stations' => $stations,
        'stats' => $stats,
        'detection_history' => $detection_history,
        'last_update' => date('c')
    ];
    
    echo json_encode($response);
    exit;
}
// Serve the dashboard HTML
?>