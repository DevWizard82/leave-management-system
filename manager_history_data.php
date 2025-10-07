<?php
require_once 'config.php';
session_start();

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['error' => 'Utilisateur non authentifié']);
    exit;
}

$status = $_GET['status'] ?? '';
$typeId = $_GET['type_id'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$sql = "
    SELECT lr.id, lr.start_date, lr.end_date, lr.status, lr.created_at, lr.days_requested, lt.type_name
    FROM leave_requests lr
    JOIN leave_types lt ON lr.type_id = lt.id
    WHERE lr.user_id = ?
      AND (
            lr.status = 'cancelled'
            OR lr.status = 'rejected'
            OR (lr.status = 'approved' AND lr.end_date < CURDATE())
          )
";

// Add filters dynamically
$params = [$userId];
$types = "i";

if (!empty($status)) {
    $sql .= " AND lr.status = ? ";
    $params[] = $status;
    $types .= "s";
}

if (!empty($typeId)) {
    $sql .= " AND lr.type_id = ? ";
    $params[] = $typeId;
    $types .= "i";
}

if (!empty($startDate) && !empty($endDate)) {
    $sql .= " AND lr.start_date >= ? AND lr.end_date <= ? ";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= "ss";
}

$sql .= " ORDER BY lr.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

echo json_encode(['history' => $history]);