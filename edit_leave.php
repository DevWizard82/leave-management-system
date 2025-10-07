<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// ---------------------------
// Vérification de la session
// ---------------------------
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vous devez être connecté.']);
    exit();
}

$employeeId = intval($_SESSION['user_id']);

// ---------------------------
// Récupération des données envoyées
// ---------------------------
$leaveId     = intval($_POST['leave_id'] ?? 0);
$leaveType   = intval($_POST['leave_type'] ?? 0);
$leavePeriod = trim($_POST['leave_period'] ?? '');
$halfDay     = isset($_POST['half_day']) ? floatval($_POST['half_day']) : 0; // Can be 0 or 0.5

if ($leaveId <= 0 || $leaveType <= 0 || empty($leavePeriod)) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
    exit();
}

// ---------------------------
// Analyse et validation de la période
// ---------------------------
$dates = explode(' to ', $leavePeriod);
if (count($dates) !== 2) {
    echo json_encode(['success' => false, 'message' => 'Format de période invalide.']);
    exit();
}

$startDate = trim($dates[0]);
$endDate   = trim($dates[1]);

// Validation format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    echo json_encode(['success' => false, 'message' => 'Format de date invalide.']);
    exit();
}

// La date de début doit être <= date de fin
if (strtotime($startDate) > strtotime($endDate)) {
    echo json_encode(['success' => false, 'message' => 'La date de début doit être avant la date de fin.']);
    exit();
}

// ---------------------------
// Vérification que la demande existe et est modifiable
// ---------------------------
$stmt = $conn->prepare("
    SELECT days_requested
    FROM leave_requests
    WHERE id = ?
      AND user_id = ?
      AND (status = 'pending' OR (status = 'approved' AND end_date >= CURDATE()))
");
$stmt->bind_param("ii", $leaveId, $employeeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Impossible de modifier cette demande.']);
    exit();
}

$oldLeave = $result->fetch_assoc();
$oldDays = floatval($oldLeave['days_requested']);

// ---------------------------
// Récupération des jours fériés
// ---------------------------
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
    $hEnd   = strtotime($row['holiday_end']);
    for ($d = $hStart; $d <= $hEnd; $d += 86400) {
        $holidayDates[date('Y-m-d', $d)] = true;
    }
}

// ---------------------------
// Calcul du nombre de jours demandés
// ---------------------------
$daysRequested = 0;

// Boucle pour compter les jours ouvrables hors week-ends et jours fériés
for ($current = strtotime($startDate); $current <= strtotime($endDate); $current += 86400) {
    $dayOfWeek = date('N', $current); // 1 = Lundi, 7 = Dimanche
    $currentDate = date('Y-m-d', $current);

    if ($dayOfWeek < 6 && !isset($holidayDates[$currentDate])) {
        $daysRequested++;
    }
}

// Gestion d'un demi-jour uniquement si la période est une seule journée
if ($startDate === $endDate) {
    $daysRequested = ($halfDay > 0) ? 0.5 : 1.0;
}

// Empêcher un retour à 0 accidentel
if ($daysRequested <= 0) {
    echo json_encode(['success' => false, 'message' => 'Le nombre de jours demandés est invalide.']);
    exit();
}

// ---------------------------
// Récupérer le solde actuel et réajuster
// ---------------------------
$stmtBalance = $conn->prepare("SELECT leave_balance FROM users WHERE id = ?");
$stmtBalance->bind_param("i", $employeeId);
$stmtBalance->execute();
$resultBalance = $stmtBalance->get_result();
$balance = floatval($resultBalance->fetch_assoc()['leave_balance'] ?? 0);

// Restaurer le solde avant d'appliquer la nouvelle demande
$adjustedBalance = $balance + $oldDays;

// Vérifier que le solde est suffisant
if ($daysRequested > $adjustedBalance) {
    echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas assez de jours de congé.']);
    exit();
}

// ---------------------------
// Vérifier chevauchement avec d'autres demandes
// ---------------------------
$stmtOverlap = $conn->prepare("
    SELECT COUNT(*) AS count 
    FROM leave_requests 
    WHERE user_id = ? 
      AND id <> ? 
      AND status IN ('pending','approved') 
      AND NOT (end_date < ? OR start_date > ?)
");
$stmtOverlap->bind_param("iiss", $employeeId, $leaveId, $startDate, $endDate);
$stmtOverlap->execute();
$rowOverlap = $stmtOverlap->get_result()->fetch_assoc();

if ($rowOverlap['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Vous avez déjà une demande de congé qui chevauche cette période.']);
    exit();
}

// ---------------------------
// Mise à jour en transaction
// ---------------------------
$conn->begin_transaction();

try {
    // Mise à jour de la demande
    $update = $conn->prepare("
        UPDATE leave_requests 
        SET type_id = ?, start_date = ?, end_date = ?, days_requested = ?
        WHERE id = ? AND user_id = ?
    ");
    $update->bind_param("issdii", $leaveType, $startDate, $endDate, $daysRequested, $leaveId, $employeeId);
    $update->execute();

    // Mise à jour du solde de congés
    $newBalance = $adjustedBalance - $daysRequested;
    $stmtUpdateBalance = $conn->prepare("UPDATE users SET leave_balance = ? WHERE id = ?");
    $stmtUpdateBalance->bind_param("di", $newBalance, $employeeId);
    $stmtUpdateBalance->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'La demande a été modifiée avec succès.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification : ' . $e->getMessage()]);
}
?>
