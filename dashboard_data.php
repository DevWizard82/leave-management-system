<?php
require_once './config.php'; // adjust path if needed
session_start();

$today = date('Y-m-d');
$hrId = $_SESSION['user_id'] ?? 0;

// Fetch dashboard stats
$absentStaff = $conn->query("
    SELECT COUNT(*) 
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    WHERE lr.status = 'approved' AND '$today' BETWEEN lr.start_date AND lr.end_date
")->fetch_row()[0];

$totalUsers = $conn->query("SELECT COUNT(*) FROM users WHERE role<>'HR'")->fetch_row()[0];
$totalManagers = $conn->query("SELECT COUNT(*) FROM users WHERE role='Manager'")->fetch_row()[0];
$expiringLeave = $conn->query("SELECT COUNT(*) FROM users WHERE leave_balance <= 2 AND status='Active'")->fetch_row()[0];

$hrReliquat = $conn->query("SELECT leave_balance FROM users WHERE id = $hrId")->fetch_assoc()['leave_balance'];

// Low leave users
$lowLeaveUsers = [];
$result = $conn->query("SELECT first_name, last_name, leave_balance FROM users WHERE leave_balance <= 2 AND status='Active'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lowLeaveUsers[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'absentStaff' => $absentStaff,
    'totalUsers' => $totalUsers,
    'totalManagers' => $totalManagers,
    'expiringLeave' => $expiringLeave,
    'hrReliquat' => $hrReliquat,
    'lowLeaveUsers' => $lowLeaveUsers
]);
