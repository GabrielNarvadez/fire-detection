<?php
include 'assets/functions.php';

header('Content-Type: application/json');

try {
	// Use shared SQLite connection and the alerts table.
	// Only expose alerts that are still active AND have been accepted by the admin.
	$db = getDB();
	$result = $db->query("SELECT * FROM alerts WHERE status = 'active' AND admin_status = 'accepted' ORDER BY timestamp DESC");

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
