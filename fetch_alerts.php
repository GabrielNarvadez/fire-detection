<?php
include 'assets/functions.php';

header('Content-Type: application/json');

try {
    $db = getDB();

    // For now: expose all alerts that are active and accepted by admin
    $result = $db->query("
        SELECT *
        FROM alerts
        WHERE status = 'active'
          AND admin_status = 'accepted'
        ORDER BY timestamp DESC
    ");

    $alerts = [];

    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // If you want firefighter.js to use this timestamp directly as Date
            // you can leave it raw (e.g. '2025-12-05 14:23:00')
            $alerts[] = $row;
        }
    }

    echo json_encode($alerts);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

?>
