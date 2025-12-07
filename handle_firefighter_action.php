<?php
session_start();
include 'assets/functions.php';
//handle_firefighter_action.php
header('Content-Type: application/json');

// Ensure firefighter is logged in
if (!isset($_SESSION['firefighter_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $alertId = isset($input['alert_id']) ? (int)$input['alert_id'] : 0;
    $status = $input['status'] ?? null; // 'responding' or 'acknowledged'

    if (!$alertId || !in_array($status, ['responding', 'acknowledged'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }

    $db = getDB();

    // Update the firefighter_status on the main alerts table
    $stmt = $db->prepare("UPDATE alerts SET firefighter_status = :status WHERE id = :id");
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->bindValue(':id', $alertId, SQLITE3_INTEGER);
    $stmt->execute();

    // You could add more detailed logging to firefighter_alerts or firefighter_stats here

    echo json_encode(['success' => true, 'message' => "Alert status updated to $status."]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}