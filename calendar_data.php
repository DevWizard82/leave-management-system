<?php
require_once 'config.php';
header('Content-Type: application/json');

// Get month & year from query params or default to current
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year  = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate
if ($month < 1 || $month > 12) $month = date('m');
if ($year < 1970) $year = date('Y');

// Start and end of the month
$startDate = date("Y-m-01", strtotime("$year-$month-01"));
$endDate   = date("Y-m-t", strtotime("$year-$month-01"));


// Holiday days
$holidayQuery = "
    SELECT DATE(holiday_start) AS date
    FROM holidays
    WHERE holiday_start BETWEEN '$startDate' AND '$endDate'
";
$holidayDays = [];
$result = $conn->query($holidayQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $holidayDays[] = $row['date'];
    }
}

// Output JSON
echo json_encode([
    'month'       => $month,
    'year'        => $year,
    'holidayDays' => $holidayDays
]);
exit;
