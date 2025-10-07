<?php
// ============================
// update_leave.php
// Automatically add 18 days for users with today's join day/month
// ============================

require_once 'config.php'; // your DB connection

$today = new DateTime();
$todayDay = $today->format('d');
$todayMonth = $today->format('m');

// Prepare the SQL query
$stmt = $conn->prepare("
    UPDATE users 
    SET leave_balance = leave_balance + 18
    WHERE DAY(created_at) = ? AND MONTH(created_at) = ?
");

// Bind parameters (both integers)
$stmt->bind_param("ii", $todayDay, $todayMonth);

if ($stmt->execute()) {
    echo "Leave balances updated successfully for users matching today's date.\n";
} else {
    echo "Error updating leave balances: " . $conn->error . "\n";
}

$stmt->close();
$conn->close();
