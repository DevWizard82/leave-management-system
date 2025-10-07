<?php
require_once 'config.php';
session_start();

// Make sure HR is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$hrId = $_SESSION['user_id'];

// Fetch HR leave history: finished, cancelled, or past approved
$sql = "
    SELECT lr.id, lr.start_date, lr.end_date, lr.status, lr.created_at, lt.type_name
    FROM leave_requests lr
    JOIN leave_types lt ON lr.type_id = lt.id
    WHERE lr.user_id = ? 
      AND (
            lr.status IN ('cancelled', 'refused') 
            OR (lr.status = 'approved' AND lr.end_date < CURDATE())
      )
    ORDER BY lr.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $hrId);
$stmt->execute();
$result = $stmt->get_result();

$history = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            'id' => (int)$row['id'],
            'type_name' => $row['type_name'],
            'created_at' => $row['created_at'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'status' => $row['status']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['history' => $history]);
