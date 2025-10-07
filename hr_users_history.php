<?php
require_once 'config.php';
session_start();
header("Content-Type: application/json");

// Ensure user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID missing']);
    exit;
}

$userId = intval($_GET['id']);
$today = date('Y-m-d');

// Correct query: join leave_requests with leave_types to get type_name
$query = $conn->prepare("
    SELECT lt.type_name AS leave_type, lr.start_date, lr.end_date, lr.status
    FROM leave_requests lr
    JOIN leave_types lt ON lr.type_id = lt.id
    WHERE lr.user_id = ?
      AND (
            lr.status = 'cancelled'
            OR (lr.status IN ('approved', 'rejected') AND lr.end_date <= ?)
          )
    ORDER BY lr.start_date DESC
");

$query->bind_param("is", $userId, $today);
$query->execute();
$result = $query->get_result();

$leaves = [];
while ($row = $result->fetch_assoc()) {
    $leaves[] = $row;
}

// Return clean JSON
echo json_encode(['success' => true, 'leaves' => $leaves]);
exit;
