<?php
ob_start();
require_once 'hr_header.php';
require_once 'config.php';

if (isset($_POST['submit_leave_request'])) {
    $userId = $_SESSION['user_id']; // HR is requesting leave for self
    $leaveTypeId = intval($_POST['leave_type']);
    $dateRange = trim($_POST['leave_period']);
    $halfDay = floatval($_POST['half_day']); // 0 or 0.5

    // --- Sanitize input ---
    $dateRange = preg_replace('/\s+to\s+/', ' to ', $dateRange);
    $dates = explode(' to ', $dateRange);

    if (count($dates) !== 2) {
        $_SESSION['leave_error'] = "Veuillez sélectionner une période valide.";
        header("Location: hr_dashboard.php");
        exit();
    }

    $startDate = trim($dates[0]);
    $endDate = trim($dates[1]);

    // --- Validate date format ---
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || 
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $_SESSION['leave_error'] = "Format de date invalide.";
        header("Location: hr_dashboard.php");
        exit();
    }

    if (strtotime($startDate) > strtotime($endDate)) {
        $_SESSION['leave_error'] = "La date de début doit être avant la date de fin.";
        header("Location: hr_dashboard.php");
        exit();
    }

    // --- Fetch holidays ---
    $stmtHolidays = $conn->prepare("
        SELECT holiday_start, holiday_end 
        FROM holidays 
        WHERE holiday_end >= ? AND holiday_start <= ?
    ");
    $stmtHolidays->bind_param("ss", $startDate, $endDate);
    $stmtHolidays->execute();
    $resultHolidays = $stmtHolidays->get_result();

    $holidayDates = [];
    while ($row = $resultHolidays->fetch_assoc()) {
        $hStart = strtotime($row['holiday_start']);
        $hEnd = strtotime($row['holiday_end']);
        for ($d = $hStart; $d <= $hEnd; $d += 86400) {
            $holidayDates[date('Y-m-d', $d)] = true;
        }
    }

    // --- Calculate leave days ---
    $daysRequested = 0;
    for ($current = strtotime($startDate); $current <= strtotime($endDate); $current += 86400) {
        $dayOfWeek = date('N', $current);
        $currentDate = date('Y-m-d', $current);

        // Skip weekends and holidays
        if ($dayOfWeek < 6 && !isset($holidayDates[$currentDate])) {
            $daysRequested += 1;
        }
    }

    // Handle half-day for single-day leave
    if ($startDate === $endDate && $halfDay > 0) {
        $daysRequested = $halfDay;
    }

    // --- Check leave balance ---
    $stmtBalance = $conn->prepare("SELECT leave_balance FROM users WHERE id = ?");
    $stmtBalance->bind_param("i", $userId);
    $stmtBalance->execute();
    $resultBalance = $stmtBalance->get_result();
    $balance = $resultBalance->fetch_assoc()['leave_balance'] ?? 0;

    if ($daysRequested > $balance) {
        $_SESSION['leave_error'] = "Vous n'avez pas assez de jours de congé.";
        header("Location: hr_dashboard.php");
        exit();
    }

    // --- Check overlapping leave requests ---
    $stmtOverlap = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM leave_requests
        WHERE user_id = ?
          AND status IN ('pending','approved')
          AND NOT (end_date < ? OR start_date > ?)
    ");
    $stmtOverlap->bind_param("iss", $userId, $startDate, $endDate);
    $stmtOverlap->execute();
    $resultOverlap = $stmtOverlap->get_result();
    $row = $resultOverlap->fetch_assoc();

    if ($row['count'] > 0) {
        $_SESSION['leave_error'] = "Vous avez déjà une demande de congé qui chevauche cette période.";
        header("Location: hr_dashboard.php");
        exit();
    }

    // --- Fetch HR id ---
    $stmtUser = $conn->prepare("SELECT hr_id FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $userData = $stmtUser->get_result()->fetch_assoc();
    $hrId = $userData['hr_id'] ?? null;

    // --- Insert leave request (approved automatically) ---
    $stmtInsert = $conn->prepare("
        INSERT INTO leave_requests
        (user_id, manager_id, hr_id, start_date, end_date, status, type_id, days_requested)
        VALUES (?, ?, ?, ?, ?, 'approved', ?, ?)
    ");
    $stmtInsert->bind_param("iiissid", $userId, $userId, $hrId, $startDate, $endDate, $leaveTypeId, $daysRequested);

    if ($stmtInsert->execute()) {
        $stmtDeduct = $conn->prepare("UPDATE users SET leave_balance = leave_balance - ? WHERE id = ?;");
        $stmtDeduct->bind_param("di", $daysRequested, $userId);
        $stmtDeduct->execute();
        $_SESSION['leave_success'] = "Votre congé a été approuvé automatiquement.";
    } else {
        $_SESSION['leave_error'] = "Erreur lors de la soumission: " . $stmtInsert->error;
    }

    header("Location: hr_dashboard.php");
    exit();
}
?>
