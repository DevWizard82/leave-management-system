<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;

if (!$userId) {
    echo json_encode(['error' => true, 'message' => 'Utilisateur non connecté']);
    exit();
}

// Fetch pending or active approved leave requests
$stmt = $conn->prepare("
    SELECT lr.id, lr.start_date, lr.end_date, lr.status, lr.created_at, lr.days_requested, lt.type_name
    FROM leave_requests lr
    JOIN leave_types lt ON lr.type_id = lt.id
    WHERE lr.user_id = ? 
      AND (lr.status = 'pending' OR (lr.status = 'approved' AND lr.end_date >= CURDATE()))
    ORDER BY lr.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

echo json_encode(['error' => false, 'requests' => $requests]);
