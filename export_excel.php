<?php
session_name('LeaveTrackr_HR');
session_start();

require_once 'config.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

ob_start(); // Start output buffering

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$sheet->setCellValue('A1', 'Prénom')
      ->setCellValue('B1', 'Nom')
      ->setCellValue('C1', 'Reliquat')
      ->setCellValue('D1', 'Rôle')
      ->setCellValue('E1', 'BU')
      ->setCellValue('F1', "Date d'embauche");

$row = 2;

// If filtered data was submitted via POST
if (isset($_POST['filteredData'])) {
    $filteredData = json_decode($_POST['filteredData'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die('Error: Invalid JSON data received: ' . json_last_error_msg());
    }

    foreach ($filteredData as $user) {
        $sheet->setCellValue('A' . $row, $user['first_name'] ?? '')
              ->setCellValue('B' . $row, $user['last_name'] ?? '')
              ->setCellValue('C' . $row, $user['leave_balance'] ?? '')
              ->setCellValue('D' . $row, $user['role'] ?? '')
              ->setCellValue('E' . $row, $user['bu_name'] ?? '')
              ->setCellValue('F' . $row, $user['created_at'] ?? '');
        $row++;
    }
} else {
    // Fallback: export all users directly from DB
    $result = $conn->query("SELECT first_name, last_name, leave_balance, role, bu_name, created_at FROM users");
    if (!$result) {
        die('Database query failed: ' . $conn->error);
    }

    while ($user = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $user['first_name'])
              ->setCellValue('B' . $row, $user['last_name'])
              ->setCellValue('C' . $row, $user['leave_balance'])
              ->setCellValue('D' . $row, $user['role'])
              ->setCellValue('E' . $row, $user['bu_name'])
              ->setCellValue('F' . $row, date('d-m-Y', strtotime($user['created_at'])));
        $row++;
    }
}

// Send as downloadable Excel file
$filename = "users_export_" . date('Y-m-d_H-i-s') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
ob_end_flush();
exit;