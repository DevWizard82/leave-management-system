<?php
require_once 'hr_header.php';
require_once 'config.php'; // Make sure this connects $conn

// ---------------------
// 1️⃣ Activity data (new users per month)
// ---------------------
$activityLabels = ["Jan","Fév","Mar","Avr","Mai","Juin"]; // preview
$activityData = array_fill(0, 6, 0); // preview first 6 months

$activityFullLabels = ["Jan","Fév","Mar","Avr","Mai","Juin","Juil","Août","Sept","Oct","Nov","Déc"];
$activityFullData = array_fill(0, 12, 0); // full year

$sql = "SELECT MONTH(created_at) AS month, COUNT(*) AS count 
        FROM users 
        WHERE status='Active'
        GROUP BY MONTH(created_at)";
$result = $conn->query($sql);

if ($result) {
    while($row = $result->fetch_assoc()) {
        $monthIndex = (int)$row['month'] - 1;
        if ($monthIndex < 6) $activityData[$monthIndex] = (int)$row['count'];
        if ($monthIndex < 12) $activityFullData[$monthIndex] = (int)$row['count'];
    }
}

// ---------------------
// 2️⃣ Leave requests by type
// ---------------------
$leaveLabels = [];
$leaveData = [];

$sql = "SELECT lt.type_name, COUNT(lr.id) AS count 
        FROM leave_requests lr
        JOIN leave_types lt ON lr.type_id = lt.id
        GROUP BY lr.type_id";
$result = $conn->query($sql);

if ($result) {
    while($row = $result->fetch_assoc()) {
        $leaveLabels[] = $row['type_name'];
        $leaveData[] = (int)$row['count'];
    }
}

// ---------------------
// 3️⃣ Attendance / approved leave days per month
// ---------------------
$attendanceLabels = ["Jan","Fév","Mar","Avr","Mai","Juin"]; // preview
$attendanceData = array_fill(0, 6, 0);

$attendanceFullLabels = ["Jan","Fév","Mar","Avr","Mai","Juin","Juil","Août","Sept","Oct","Nov","Déc"];
$attendanceFullData = array_fill(0, 12, 0);

$sql = "SELECT MONTH(start_date) AS month, SUM(days_requested) AS days
        FROM leave_requests
        WHERE status='approved'
        GROUP BY MONTH(start_date)";
$result = $conn->query($sql);

if ($result) {
    while($row = $result->fetch_assoc()) {
        $monthIndex = (int)$row['month'] - 1;
        if ($monthIndex < 6) $attendanceData[$monthIndex] = (float)$row['days'];
        if ($monthIndex < 12) $attendanceFullData[$monthIndex] = (float)$row['days'];
    }
}

$topPreview = 5;
$topFull = 10;

$previewLabels = [];
$previewData = [];
$fullLabels = [];
$fullData = [];

// Step 1: SELECT top employees ordered by leave_balance
$sql = "SELECT first_name, last_name, leave_balance 
        FROM users 
        WHERE status='Active' 
        ORDER BY leave_balance DESC 
        LIMIT $topFull"; // full chart data

$result = $conn->query($sql);

if ($result) {
    $counter = 0;
    while($row = $result->fetch_assoc()) {
        $fullLabels[] = $row['first_name'] . ' ' . $row['last_name'];
        $fullData[] = (float)$row['leave_balance'];
        if ($counter < $topPreview) {
            $previewLabels[] = $row['first_name']; // maybe just first name for preview
            $previewData[] = (float)$row['leave_balance'];
        }
        $counter++;
    }
}
?>

<div id="reports-page" class="page-content p-4 md:p-8">
    <div class="mb-6 md:mb-8">
        <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">Rapports et statistiques</h2>
        <p class="text-gray-600">Visualisez les activités et la répartition des congés</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <!-- Activity -->
        <div class="bg-white rounded-lg shadow-md p-4 cursor-pointer hover:shadow-lg transition h-56" data-chart="topLeave">
            <h3 class="text-sm font-semibold text-gray-800 mb-2">Activité utilisateurs</h3>
            <canvas id="topLeave" class="w-full h-full"></canvas>
        </div>

        <!-- Leave -->
        <div class="bg-white rounded-lg shadow-md p-4 cursor-pointer hover:shadow-lg transition h-56" data-chart="leaveChartPreview">
            <h3 class="text-sm font-semibold text-gray-800 mb-2">Répartition congés</h3>
            <canvas id="leaveChartPreview" class="w-full h-full"></canvas>
        </div>

        <!-- Attendance -->
        <div class="bg-white rounded-lg shadow-md p-4 cursor-pointer hover:shadow-lg transition h-56" data-chart="attendanceChartPreview">
            <h3 class="text-sm font-semibold text-gray-800 mb-2">Présence mensuelle</h3>
            <canvas id="attendanceChartPreview" class="w-full h-full"></canvas>
        </div>
    </div>


    <div id="chartModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 relative w-full max-w-3xl h-3/4 md:h-auto flex flex-col items-center justify-center">
            <!-- Close button -->
            <button id="closeChartModal" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 text-2xl font-bold">&times;</button>

            <!-- Modal title -->
            <h3 id="modalTitle" class="text-lg font-semibold text-gray-800 mb-4"></h3>

            <!-- Expanded chart -->
            <div class="w-full h-96 flex items-center justify-center">
                <canvas id="expandedChart"></canvas>
            </div>
        </div>
    </div>




</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="common.js?v=<?= time(); ?>"></script>
<script src="reports.js?v=<?= time(); ?>"></script>
<script>
window.chartData = {
  topLeave: {
    labels: <?= json_encode($previewLabels) ?>,    // top 5 employees for preview
    data: <?= json_encode($previewData) ?>,        // their leave_balance
    fullLabels: <?= json_encode($fullLabels) ?>,   // top 10 employees full names
    fullData: <?= json_encode($fullData) ?>       // their leave_balance
  },
  leaveTypes: {
    labels: <?= json_encode($leaveLabels) ?>,
    data: <?= json_encode($leaveData) ?>
  },
  attendance: {
    labels: <?= json_encode($attendanceLabels) ?>,
    data: <?= json_encode($attendanceData) ?>,
    fullLabels: <?= json_encode($attendanceFullLabels) ?>,
    fullData: <?= json_encode($attendanceFullData) ?>
  }
};


</script>


</body>
</html>
