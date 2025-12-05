<?php
include 'assets/functions.php';

header('Content-Type: application/json');

try {
	// Use shared SQLite connection and the alerts table.
	// Only expose alerts that are still active AND have been accepted by the admin.
    $db = getDB();
	// Only expose alerts that are still active, have been accepted by the admin,
	// and have a corresponding notification entry for firefighters.
    $result = $db->query("\n        SELECT a.*,\n               n.id AS notification_id,\n               n.status AS notification_status,\n               n.sent_at,\n               n.decision\n        FROM alerts a\n        JOIN notifications n ON n.alert_id = a.id AND n.decision = 'accepted'\n        WHERE a.status = 'active' AND a.admin_status = 'accepted'\n        ORDER BY n.sent_at DESC, a.timestamp DESC\n    ");

	$alerts = [];
	if ($result) {
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			// Normalize timestamp so the frontend can parse it easily
			if (isset($row['timestamp'])) {
				$row['timestamp'] = formatTimestamp($row['timestamp']);
			}
			$alerts[] = $row;
		}
	}

	echo json_encode($alerts);
} catch (Exception $e) {
	echo json_encode(['error' => $e->getMessage()]);
}
