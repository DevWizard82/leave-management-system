<?php
require_once 'config.php';

require_once 'C:/xampp/htdocs/LeaveManager/hijri.class.php';

use hijri\Calendar;

// -------------------- INIT --------------------
$today = new DateTime();
$currentYear = (int)$today->format('Y');

$calendar = new Calendar();

// -------------------- FIXED HOLIDAYS --------------------
$fixedHolidays = [
    ["name" => "Nouvel An", "day" => 1, "month" => 1],
    ["name" => "Manifeste de l'indépendance", "day" => 11, "month" => 1],
    ["name" => "Nouvel An Amazigh", "day" => 14, "month" => 1],
    ["name" => "Fête du Travail", "day" => 1, "month" => 5],
    ["name" => "Fête du Trône", "day" => 30, "month" => 7],
    ["name" => "Allégeance Oued Eddahab", "day" => 14, "month" => 8],
    ["name" => "Révolution du Roi et du Peuple", "day" => 20, "month" => 8],
    ["name" => "Fête de la Jeunesse", "day" => 21, "month" => 8],
    ["name" => "Marche Verte", "day" => 6, "month" => 11],
    ["name" => "Fête de l'indépendance", "day" => 18, "month" => 11]
];

// -------------------- ISLAMIC HOLIDAYS --------------------
$islamicHolidays = [
    ["name" => "1er Moharram", "day" => 1, "month" => 1],
    ["name" => "Aïd Al Mawlid", "day" => 12, "month" => 3],
    ["name" => "Aïd al-Fitr", "day" => 1, "month" => 10],
    ["name" => "Aïd al-Adha", "day" => 10, "month" => 12]
];

// -------------------- INSERT OR UPDATE FUNCTION --------------------
function insertOrUpdateHoliday($conn, $name, $start) {
    $nameEsc = $conn->real_escape_string($name);

    $sql = "INSERT INTO holidays (holiday_name, holiday_start, holiday_end, created_at, updated_at)
            VALUES ('$nameEsc', '$start', '$start', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                holiday_start='$start',
                holiday_end='$start',
                updated_at=NOW()";

    if ($conn->query($sql)) {
        echo "Jour férié inséré/mis à jour: $name ($start)\n";
    } else {
        echo "Erreur pour $name: " . $conn->error . "\n";
    }
}

// -------------------- PROCESS FIXED HOLIDAYS --------------------
foreach ($fixedHolidays as $h) {
    $holidayDate = DateTime::createFromFormat('Y-m-d', "$currentYear-{$h['month']}-{$h['day']}");
    if ($holidayDate < $today) {
        $holidayDate->modify('+1 year');
    }
    $start = $holidayDate->format('Y-m-d');
    insertOrUpdateHoliday($conn, $h['name'], $start);
}

// -------------------- PROCESS ISLAMIC HOLIDAYS --------------------
// 1️⃣ Get current Hijri year
$todayHijri = $calendar->GregorianToHijri(
    (int)$today->format('Y'),
    (int)$today->format('m'),
    (int)$today->format('d')
);
$currentHijriYear = $todayHijri['y'];

// 2️⃣ Loop through Islamic holidays
foreach ($islamicHolidays as $h) {
    $gregorianArray = $calendar->HijriToGregorian($currentHijriYear, $h['month'], $h['day']);
    $holidayDate = DateTime::createFromFormat(
        'Y-m-d',
        sprintf('%04d-%02d-%02d', $gregorianArray['y'], $gregorianArray['m'], $gregorianArray['d'])
    );

    // If already passed, use next Hijri year
    if ($holidayDate < $today) {
        $gregorianArray = $calendar->HijriToGregorian($currentHijriYear + 1, $h['month'], $h['day']);
        $holidayDate = DateTime::createFromFormat(
            'Y-m-d',
            sprintf('%04d-%02d-%02d', $gregorianArray['y'], $gregorianArray['m'], $gregorianArray['d'])
        );
    }

        // ✅ Add one day
    $holidayDate->modify('+1 day');

    $start = $holidayDate->format('Y-m-d');
    insertOrUpdateHoliday($conn, $h['name'], $start);
}

$conn->close();
?>
