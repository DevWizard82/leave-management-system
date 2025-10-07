<?php
require_once 'hr_header.php';

// Example queries
$today = date('Y-m-d');

// Total users excluding HR
$totalUsers = $conn->query("SELECT COUNT(*) FROM users WHERE role<>'HR'")->fetch_row()[0];

// Total managers
$totalManagers = $conn->query("SELECT COUNT(*) FROM users WHERE role='Manager'")->fetch_row()[0];

// Expiring leave <= 2
$expiringleave = $conn->query("SELECT COUNT(*) FROM users WHERE leave_balance <= 2 AND status='Active';")->fetch_row()[0];

// Absent staff today
$absentstaff = $conn->query("
    SELECT COUNT(*) 
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    WHERE lr.status = 'approved' AND '$today' BETWEEN lr.start_date AND lr.end_date
")->fetch_row()[0];

// HR reliquat (for logged-in HR)
$hrId = $_SESSION['user_id'] ?? 0; // make sure session is started
$hrReliquat = 0;
if ($hrId) {
    $hrReliquat = $conn->query("SELECT leave_balance FROM users WHERE id = $hrId")->fetch_assoc()['leave_balance'] ?? 0;
}

// Low leave users
$lowLeaveUsers = [];
$result = $conn->query("SELECT first_name, last_name, leave_balance FROM users WHERE leave_balance <= 2 AND status='Active'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lowLeaveUsers[] = $row;
    }
}
$totalLowLeave = count($lowLeaveUsers);

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
    <!-- Litepicker CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css">
    <script src="https://cdn.jsdelivr.net/npm/litepicker/dist/bundle.js"></script>
    
    <!-- SweetAlert -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
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
<div id="dashboard-page" class="page-content p-4 md:p-8">
        <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2" style="margin-top: -60px;">Aperçu du tableau de bord</h2>
    <div class="flex justify-end mb-4">
        <button onclick="toggleLeaveModal()" 
            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-medium flex items-center gap-2 transition duration-350 mr-[50px]">
            <i class="fa-solid fa-calendar-plus"></i>
            Demander un congé
        </button>
    </div>
    <div class="flex flex-wrap gap-4 md:gap-6 mb-6">

        <!-- carte 1 -->
        <div class="flip-card">
            <div class="flip-card-inner">

                <!-- FRONT SIDE -->
                <div class="flip-card-front">
                    <div class="stat-card bg-gradient-to-r from-blue-400 to-blue-600 text-white shadow-lg p-6 rounded-lg transform transition hover:-translate-y-1 hover:shadow-2xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-custom font-medium text-white">Total des utilisateurs</p>
                                <p class="text-3xl font-bold text-white" id="total-users">
                                    <?= htmlspecialchars($totalUsers); ?>
                                </p>
                                <p class="text-sm text-white">Employés actifs</p>
                            </div>
                            <div class="p-3 bg-white bg-opacity-25 rounded-full">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BACK SIDE -->
                <div class="flip-card-back">
                    <div class="stat-card bg-gradient-to-r from-blue-200 to-blue-400 text-gray-900 shadow-lg p-6 rounded-lg transform transition hover:-translate-y-1 hover:shadow-2xl flex flex-col justify-center items-center">
                        <p class="text-gray-900 text-lg mb-4 text-center">Voir plus de détails</p>
                        <button class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition text-center" onclick="window.location.href='hr_users.php'">
                            En savoir plus
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- carte 2 -->
        <div class="flip-card">
            <div class="flip-card-inner">

                <!-- FRONT SIDE -->
                <div class="flip-card-front">
                    <div class="stat-card bg-gradient-to-r from-green-500 to-teal-500 text-white rounded-lg shadow-md p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-custom font-medium text-white">Reliquat</p>
                                <p class="text-3xl font-bold text-white" id="hr-reliquat">
                                    <?= htmlspecialchars($hrReliquat); ?>
                                </p>
                                <p class="text-sm text-white">
                                    <?php if ($hrReliquat === 1): ?>
                                        jour disponible
                                    <?php else: ?>
                                        jours disponibles
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="p-3 bg-white bg-opacity-25 rounded-full">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BACK SIDE -->
                <div class="flip-card-back">
                    <div class="bg-gradient-to-r from-green-200 to-teal-300 text-gray-900 rounded-lg shadow-md p-6 flex flex-col justify-center items-center">
                        <p class="text-gray-900 text-lg mb-4">Voir plus de détails</p>
                        <button class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition" onclick="window.location.href='hr_history.php'">
                            En savoir plus
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- carte 3 -->
        <div class="flip-card">
            <div class="flip-card-inner">

                <!-- FRONT SIDE -->
                <div class="flip-card-front">
                    <div class="bg-gradient-to-r from-orange-400 to-orange-500 text-white rounded-lg shadow-md p-6 relative overflow-hidden card-hover">
                        <div class="flex items-center justify-between relative z-10">
                            <div>
                                <p class="text-custom font-medium text-white">reliquats faibles</p>
                                <p class="text-3xl font-bold text-white" id="expiring-leave">
                                    <?= htmlspecialchars($expiringleave); ?>
                                </p>
                                <p class="text-sm text-white">Attention requise</p>
                            </div>
                            <div class="p-3 bg-white bg-opacity-25 rounded-full">
                                <svg class="w-6 h-6 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BACK SIDE -->
                <div class="flip-card-back">
                    <div class="bg-gradient-to-r from-orange-200 to-orange-300 text-gray-900 rounded-lg shadow-md p-6 flex flex-col justify-center items-center">
                        <p class="text-gray-900 text-lg mb-4">Voir les détails</p>
                        <button onclick="toggleLowLeaveModal()" class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">
                            Savoir plus
                        </button>
                    </div>
                </div>

            </div>
        </div>
        <!-- carte 4 -->
        <div class="flip-card">
            <div class="flip-card-inner">

                <!-- FRONT SIDE -->
                <div class="flip-card-front">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-700 text-white rounded-lg shadow-md p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-custom font-medium text-white">Personnel absent</p>
                                <p class="text-3xl font-bold text-white" id="absent-staff"><?= $absentstaff; ?></p>
                                <p class="text-sm text-white">Aujourd'hui</p>
                            </div>
                            <div class="p-3 bg-white bg-opacity-25 rounded-full">
                                <i class="w-6 h-6 text-white fa-solid fa-plane-departure"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BACK SIDE -->
                <div class="flip-card-back">
                    <div class="bg-gradient-to-r from-purple-300 to-purple-400 text-gray-900 rounded-lg shadow-md p-6 flex flex-col justify-center items-center">
                        <p class="text-gray-900 text-lg mb-4">Voir plus de détails</p>
                        <button class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 transition" onclick="showAbsentStaff()">
                            En savoir plus
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- Upcoming Holidays Card -->
        <div class="flip-card holidays"  style="height: 328px;">
            <div class="flip-card-inner h-full">

                <!-- FRONT SIDE -->
                <div class="flip-card-front">
                    <div class="bg-gradient-to-r from-yellow-400 to-yellow-500 text-white rounded-lg shadow-md p-6 card-hover flex flex-col h-full relative">
                        <!-- Top-right icon -->
                        <div class="absolute top-4 right-4 p-3 bg-white bg-opacity-25 rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="flex flex-col justify-center h-full">
                            <p class="text-2xl md:text-3xl font-semibold text-white mb-4">Jours fériés de <?= $monthLabel ?></p>
                            <?php if (count($upcomingHolidays) > 0): ?>
                                <ul class="space-y-2 text-lg md:text-xl max-h-[400px] overflow-y-auto">
                                    <?php foreach ($upcomingHolidays as $holiday): ?>
                                        <li><?= date('d/m/Y', strtotime($holiday['holiday_start'])) ?> - <?= htmlspecialchars($holiday['holiday_name']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-lg">Aucun jour férié ce mois-ci</p>
                            <?php endif; ?>
                        </div>
                    </div>


                </div>


                <!-- BACK SIDE -->
                <div class="flip-card-back h-full">
                    <div class="bg-gradient-to-r from-yellow-200 to-yellow-300 text-gray-900 rounded-lg shadow-md p-6 flex flex-col justify-center items-center h-full">
                        <p class="text-yellow-900 text-3xl font-semibold mb-6 text-center">Voir tous les jours fériés</p>
                        <button class="bg-yellow-500 text-white px-6 py-3 rounded-lg hover:bg-yellow-600 transition text-lg" onclick="window.location.href='hr_holidays.php'">
                            En savoir plus
                        </button>
                    </div>
                </div>


            </div>
        </div>

        <!-- Mini Calendar Widget -->
        <div class="w-[520px]">
        <div class="flip-card-inner">
            <div class="flip-card-front">
            <div class="bg-gradient-to-r from-cyan-500 to-blue-500 text-white rounded-lg shadow-md p-6 card-hover">

                <!-- Header with navigation -->
                <div class="flex items-center justify-between mb-3">
                <button onclick="changeMonth(-1)" class="bg-white bg-opacity-25 px-2 py-1 rounded hover:bg-opacity-40">&lt;</button>
                <p id="calendar-title" class="text-lg font-semibold">Loading...</p>
                <button onclick="changeMonth(1)" class="bg-white bg-opacity-25 px-2 py-1 rounded hover:bg-opacity-40">&gt;</button>
                </div>

                <!-- Calendar Grid -->
                <div id="mini-calendar" 
                    class="grid grid-cols-7 gap-1 text-center text-gray-800 bg-white rounded-md p-2">
                <!-- JS will populate -->
                </div>

                <!-- Legend -->
                <div class="flex justify-center gap-4 mt-4 text-sm">
                <div class="flex items-center gap-1">
                    <span class="w-3 h-3 bg-yellow-400 rounded"></span> Jour férié
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-3 h-3 bg-blue-300 rounded"></span> Aujourd'hui
                </div>
                </div>

            </div>
            </div>
        </div>
        </div>




    </div>

        </div>






        <!-- Modal -->
        <div id="lowLeaveModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 opacity-0 transition-opacity duration-300">
            <div class="bg-white rounded-lg p-6 w-96 max-w-full transform scale-95 transition-all duration-300 opacity-0">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-gray-800">Employés avec peu de congés</h2>
                    <button onclick="toggleLowLeaveModal()" class="text-gray-500 hover:text-gray-800 font-bold">&times;</button>
                </div>
                <div id="low-leave-container" class="max-h-64 overflow-y-auto">
                    <?php if ($totalLowLeave > 0): ?>
                        <ul class="space-y-2">
                            <?php foreach ($lowLeaveUsers as $user): ?>
                                <li class="flex justify-between border-b border-gray-200 pb-1">
                                    <span><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                    <span class="font-semibold text-red-600"><?= $user['leave_balance']; ?> jours</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-center text-gray-500">Aucun employé n’a moins de 2 jours de congés</p>
                    <?php endif; ?>
                </div>
                <div class="text-center mt-4">
                    <button onclick="toggleLowLeaveModal()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Fermer</button>
                </div>
            </div>
        </div>





    </div>
</div>
<!-- Modal: Absent Staff -->
<div id="absentStaffModal" 
     class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-lg p-6 w-[600px] max-w-full transform scale-95 transition-all duration-300 opacity-0">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-800">Personnel absent aujourd'hui</h2>
            <button onclick="toggleAbsentStaffModal()" 
                    class="text-gray-500 hover:text-gray-800 font-bold">&times;</button>
        </div>

        <!-- Table container -->
        <div class="overflow-x-auto max-h-96 overflow-y-auto">
            <table class="min-w-full border border-gray-200 rounded-lg">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-700">Nom</th>
                        <th class="px-4 py-2 text-left text-gray-700">Période</th>
                        <th class="px-4 py-2 text-left text-gray-700">Type de congé</th>
                    </tr>
                </thead>
                <tbody id="absent-staff-body" class="divide-y divide-gray-200">
                   
                </tbody>
            </table>
        </div>

        <div class="text-center mt-4">
            <button onclick="toggleAbsentStaffModal()" 
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
                Fermer
            </button>
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

            <form id="leaveRequestForm" method="POST" action="./hr_demandes.php">
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

    <script>

        let currentMonth = new Date().getMonth() + 1; // 1-12
        let currentYear = new Date().getFullYear();

        async function renderMiniCalendar(month = currentMonth, year = currentYear) {
            const calendarContainer = document.getElementById("mini-calendar");
            const title = document.getElementById("calendar-title");
            calendarContainer.innerHTML = "";

            // Fetch data for selected month
            const res = await fetch(`calendar_data.php?month=${month}&year=${year}`);
            const data = await res.json();

            // ✅ Only keep holiday days
            const holidayDays = data.holidayDays || [];

            // Title like "November 2025"
            const monthNames = [
                "Janvier", "Février", "Mars", "Avril", "Mai", "Juin",
                "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"
            ];
            title.textContent = `${monthNames[month - 1]} ${year}`;

            // Build calendar
            const today = new Date();
            const firstDay = new Date(year, month - 1, 1).getDay();
            const daysInMonth = new Date(year, month, 0).getDate();

            const weekdays = ["D", "L", "M", "M", "J", "V", "S"];
            weekdays.forEach(day => {
                const div = document.createElement("div");
                div.textContent = day;
                div.className = "font-bold text-gray-700 text-sm";
                calendarContainer.appendChild(div);
            });

            // Empty cells before the first day
            for (let i = 0; i < (firstDay === 0 ? 6 : firstDay - 1); i++) {
                const empty = document.createElement("div");
                calendarContainer.appendChild(empty);
            }

            // Days of the month
            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const div = document.createElement("div");
                div.textContent = day;
                div.className = `
                    p-1 rounded text-sm cursor-pointer transition
                    hover:bg-blue-100
                `;

                // Highlight today
                if (
                    today.getFullYear() === year &&
                    today.getMonth() + 1 === month &&
                    today.getDate() === day
                ) {
                    div.classList.add("bg-blue-300", "text-white", "font-bold");
                }

                // Highlight holidays only
                if (holidayDays.includes(dateStr)) {
                    div.classList.add("bg-yellow-400", "text-white", "font-bold");
                }

                calendarContainer.appendChild(div);
            }
        }

        // Change month
        function changeMonth(direction) {
            currentMonth += direction;

            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            } else if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }

            renderMiniCalendar(currentMonth, currentYear);
        }

        // Initial load
        renderMiniCalendar();




        // Toggle modal visibility
        function toggleAbsentStaffModal() {
            const modal = document.getElementById('absentStaffModal');
            const content = modal.querySelector('div');

            if (modal.classList.contains('hidden')) {
                // Show modal
                modal.classList.remove('hidden', 'opacity-0');
                setTimeout(() => {
                    content.classList.remove('scale-95', 'opacity-0');
                    content.classList.add('scale-100', 'opacity-100');
                }, 10);
            } else {
                // Hide modal
                content.classList.remove('scale-100', 'opacity-100');
                content.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    modal.classList.add('hidden', 'opacity-0');
                }, 300);
            }
        }

