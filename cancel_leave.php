<?php
require_once 'config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_id'])) {
    $leaveId = intval($_POST['leave_id']);
    $userId = $_SESSION['user_id'];

    // Fetch leave details including days_requested
    $stmt = $conn->prepare("
        SELECT start_date, end_date, status, days_requested
        FROM leave_requests
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $leaveId, $userId);
    $stmt->execute();
    $leave = $stmt->get_result()->fetch_assoc();

    if (!$leave) {
        $_SESSION['cancel_error'] = "Demande introuvable.";
        header("Location: leave_requests.php");
        exit;
    }

    if ($leave['status'] === 'pending') {
        // Pending → just cancel
        $stmt = $conn->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $leaveId);
        if ($stmt->execute()) {
            $_SESSION['cancel_success'] = "La demande en attente a été annulée avec succès.";
        } else {
            $_SESSION['cancel_error'] = "Impossible d'annuler la demande en attente.";
        }

    } elseif ($leave['status'] === 'approved') {
        // Approved → use days_requested to restore balance
        $daysRequested = floatval($leave['days_requested']);

        // Add back to leave_balance
        $stmt = $conn->prepare("UPDATE users SET leave_balance = leave_balance + ? WHERE id = ?");
        $stmt->bind_param("di", $daysRequested, $userId);
        $stmt->execute();

        // Cancel the leave
        $stmt = $conn->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $leaveId);
        if ($stmt->execute()) {
            $_SESSION['cancel_success'] = "La demande approuvée a été annulée. Les journées déjà consommées ont été rétablies dans le solde.";
        } else {
            $_SESSION['cancel_error'] = "Impossible d'annuler la demande approuvée.";
        }
    }

    header("Location: leave_requests.php");
    exit;
}
?>
