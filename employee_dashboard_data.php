<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$employeeId = $_SESSION['user_id'];

$employee = $conn->query("SELECT leave_balance FROM users WHERE id = $employeeId")->fetch_assoc();
$leaveBalance = $employee['leave_balance'];

$pendingRequests = $conn->query("SELECT COUNT(*) FROM leave_requests WHERE user_id = $employeeId AND status='pending'")->fetch_row()[0];
$approvedRequests = $conn->query("SELECT COUNT(*) FROM leave_requests WHERE user_id = $employeeId AND status='approved'")->fetch_row()[0];
$rejectedRequests = $conn->query("SELECT COUNT(*) FROM leave_requests WHERE user_id = $employeeId AND status='rejected'")->fetch_row()[0];

echo json_encode([
    'leaveBalance' => $leaveBalance,
    'pendingRequests' => $pendingRequests,
    'approvedRequests' => $approvedRequests,
    'rejectedRequests' => $rejectedRequests
]);