async function showAbsentStaff() {
    try {
        // Fetch absent staff data
        const response = await fetch('absent_staff.php');

        if (!response.ok) {
            throw new Error(`HTTP ${response.status} - ${response.statusText}`);
        }

        const data = await response.json();
        const tbody = document.getElementById('absent-staff-body');
        tbody.innerHTML = ''; // Clear previous content

        // If the API returned an error object
        if (data && data.error) {
            console.error('Server error:', data);
            tbody.innerHTML = `
                <tr>
                    <td colspan="3" class="px-4 py-2 text-center text-red-500">
                        Erreur du serveur : ${data.message || data.error}
                    </td>
                </tr>
            `;
            return;
        }

        // If there is data
        if (Array.isArray(data) && data.length > 0) {
            data.forEach(staff => {
                const tr = document.createElement('tr');
                tr.classList.add('hover:bg-gray-50', 'transition'); // Styling for hover effect
                tr.innerHTML = `
                    <td class="px-4 py-2 border-b">
                        ${staff.first_name || ''} ${staff.last_name || ''}
                    </td>
                    <td class="px-4 py-2 border-b">
                        ${staff.start_date} → ${staff.end_date}
                    </td>
                    <td class="px-4 py-2 border-b">
                        ${staff.type_name || ''}
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            // If empty, show a "no absent staff" message with colspan
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td colspan="3" class="px-4 py-2 text-center text-gray-500">
                    Aucun employé absent aujourd'hui
                </td>
            `;
            tbody.appendChild(tr);
        }

        // Finally, toggle the modal open
        toggleAbsentStaffModal();

    } catch (error) {
        console.error('Error fetching absent staff:', error);
        const tbody = document.getElementById('absent-staff-body');
        tbody.innerHTML = `
            <tr>
                <td colspan="3" class="px-4 py-2 text-center text-red-500">
                    Une erreur est survenue lors du chargement des données.
                </td>
            </tr>
        `;
    }
}





      document.querySelectorAll('.flip-card').forEach(card => {
        card.addEventListener('click', () => {
        card.classList.toggle('flipped');
        });
      });


        async function updateDashboard() {
            try {
                const res = await fetch('./dashboard_data.php');
                const data = await res.json();

                // Update numbers
                document.getElementById('total-users').textContent = data.totalUsers;
                document.getElementById('expiring-leave').textContent = data.expiringLeave;
                document.getElementById('hr-reliquat').textContent = data.hrReliquat;
                document.getElementById('absent-staff').textContent = data.absentStaff;

                // Update Low Leave Users list
                const container = document.getElementById('low-leave-container');
                container.innerHTML = '';

                if (data.lowLeaveUsers.length > 0) {
                    const ul = document.createElement('ul');
                    ul.classList.add('space-y-2');

                    data.lowLeaveUsers.forEach(user => {
                        const li = document.createElement('li');
                        li.classList.add('flex', 'justify-between', 'border-b', 'border-gray-200', 'pb-1');

                        const name = document.createElement('span');
                        name.textContent = `${user.first_name} ${user.last_name}`;

                        const balance = document.createElement('span');
                        balance.classList.add('font-semibold', 'text-red-600');
                        balance.textContent = `${user.leave_balance} jours`;

                        li.appendChild(name);
                        li.appendChild(balance);
                        ul.appendChild(li);
                    });

                    container.appendChild(ul);
                } else {
                    const p = document.createElement('p');
                    p.classList.add('text-center', 'text-gray-500');
                    p.textContent = 'Aucun employé n’a moins de 2 jours de congés';
                    container.appendChild(p);
                }

            } catch (err) {
                console.error('Error updating dashboard:', err);
            }
        }

        // Call every 5 seconds (adjust as needed)
        setInterval(updateDashboard, 5000);

        // Initial load
        updateDashboard();


        function toggleLowLeaveModal() {
            const modal = document.getElementById('lowLeaveModal');
            const content = modal.querySelector('div');

            if (modal.classList.contains('hidden')) {
                // Show modal
                modal.classList.remove('hidden', 'opacity-0');
                setTimeout(() => {
                    content.classList.remove('scale-95', 'opacity-0');
                    content.classList.add('scale-100', 'opacity-100');
                }, 10); // tiny delay to allow transition
            } else {
                // Hide modal
                content.classList.remove('scale-100', 'opacity-100');
                content.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    modal.classList.add('hidden', 'opacity-0');
                }, 300); // match transition duration
            }
        }

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="common.js?v=<?= time(); ?>"></script>
    <script src="hr_dashboard.js?v=<?= time(); ?>"></script>
</body>
</html>