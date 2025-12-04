<?php
include 'assets/functions.php';

header('Content-Type: application/json');

try {
	// Use shared SQLite connection and return raw detections for history views
	$db = getDB();

	$stmt = $db->prepare('SELECT * FROM detections ORDER BY timestamp DESC LIMIT 50');
	$result = $stmt->execute();

	$detections = [];
	if ($result) {
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			if (isset($row['timestamp'])) {
				$row['timestamp'] = formatTimestamp($row['timestamp']);
			}
			$detections[] = $row;
		}
	}

	echo json_encode($detections);
} catch (Exception $e) {
	echo json_encode(['error' => $e->getMessage()]);
}
