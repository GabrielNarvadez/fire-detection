<?php
include 'assets/functions.php';

header('Content-Type: application/json');

try {
    $db = new SQLite3('fire_detection.db');
    $result = $db->query('SELECT * FROM activities ORDER BY timestamp DESC');

    $activities = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $activities[] = $row;
    }
// Return the activities as JSON
    echo json_encode($activities);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
