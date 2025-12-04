<?php
include 'assets/functions.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $action = $input['action'] ?? null;
    $alertId = isset($input['alert_id']) ? (int)$input['alert_id'] : 0;

    if (!$action || $alertId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }

    $db = getDB();

    // Ensure the alert exists
    $stmt = $db->prepare('SELECT id FROM alerts WHERE id = :id');
    if (!$stmt) {
        throw new Exception('Failed to prepare alert lookup statement: ' . $db->lastErrorMsg());
    }
    $stmt->bindValue(':id', $alertId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result ? $result->fetchArray(SQLITE3_ASSOC) : false;

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Alert not found']);
        exit;
    }

    if ($action === 'accept') {
        $stmt = $db->prepare("UPDATE alerts SET admin_status = 'accepted' WHERE id = :id");
        $responseMessage = 'Alert accepted. Ready to notify firefighters.';
        $activityMessage = "[alert_accepted] Alert #$alertId accepted by admin";
    } elseif ($action === 'decline') {
        // Mark as declined and ensure it no longer appears as active
        $stmt = $db->prepare("UPDATE alerts SET admin_status = 'declined', status = 'closed' WHERE id = :id");
        $responseMessage = 'Alert declined and removed from active alerts.';
        $activityMessage = "[alert_declined] Alert #$alertId declined by admin";
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
    }

    if (!$stmt) {
        throw new Exception('Failed to prepare alert update statement: ' . $db->lastErrorMsg());
    }

    $stmt->bindValue(':id', $alertId, SQLITE3_INTEGER);
    $stmt->execute();

    // Log to activity table (message + default timestamp)
    $log = $db->prepare('INSERT INTO activity (message) VALUES (:message)');
    if ($log) {
        $log->bindValue(':message', $activityMessage, SQLITE3_TEXT);
        $log->execute();
    }

    echo json_encode([
        'success' => true,
        'message' => $responseMessage,
        'alert_id' => $alertId,
        'action' => $action,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
