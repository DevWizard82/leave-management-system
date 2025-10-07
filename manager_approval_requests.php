<?php
require_once 'manager_header.php';
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



$managerId = $_SESSION['user_id'];

// ========== Handle Approve or Reject Actions ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leaveId = intval($_POST['leave_id']);
    $action = $_POST['action'];

    if (!in_array($action, ['approved', 'refused'])) {
        $_SESSION['error'] = "Action non valide.";
        header("Location: manager_approval_requests.php");
        exit();
    }

$mailDebugOutput = ''; // variable to store debug messages


    if ($action === 'approved') {
        // Start transaction
        $conn->begin_transaction();
        try {
            // Fetch leave details along with leave type
            $stmt = $conn->prepare("
                SELECT lr.user_id, lr.days_requested, start_date, end_date, lt.affecte_le_reliquat
                FROM leave_requests lr
                JOIN leave_types lt ON lr.type_id = lt.id
                WHERE lr.id = ? AND lr.manager_id = ? AND lr.status = 'pending'
            ");
            $stmt->bind_param("ii", $leaveId, $managerId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                throw new Exception("Demande introuvable ou déjà traitée.");
            }

            $row = $result->fetch_assoc();
            $employeeId = $row['user_id'];
            $daysRequested = $row['days_requested'];
            $affecte = $row['affecte_le_reliquat'];

            // Deduct from leave_balance only if affecte_le_reliquat = 1
            if ($affecte == 1) {
                $stmtUpdate = $conn->prepare("
                    UPDATE users 
                    SET leave_balance = GREATEST(leave_balance - ?, 0) 
                    WHERE id = ?
                ");
                $stmtUpdate->bind_param("ii", $daysRequested, $employeeId);
                $stmtUpdate->execute();
            }

            // Update leave request status
            $stmtStatus = $conn->prepare("
                UPDATE leave_requests 
                SET status = 'approved', reviewed_at = NOW() 
                WHERE id = ? AND manager_id = ?
            ");
            $stmtStatus->bind_param("ii", $leaveId, $managerId);
            $stmtStatus->execute();

            $conn->commit();
            
            $stmtEmail = $conn->prepare("SELECT email, first_name FROM
            users WHERE id = ?");
            $stmtEmail->bind_param("i", $employeeId);
            $stmtEmail->execute();
            $userData = $stmtEmail->get_result()->fetch_assoc();

            if ($userData && !empty($userData['email'])) {
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
                    $mail->SMTPDebug = 2; // 0 = off, 1 = client messages, 2 = client+server messages
                    $mail->Debugoutput = function($str, $level) {
                        $jsMessage = json_encode("PHPMailer debug level {$level}: {$str}");
                        echo "<script>console.log({$jsMessage});</script>";
                    };


                    $mail->setFrom($smtpEmail, 'Manager');
                    $mail->addAddress($userData['email'], $userData['first_name']);

                    if ($row['start_date'] === $row['end_date'] && $row['days_requested'] === 0.5) {
                        $leavePeriod = "Demi-journée à " . date('d/m/Y', strtotime($row['start_date']));
                    
                    } else {
                        $leavePeriod = "Du " . date('d/m/Y', strtotime($row['start_date'])) .
                        " au " . date('d/m/Y', strtotime($row['end_date'])) .
                        " — " . $row['days_requested'] . " jours";
                    }

                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Encoding = 'base64';
                    $mail->Subject = "Votre demande de congé a été approuvée";
                    $mail->Body = "Bonjour {$userData['first_name']},<br><br>
                    Votre demande de congé <strong>{$leavePeriod}</strong> a été <strong>approuvée</strong>.<br><br>
                    Cordialement,<br>Manager";

                    $mail->send();
 
                } catch(Exception $e) {
                    error_log("Email non envoyé à {$userData['email']}: {$mail->ErrorInfo}");
                } 
            }

            $_SESSION['success'] = "La demande a été approuvée avec succès" . ($affecte == 1 ? " et le reliquat mis à jour." : ".");
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Erreur lors de l'approbation : " . $e->getMessage();
        }
    }
 else {
        $stmtStatus = $conn->prepare("UPDATE leave_requests SET status = 'refused', reviewed_at = NoW() WHERE id = ? AND manager_id = ? AND status = 'pending';");
        $stmtStatus->bind_param("ii", $leaveId, $managerId);
        $stmtStatus->execute();

        $stmtEmail = $conn->prepare("SELECT email, first_name FROM users
        WHERE id = ?");
        $stmtEmail->bind_param("i", $employeeId);
        $stmtEmail->execute();
        $userData = $stmtEmail->get_result()->fetch_assoc();

        $stmt = $conn->prepare("SELECT start_date, end_date, days_requested FROM leave_requests WHERE id=?");
        $stmt->bind_param("i", $leaveId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();


        if ($userData && !empty($userData['email'])) {
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
$mail->SMTPDebug = 2; // 0 = off, 1 = client messages, 2 = client+server messages
$mail->Debugoutput = function($str, $level) use (&$mailDebugOutput) {
    $mailDebugOutput .= "Debug level {$level}: {$str}<br>";
};

                $mail->setFrom($smtpEmail, 'Manager');
                $mail->addAddress($userData['email'], $userData['first_name']);

                if ($row['start_date'] === $row['end_date'] && $row['days_requested'] == 0.5) {
                    $leavePeriod = "Demi-journée à " . date('d/m/Y', strtotime($row['start_date']));
            } else {
                $leavePeriod = "Du " . date('d/m/Y', strtotime($row['start_date'])) .
                               " au " . date('d/m/Y', strtotime($row['end_date'])) .
                               " — " . $row['days_requested'] . " jours";
            }

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->Subject = "Votre demande de congé a été refusée";
            $mail->Body = "Bonjour {$userData['first_name']},<br><br>
                           Votre demande de congé <strong>{$leavePeriod}</strong> a été <strong>refusée</strong>.<br><br>
                           Cordialement,<br>Manager";

            $mail->send();

            } catch(Exception $e) {
                error_log("Email non envoyé à {$userData['email']}: {$mail->ErrorInfo}");
            }
        }
        

        $_SESSION['success'] = "La demande a été refusée avec succès.";
    }


}

// Fetch leave types for filter
$typesResult = $conn->query("SELECT id, type_name FROM leave_types ORDER BY type_name ASC");
$leaveTypes = [];
while ($row = $typesResult->fetch_assoc()) {
    $leaveTypes[] = $row;
}
?>

<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<div class="page-content p-8">

    <div class="mb-8">
        <h2 class="text-3xl font-bold text-gray-800 mb-2">Demandes en attente d'approbation</h2>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center justify-center gap-4 mb-6 bg-gray-50 p-4 rounded-lg shadow-sm">
        <div>
            <label class="block text-sm font-medium text-gray-700">Type de congé</label>
            <select id="typeFilter" class="mt-1 border rounded px-3 py-2">
                <option value="">Tous</option>
                <?php foreach($leaveTypes as $type): ?>
                    <option value="<?= $type['id']; ?>"><?= htmlspecialchars($type['type_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Période</label>
            <div class="flex gap-2 mt-1">
                <input type="date" id="startDateFilter" class="border rounded px-2 py-1">
                <span class="self-center">→</span>
                <input type="date" id="endDateFilter" class="border rounded px-2 py-1">
            </div>
        </div>

        <div class="mt-6">
            <button id="resetFilters" class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300 transition">
                Effacer les filtres
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full border-collapse border border-gray-200">
            <thead class="bg-gray-100">
                <?php
                $columns = [
                    'first_name' => 'Employé',
                    'type_name' => 'Type de Congé',
                    'start_date' => 'Date Début',
                    'end_date' => 'Date Fin',
                    'days_requested' => 'Jours',
                    'created_at' => 'Soumis le'
                ];
                ?>
                <tr>
                    <?php foreach($columns as $key => $label): ?>
                        <th class="px-4 py-2 border-b text-left cursor-pointer" data-sort="<?= $key ?>">
                            <?= $label ?>
                            <span class="sort-arrows text-gray-400 ml-1">▲▼</span>
                        </th>
                    <?php endforeach; ?>
                    <th class="px-4 py-2 border-b">Actions</th>
                </tr>
            </thead>
            <tbody id="requests-tbody">
                <tr>
                    <td colspan="7" class="px-4 py-4 text-center text-gray-400">
                        Chargement des demandes...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const tbody = document.getElementById("requests-tbody");
    const typeFilter = document.getElementById("typeFilter");
    const startDateFilter = document.getElementById("startDateFilter");
    const endDateFilter = document.getElementById("endDateFilter");
    const resetFilters = document.getElementById("resetFilters");
    let sortColumn = '';
    let sortDirection = 'ASC';

    async function fetchRequests() {
        try {
            const params = new URLSearchParams({
                type: typeFilter.value,
                start: startDateFilter.value,
                end: endDateFilter.value,
                sort: sortColumn,
                direction: sortDirection
            });
            const response = await fetch("fetch_manager_requests.php?" + params.toString());
            const data = await response.text();
            tbody.innerHTML = data;
        } catch (error) {
            console.error("Erreur de récupération :", error);
            tbody.innerHTML = `<tr>
                <td colspan="7" class="px-4 py-4 text-center text-red-500">
                    Erreur lors du chargement des données.
                </td>
            </tr>`;
        }
    }

    // Fetch on page load
    fetchRequests();
    setInterval(fetchRequests, 5000);

    // Filters
    [typeFilter, startDateFilter, endDateFilter].forEach(el => el.addEventListener("change", fetchRequests));
    resetFilters.addEventListener("click", () => {
        typeFilter.value = "";
        startDateFilter.value = "";
        endDateFilter.value = "";
        fetchRequests();
    });

    // Sortable headers
    document.querySelectorAll('th[data-sort]').forEach(header => {
        header.addEventListener('click', () => {
            const column = header.getAttribute('data-sort');
            if (sortColumn === column) {
                sortDirection = sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                sortColumn = column;
                sortDirection = 'ASC';
            }
            fetchRequests();
        });
    });

    // SweetAlert notifications
    <?php if(isset($_SESSION['success'])): ?>
        Swal.fire({
            toast: true,
            position: 'top',
            icon: 'success',
            title: "<?= addslashes($_SESSION['success']); ?>",
            showConfirmButton: true,
            timer: 3000,
            timerProgressBar: true,
            background: '#d4edda',
            color: '#155724',
            customClass: { popup: 'center-top-toast' }
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
        Swal.fire({
            toast: true,
            position: 'top',
            icon: 'error',
            title: "<?= addslashes($_SESSION['error']); ?>",
            showConfirmButton: true,
            timer: 3000,
            timerProgressBar: true,
            background: '#f8d7da',
            color: '#721c24',
            customClass: { popup: 'center-top-toast' }
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script>
<script src="./common.js?v=<?= time(); ?>"></script>
