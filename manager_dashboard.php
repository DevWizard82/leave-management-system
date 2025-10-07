<?php
require_once 'manager_header.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Manager') {
    header("Location: index.php");
    exit();
}

require_once 'config.php';
$employeeId = $_SESSION['user_id'];

// Employee-specific data
$stmt = $conn->prepare("SELECT leave_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$stmt->bind_result($leaveBalance);
$stmt->fetch();
$stmt->close();

// Team members count and list
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE manager_id = ? ORDER BY first_name ASC");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$result = $stmt->get_result();
$teamMembersList = $result->fetch_all(MYSQLI_ASSOC);
$teamMembers = count($teamMembersList);
$stmt->close();

// Approved requests count
$stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND status='approved'");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$stmt->bind_result($approvedRequests);
$stmt->fetch();
$stmt->close();

// Rejected requests count
$stmt = $conn->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id = ? AND status='rejected'");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$stmt->bind_result($rejectedRequests);
$stmt->fetch();
$stmt->close();


$monthStart = date('Y-m-01');  // first day of this month
$monthEnd   = date('Y-m-t');   // last day of this month

$upcomingHolidays = [];
$sql = "SELECT holiday_name, holiday_start 
        FROM holidays 
        WHERE (holiday_start BETWEEN '$monthStart' AND '$monthEnd')
        ORDER BY holiday_start ASC";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $upcomingHolidays[] = $row;
    }
}

$monthNames = [
    1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
];

