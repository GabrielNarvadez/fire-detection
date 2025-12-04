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
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
    
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

// Handle alert status update
if (isset($_GET['update_alert'])) {
    header('Content-Type: application/json');
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    $status = SQLite3::escapeString($input['status'] ?? 'acknowledged');
    
    if ($id) {
        $db->exec("UPDATE alerts SET status='$status' WHERE id=$id");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    }
    exit;
}

// Main API endpoint - get all dashboard data
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $db = getDB();
    
    // Get cameras
    $cameras = [];
    $result = $db->query("SELECT * FROM cameras ORDER BY id");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $cameras[$row['id']] = $row;
    }
    
    // Get detections
    $detections = [];
    $result = $db->query("SELECT * FROM detections ORDER BY timestamp DESC LIMIT 100");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $detections[] = $row;
    }
    
    // Get alerts
    $alerts = [];
    $result = $db->query("SELECT * FROM alerts ORDER BY timestamp DESC LIMIT 20");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $alerts[] = $row;
    }
    
    // Get activity
    $activity = [];
    $result = $db->query("SELECT * FROM activity ORDER BY timestamp DESC LIMIT 50");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
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
    
    // Get stats
    $today = date('Y-m-d');
    $stats = $db->querySingle("SELECT * FROM stats WHERE date='$today'", true);
    if (!$stats) {
        $stats = [
            'detections_today' => 0,
            'fire_today' => 0,
            'smoke_today' => 0,
            'avg_response_time' => 3.2
        ];
    }
    $stats['active_cameras'] = $db->querySingle("SELECT COUNT(*) FROM cameras WHERE status='online'");
    $stats['personnel_online'] = $db->querySingle("SELECT COUNT(*) FROM personnel WHERE status='online'");
    
    // Get detection history (last 24 hours, 30-min intervals)
    $detection_history = [];
    $result = $db->query("SELECT * FROM detection_history WHERE interval_start >= datetime('now', '-24 hours') ORDER BY interval_start ASC");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $detection_history[] = $row;
    }
    
    echo json_encode([
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
    ]);
    exit;
}

// Serve the dashboard HTML
?>