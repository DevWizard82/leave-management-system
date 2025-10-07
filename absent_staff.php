<?php
// api/absent_staff.php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';

$today = date('Y-m-d');

$sql = "
    SELECT 
        u.first_name, 
        u.last_name, 
        lr.start_date, 
        lr.end_date, 
        lt.type_name
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    JOIN leave_types lt ON lr.type_id = lt.id
    WHERE lr.status = 'approved'
      AND ? BETWEEN lr.start_date AND lr.end_date
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    // Return an error object so the frontend can show a meaningful message
    echo json_encode(['error' => 'db_prepare_failed', 'message' => $conn->error]);
    exit;
}

$stmt->bind_param('s', $today);
$stmt->execute();
$result = $stmt->get_result();

$absent = [];
while ($row = $result->fetch_assoc()) {
    $row['start_date'] = date('d-m-Y', strtotime($row['start_date']));
    $row['end_date'] = date('d-m-Y', strtotime($row['end_date']));
    $absent[] = $row;
}

echo json_encode($absent, JSON_UNESCAPED_UNICODE);
exit;