$currentMonth = (int)date('n');
$currentYear = date('Y');
$monthLabel = $monthNames[$currentMonth] . ' ' . $currentYear;
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Accueil</title>

        <style>

            .stat-card {
                position: relative; /* for tooltip positioning */
            }

        /* Hidden by default */
        .stat-card .tooltip-text {
            visibility: hidden;
            opacity: 0;
            width: max-content;
            max-width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            padding: 5px 8px;
            border-radius: 4px;
            position: absolute;
            top: -60px; /* above the card */
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.8rem;
            transition: opacity 0.2s;
            z-index: 10;
        }

        /* Small arrow */
        .stat-card .tooltip-text::after {
            content: "";
            position: absolute;
            bottom: -8px; /* arrow points downwards */
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }

        /* Show tooltip on hover */
        .stat-card:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }


            .stat-card {
                border-radius: 1rem;
                padding: 1.5rem;
                color: #fff;
                box-shadow: 0 10px 20px rgba(0,0,0,0.1);
                transition: transform 0.2s, box-shadow 0.2s;
            }

            
            .stat-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 15px 25px rgba(0,0,0,0.2);
            }

            .text-custom {
                font-size: 17px;
            }
            .btn-add-leave {
                background-color: #e72b33; /* your brand red */
                color: #fff;
                border: none;
                padding: 0.5em 1em;
                border-radius: 5px;
                cursor: pointer;
                transition: background-color 0.3s;
            }

            .btn-add-leave:hover {
                background-color: #f03c46; /* lighter hover red */
            }

            .flip-card {
            perspective: 1000px;
            width: 260px;
            height: 180px;
            }

            .holidays {
                width: 570px;
                height: 470px;
            }

            .flip-card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform 0.8s;
            transform-style: preserve-3d;
            cursor: pointer;
            }

            .flip-card.flipped .flip-card-inner {
            transform: rotateY(180deg);
            }

            .flip-card-front,
            .flip-card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: 0.5rem;
            }

            .flip-card-back {
            transform: rotateY(180deg);
            }
        </style>

        <!-- Litepicker CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">

        <!-- Litepicker JS -->
        <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/bundle.js"></script>
        <!-- CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
        <!-- JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    </head>
    <body>
        <div id="employee-dashboard" class="page-content p-4 md:p-8">
            <div class="flex justify-end mb-4">
                <button onclick="toggleLeaveModal()" 
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-medium flex items-center gap-2 transition duration-350">
                    <i class="fa-solid fa-calendar-plus"></i>
                    Demander un congé
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
                <!-- Reliquat -->
                <div class="stat-card cursor-pointer bg-gradient-to-r from-blue-400 to-blue-600 text-white shadow-lg p-6 rounded-lg transform transition hover:-translate-y-1 hover:shadow-2xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-lg font-medium opacity-90">Reliquat</p>
                            <p class="text-3xl font-bold" id="leaveBalance"><?= htmlspecialchars($leaveBalance); ?></p>
                            <p class="text-sm opacity-80" id="leaveText"><?= $leaveBalance === 1 ? "jour disponible" : "jours disponibles"; ?></p>
                        </div>
                        <div class="p-3 bg-white bg-opacity-25 rounded-full">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Membres de l'équipe  -->
                <div onclick="toggleTeamModal()" class="cursor-pointer stat-card bg-gradient-to-r from-gray-400 to-slate-600 text-white shadow-lg p-6 rounded-lg transform transition hover:-translate-y-1 hover:shadow-2xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-lg font-medium opacity-90">Membres de l'équipe</p>
                            <p class="text-3xl font-bold" id="teamMembers"><?= $teamMembers; ?></p>
                            <p class="text-sm opacity-80">Actuellement actifs</p>
                        </div>
                        <div class="p-3 bg-white bg-opacity-25 rounded-full">
                            <img src="https://img.icons8.com/ios-filled/50/ffffff/user-group-man-man.png" 
                                alt="Team Icon" class="w-6 h-6">
                        </div>
                    </div>
                     <!-- Tooltip span -->
                    <span class="tooltip-text">Voir tous les membres de votre équipe</span>
                </div>


                <!-- Approved requests -->
                <div class="cursor-pointer stat-card bg-gradient-to-r from-green-500 to-teal-500 text-white shadow-lg p-6 rounded-lg transform transition hover:-translate-y-1 hover:shadow-2xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium opacity-90 text-lg">Demandes approuvées</p>
                            <p class="text-3xl font-bold" id="approvedRequests"><?= $approvedRequests; ?></p>
                            <p class="text-sm opacity-80">Cette année</p>
                        </div>
                        <div class="p-3 bg-white bg-opacity-25 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Rejected requests -->
                <div class="cursor-pointer stat-card bg-gradient-to-r from-red-400 to-red-600 text-white shadow-lg p-6 rounded-lg transform transition hover:-translate-y-1 hover:shadow-2xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium opacity-90 text-lg">Demandes refusées</p>
                            <p class="text-3xl font-bold" id="rejectedRequests"><?= $rejectedRequests; ?></p>
                            <p class="text-sm opacity-80">Cette année</p>
                        </div>
                        <div class="p-3 bg-white bg-opacity-25 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12"/>
                            </svg>
                        </div>
                    </div>
                </div>









        </div>
    </div>
    <div id="leaveRequestModal" 
        class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 transition-opacity opacity-0 duration-300">
        
        <div class="bg-white rounded-lg shadow-lg w-96 p-6 relative">
            <!-- Close button -->
            <button onclick="toggleLeaveModal()" 
                    class="absolute top-3 right-3 text-gray-500 hover:text-gray-700">
                ✖
            </button>

            <h2 class="text-xl font-bold mb-4 text-gray-800">Nouvelle demande de congé</h2>

            <form id="leaveRequestForm" method="POST" action="./manager_demandes.php">
                <!-- Leave Type -->
                <div class="mb-4">
                    <label for="leave_type" class="block text-sm font-medium text-gray-700 mb-1">Type de congé</label>
                    <select id="leave_type" name="leave_type" 
                        class="block w-full rounded-lg border border-gray-300 bg-white py-2 px-3 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-700">
                        <?php
                        $resLeaveTypes = $conn->query("SELECT id, type_name FROM leave_types ORDER BY type_name ASC");
                        while ($type = $resLeaveTypes->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($type['id']) . '">' . htmlspecialchars($type['type_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>


                <!-- Date -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700" for="leave_period">Période</label>
                    <input type="text" id="leave_period" name="leave_period" 
                        class="block w-full rounded-lg border border-gray-300 bg-white py-2 px-3 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-700">
                </div>

                <!-- Half Day Option -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700" for="half_day">Demi-journée (si même jour)</label>
                    <select id="half_day" name="half_day"
                        class="block w-full rounded-lg border border-gray-300 bg-white py-2 px-3 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-700">
                        <option value="0">Non</option>
                        <option value="0.5">Oui</option>
                    </select>
                </div>

                <!-- Submit -->
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="toggleLeaveModal()" 
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        Annuler
                    </button>
                    <button name="submit_leave_request" type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Soumettre
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- Team Members Modal -->
    <div id="teamModal" class="opacity-0 fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-lg w-full overflow-y-auto max-h-[80vh] relative">
            <span onclick="toggleTeamModal()" class="absolute top-2 right-4 cursor-pointer text-xl">&times;</span>
            <h2 class="text-xl font-bold mb-4">Membres de l'équipe</h2>
            <ul class="list-disc list-inside space-y-2">
                <?php foreach ($teamMembersList as $member): ?>
                    <li><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <script>
            
        document.addEventListener("DOMContentLoaded", function () {
            const input = document.getElementById('leave_period');

            const picker = new Litepicker({
                element: input,
                singleMode: false,
                autoApply: true,
                minDate: new Date(new Date().setHours(0, 0, 0, 0)),
                lang: 'fr',
                format: 'YYYY-MM-DD',
                delimiter: ' to ',
                setup: (picker) => {
                    picker.on('selected', (start, end) => {
                        if (start && end) {
                            // ALWAYS override the input with exactly one " to "
                            input.value = start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD');
                            console.log("DEBUG cleaned value:", input.value);
                        }
                    });
                }
            });

            input.readOnly = true; // prevent user from typing broken format

            // Extra safeguard: clean before submit
            const form = input.closest('form');
            if (form) {
                form.addEventListener('submit', () => {
                    let parts = input.value.split(' to ');
                    if (parts.length !== 2) {
                        // fallback: use first and last parts
                        parts = [parts[0], parts[parts.length - 1]];
                    }
                    input.value = parts[0].trim() + ' to ' + parts[1].trim();
                    console.log("DEBUG sanitized before submit:", input.value);
                });
            }
        });

        function toggleTeamModal() {
            const modal = document.getElementById('teamModal');
            if (!modal) return; // Safety check

            if (modal.classList.contains('hidden')) {
                // Show the modal
                modal.classList.remove('hidden', 'opacity-0');
                modal.classList.add('flex', 'opacity-100');
            } else {
                // Hide the modal
                modal.classList.remove('flex', 'opacity-100');
                modal.classList.add('hidden', 'opacity-0');
            }
        }

        function toggleLeaveModal() {
            const modal = document.getElementById('leaveRequestModal');
            
            if (!modal) return; // Safety check

            if (modal.classList.contains('hidden')) {
                // Show the modal
                modal.classList.remove('hidden', 'opacity-0');
                modal.classList.add('flex', 'opacity-100');
            } else {
                // Hide the modal
                modal.classList.remove('flex', 'opacity-100');
                modal.classList.add('hidden', 'opacity-0');
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
        <?php if (isset($_SESSION['leave_success'])): ?>
            Swal.fire({
                toast: true,
                position: 'top',
                icon: 'success',
                title: "<?= addslashes($_SESSION['leave_success']); ?>",
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                background: '#d4edda',
                color: '#155724',
                customClass: {
                    popup: 'center-top-toast'
                }
            });
            <?php unset($_SESSION['leave_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['leave_error'])): ?>
            Swal.fire({
                toast: true,
                position: 'top',
                icon: 'error',
                title: "<?= addslashes($_SESSION['leave_error']); ?>",
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                background: '#f8d7da',
                color: '#721c24',
                customClass: {
                    popup: 'center-top-toast'
                }
            });
            <?php unset($_SESSION['leave_error']); ?>
        <?php endif; ?>
        });

    </script>

        <script src="common.js?v=<?= time(); ?>"></script>
    </body>
</html>
