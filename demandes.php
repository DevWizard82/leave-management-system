<?php
ob_start();
require_once 'employee_header.php';

require_once 'config.php';

require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$smtpEmail = $_ENV['SMTP_EMAIL'];
$smtpPassword = $_ENV['SMTP_PASSWORD'];
$smtpPort = $_ENV['SMTP_PORT'];
$smtpHost = $_ENV['SMTP_HOST'];


if (isset($_POST['submit_leave_request'])) {
    $userId = $_SESSION['user_id'];
    $leaveTypeId = intval($_POST['leave_type']);
    $dateRange = trim($_POST['leave_period']);
    $halfDay = floatval($_POST['half_day']); // 0 or 0.5

    // --- Sanitize input ---
    $dateRange = preg_replace('/\s+to\s+/', ' to ', $dateRange);
    $dates = explode(' to ', $dateRange);

    if (count($dates) !== 2) {
        $_SESSION['leave_error'] = "Veuillez sélectionner une période valide.";
        header("Location: employee_dashboard.php");
        exit();
    }

    $startDate = trim($dates[0]);
    $endDate = trim($dates[1]);

    // --- Validate date format ---
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || 
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $_SESSION['leave_error'] = "Format de date invalide.";
        header("Location: employee_dashboard.php");
        exit();
    }

    if (strtotime($startDate) > strtotime($endDate)) {
        $_SESSION['leave_error'] = "La date de début doit être avant la date de fin.";
        header("Location: employee_dashboard.php");
        exit();
    }


    // Fetch holidays that overlap with leave period
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
        for ($d = $hStart; $d <= $hEnd; $d += 86400) { // loop through each holiday day
            $holidayDates[date('Y-m-d', $d)] = true;
        }
    }

    $daysRequested = 0;
    for ($current = strtotime($startDate); $current <= strtotime($endDate); $current += 86400) {
        $dayOfWeek = date('N', $current); // 1 = Monday, 7 = Sunday
        $currentDate = date('Y-m-d', $current);

        // Skip weekends (Saturday=6, Sunday=7) and holidays
        if ($dayOfWeek < 6 && !isset($holidayDates[$currentDate])) {
            $daysRequested += 1;
        }
    }

    // Handle half-day if leave is only one day
    if ($startDate === $endDate && $halfDay > 0) {
        $daysRequested = $halfDay; // replace full day with half-day
    }


    // --- Check leave balance ---
    $stmtBalance = $conn->prepare("SELECT leave_balance FROM users WHERE id = ?");
    $stmtBalance->bind_param("i", $userId);
    $stmtBalance->execute();
    $resultBalance = $stmtBalance->get_result();
    $balance = $resultBalance->fetch_assoc()['leave_balance'] ?? 0;

    if ($daysRequested > $balance) {
        $_SESSION['leave_error'] = "Vous n'avez pas assez de jours de congé.";
        header("Location: employee_dashboard.php");
        exit();
    }

    // --- Check for overlapping leave requests ---
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
        header("Location: employee_dashboard.php");
        exit();
    }

    // --- Fetch manager_id and hr_id ---
    $stmtUser = $conn->prepare("SELECT manager_id, hr_id FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $userData = $stmtUser->get_result()->fetch_assoc();
    $managerId = $userData['manager_id'] ?? null;
    $hrId = $userData['hr_id'] ?? null;

    // --- Insert leave request ---
    $stmtInsert = $conn->prepare("
        INSERT INTO leave_requests
        (user_id, manager_id, hr_id, start_date, end_date, status, type_id, days_requested)
        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
    ");
    $stmtInsert->bind_param("iiissid", $userId, $managerId, $hrId, $startDate, $endDate, $leaveTypeId, $daysRequested);

    // --- Get the manager_id of the user ---
    $stmtmanagermail = $conn->prepare("
        SELECT manager_id FROM users WHERE id = ?;
    ");
    $stmtmanagermail->bind_param("i", $userId);
    $stmtmanagermail->execute();
    $stmtmanagermail->bind_result($managerId);
    $stmtmanagermail->fetch();
    $stmtmanagermail->close();

    // --- Get the manager's email ---
    if ($managerId) { // Make sure user has a manager assigned
        $stmtmanageremail = $conn->prepare("
            SELECT email, first_name, last_name FROM users WHERE id = ?;
        ");
        $stmtmanageremail->bind_param("i", $managerId);
        $stmtmanageremail->execute();
        $stmtmanageremail->bind_result($managerEmail, $managerFirstName, $managerLastName);
        $stmtmanageremail->fetch();
        $stmtmanageremail->close();

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->SMTPAuth = true;
            $mail->Host = $smtpHost;
            $mail->Username = $smtpEmail;
            $mail->Password = $smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)$smtpPort;

            // Debugging
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                $jsMessage = json_encode("PHPMailer debug level {$level}: {$str}");
                echo "<script>console.log({$jsMessage});</script>";
            };

            $mail->setFrom($smtpEmail, 'RH');
            $mail->addAddress($managerEmail, $managerFirstName . ' ' . $managerLastName);

            // Format leave period
            if ($daysRequested === 0.5 && $startDate === $endDate) {
                $leavePeriod = "Demi-journée le " . date('d/m/Y', strtotime($startDate));
            } else {
                $leavePeriod = "Du " . date('d/m/Y', strtotime($startDate)) .
                    " au " . date('d/m/Y', strtotime($endDate)) .
                    " — " . $daysRequested . " jours";
            }

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->Subject = "Nouvelle demande de congé";
            $mail->Body = "Bonjour {$managerFirstName},<br><br>
                L'employé <strong>{$userData['first_name']} {$userData['last_name']}</strong> a soumis une nouvelle demande de congé pour la période suivante :<br>
                <strong>{$leavePeriod}</strong>.<br><br>
                Veuillez examiner et approuver la demande dans le système.<br><br>
                Cordialement,<br>RH";

            $mail->send();

        } catch(Exception $e) {
            error_log("Email non envoyé à {$managerEmail}: {$mail->ErrorInfo}");
        } 
    }




    if ($stmtInsert->execute()) {
        $_SESSION['leave_success'] = "Demande de congé soumise avec succès.";
    } else {
        $_SESSION['leave_error'] = "Erreur lors de la soumission: " . $stmtInsert->error;
    }

    header("Location: employee_dashboard.php");
    exit();
}
?>
